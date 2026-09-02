<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DocumentResource\Pages;
use App\Models\Document;
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
                    ->visible(fn (Document $record) => auth()->user()?->can('verify_document_document') && ! $record->is_verified)
                    ->action(function (Document $record): void {
                        $record->update([
                            'is_verified' => true,
                            'verified_at' => now(),
                            'verified_by' => auth()->id(),
                        ]);
                        app(RegistrationWorkflowService::class)->refreshDocumentStage($record->registration);
                        Notification::make()->title('Berkas diverifikasi')->success()->send();
                    }),
                Tables\Actions\Action::make('reset')
                    ->label('Batalkan')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Document $record) => auth()->user()?->can('verify_document_document') && $record->is_verified)
                    ->action(function (Document $record): void {
                        $record->update([
                            'is_verified' => false,
                            'verified_at' => null,
                            'verified_by' => null,
                        ]);
                        $record->registration->update([
                            'current_stage' => 'document_verification',
                            'documents_verified_at' => null,
                        ]);
                    }),
                Tables\Actions\DeleteAction::make(),
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
            $query->whereHas(
                'registration',
                fn (Builder $registration) => $registration->where('unit_id', auth()->user()->unit_id),
            );
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->where('is_verified', false)->count();
    }
}
