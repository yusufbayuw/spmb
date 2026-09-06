<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReRegistrationItemResource\Pages;
use App\Models\ReRegistrationItem;
use App\Services\ReRegistrationService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReRegistrationItemResource extends Resource
{
    protected static ?string $model = ReRegistrationItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Daftar Ulang';

    protected static ?string $navigationGroup = 'Seleksi';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('registration.registration_number')->label('Pendaftaran')->disabled(),
            Forms\Components\TextInput::make('label')->label('Persyaratan')->disabled(),
            Forms\Components\TextInput::make('status')->label('Status')->disabled(),
            Forms\Components\Textarea::make('rejection_reason')->label('Alasan penolakan')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('registration.registration_number')->label('No. Registrasi'),
            Tables\Columns\TextColumn::make('registration.full_name')->label('Pendaftar')->searchable(),
            Tables\Columns\TextColumn::make('registration.unit.name')->label('Unit')->badge(),
            Tables\Columns\TextColumn::make('label')->label('Persyaratan'),
            Tables\Columns\TextColumn::make('original_name')->label('Berkas')->url(fn (ReRegistrationItem $record): ?string => $record->file_path ? route('files.applicant.re-registration.show', $record) : null)->openUrlInNewTab()->placeholder('-'),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('verified_at')->label('Diperiksa')->dateTime('d M Y H:i')->placeholder('-'),
        ])->actions([
            Tables\Actions\Action::make('verify')
                ->label('Verifikasi')
                ->visible(fn (ReRegistrationItem $record): bool => in_array($record->status, ['pending', 'submitted', 'rejected'], true))
                ->form([
                    Forms\Components\Toggle::make('approved')->label('Disetujui')->default(true),
                    Forms\Components\Textarea::make('reason')->label('Alasan penolakan')->required(fn (Forms\Get $get): bool => ! $get('approved')),
                ])
                ->action(fn (ReRegistrationItem $record, array $data) => app(ReRegistrationService::class)->verify($record, auth()->user(), (bool) $data['approved'], $data['reason'] ?? null)),
            Tables\Actions\Action::make('enroll')
                ->label('Selesaikan Enrollment')
                ->color('success')
                ->visible(fn (ReRegistrationItem $record): bool => $record->registration?->current_stage === 'enrollment' && (bool) auth()->user()?->can('enroll_registration'))
                ->requiresConfirmation()
                ->action(fn (ReRegistrationItem $record) => app(ReRegistrationService::class)->enroll($record->registration, auth()->user())),
        ])->bulkActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['registration.unit'])
            ->when(auth()->user()?->isTU(), fn (Builder $q) => $q->whereHas('registration', fn (Builder $registration) => $registration->where('unit_id', auth()->user()->unit_id)));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListReRegistrationItems::route('/')];
    }
}
