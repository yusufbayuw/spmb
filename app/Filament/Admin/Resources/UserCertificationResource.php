<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserCertificationResource\Pages;
use App\Models\UserCertification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserCertificationResource extends Resource
{
    protected static ?string $model = UserCertification::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Sertifikat Staff';
    protected static ?string $modelLabel = 'Sertifikat Staff';
    protected static ?string $pluralModelLabel = 'Sertifikat Staff';
    protected static ?string $navigationGroup = 'Training & Sertifikasi';
    protected static ?int $navigationSort = 22;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) $user?->is_active
            && ($user->isAdmin() || ($user->isAdminUnit() && $user->hasOperationalUnitAccess()));
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user.unit', 'program'])
            ->when(
                auth()->user()?->isAdminUnit(),
                fn (Builder $query): Builder => $query->whereHas(
                    'user',
                    fn (Builder $userQuery): Builder => $userQuery->where('unit_id', auth()->user()->unit_id),
                ),
            );
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('issued_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('certificate_number')->label('Nomor')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('user.name')->label('Nama')->searchable(),
            Tables\Columns\TextColumn::make('user.unit.name')->label('Unit')->placeholder('Admin Pusat'),
            Tables\Columns\TextColumn::make('program.name')->label('Sertifikasi'),
            Tables\Columns\TextColumn::make('score')->label('Nilai')->numeric(decimalPlaces: 2),
            Tables\Columns\TextColumn::make('issued_at')->label('Terbit')->dateTime('d/m/Y H:i'),
            Tables\Columns\TextColumn::make('expires_at')->label('Berlaku Sampai')->date('d/m/Y')->placeholder('Tanpa batas'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                ->formatStateUsing(fn (string $state, UserCertification $record): string => match ($record->effectiveStatus()) {
                    'active' => 'Aktif',
                    'expired' => 'Kedaluwarsa',
                    'revoked' => 'Dicabut',
                    default => $state,
                }),
        ])->actions([
            Tables\Actions\Action::make('verify')->label('Buka')->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (UserCertification $record): string => route('certificates.verify', $record))->openUrlInNewTab(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUserCertifications::route('/')];
    }
}
