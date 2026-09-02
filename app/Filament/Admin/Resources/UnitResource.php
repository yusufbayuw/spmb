<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Unit Sekolah';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Unit Sekolah')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Nama Unit')->required()->maxLength(50),
                    Forms\Components\TextInput::make('code')->label('Kode Unit')->required()->maxLength(10)->unique(ignoreRecord: true),
                    Forms\Components\Textarea::make('description')->label('Deskripsi')->maxLength(255)->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama Unit')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->badge(),
                Tables\Columns\TextColumn::make('registrations_count')->label('Pendaftar')->counts('registrations')->alignCenter(),
                Tables\Columns\TextColumn::make('description')->label('Deskripsi')->limit(50)->toggleable(),
                Tables\Columns\ToggleColumn::make('is_active')->label('Aktif'),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
