<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Services\ApplicantUploadSecurity;
use App\Services\RegistrationWorkflowService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Verifikasi Berkas';
    protected static ?string $navigationGroup = 'Verifikasi';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('registration.registration_number')->label('No. Registrasi'),
                Tables\Columns\TextColumn::make('registration.full_name')->label('Calon Siswa')->searchable(),
                Tables\Columns\TextColumn::make('registration.unit.name')->label('Unit')->badge(),
                Tables\Columns\TextColumn::make('type')->label('Jenis')->badge(),
                Tables\Columns\TextColumn::make('original_name')
                    ->label('File')
                    ->url(fn (Document $record): string => route('files.applicant.documents.show', $record)),
                Tables\Columns\TextColumn::make('malware_scan_status')
                    ->label('Security')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'clean' => 'AV Clean',
                        'unavailable' => 'AV Opsional',
                        'scan_error' => 'AV Error',
                        default => 'Belum Scan',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'clean' => 'success',
                        'unavailable' => 'warning',
                        'scan_error' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_verified')->label('Valid')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_verified')->label('Verifikasi'),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->label('Verifikasi')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Document $record) =>
                        auth()->user()?->can('verify_document_document')
                        && ! $record->is_verified
                        && $record->registration?->isOperational()
                        && in_array($record->registration?->current_stage, ['documents', 'document_verification'], true)
                    )
                    ->action(function (Document $record): void {
                        $record->registration->assertCurrentStage(['documents', 'document_verification']);

                        if (! $record->security_scanned_at || ! $record->sha256) {
                            $inspection = app(ApplicantUploadSecurity::class)->inspect($record->file_path);
                            $record->update([
                                'mime_type' => $inspection['mime_type'],
                                'file_size' => $inspection['size'],
                                'sha256' => $inspection['sha256'],
                                'malware_scan_status' => $inspection['malware_scan_status'],
                                'security_scanned_at' => $inspection['security_scanned_at'],
                            ]);
                        }

                        $record->update([
                            'is_verified' => true,
                            'verified_at' => now(),
                            'verified_by' => auth()->id(),
                        ]);

                        app(RegistrationWorkflowService::class)->refreshDocumentStage($record->registration);
                        Notification::make()->title('Berkas lolos pemeriksaan keamanan dan diverifikasi')->success()->send();
                    }),
                Tables\Actions\Action::make('reset')
                    ->label('Batalkan')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Document $record) =>
                        auth()->user()?->can('verify_document_document')
                        && $record->is_verified
                        && $record->registration?->isOperational()
                        && in_array($record->registration?->current_stage, ['documents', 'document_verification'], true)
                    )
                    ->action(function (Document $record): void {
                        $record->registration->assertCurrentStage(['documents', 'document_verification']);
                        $record->update(['is_verified' => false, 'verified_at' => null, 'verified_by' => null]);
                        $record->registration->transitionTo('document_verification', ['documents_verified_at' => null]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDocuments::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('registration.unit');
        if (auth()->user()?->isTU()) {
            $query->whereHas('registration', fn (Builder $registration) => $registration->where('unit_id', auth()->user()->unit_id));
        }
        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->where('is_verified', false)
            ->whereHas('registration', fn (Builder $q) => $q->where('lifecycle_status', 'active'))
            ->count();
    }

    public static function canDelete($record): bool { return false; }
}
