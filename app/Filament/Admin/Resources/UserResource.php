<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Akun')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                    Forms\Components\TextInput::make('phone')->label('No. Telepon')->tel()->maxLength(20),
                    Forms\Components\Select::make('role')
                        ->label('Role')
                        ->options(['admin' => 'Admin', 'tu' => 'TU', 'user' => 'Pendaftar'])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                            if ($state !== 'tu') {
                                $set('unit_id', null);
                            }
                        }),
                    Forms\Components\Select::make('unit_id')
                        ->relationship('unit', 'name')
                        ->label('Unit')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get) => $get('role') === 'tu')
                        ->required(fn (Forms\Get $get) => $get('role') === 'tu'),
                    Forms\Components\TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context): bool => $context === 'create')
                        ->maxLength(255),
                    Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('Telepon')->toggleable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ['admin' => 'Admin', 'tu' => 'TU', 'user' => 'Pendaftar'][$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'tu' => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('unit.name')->label('Unit')->default('-'),
                Tables\Columns\TextColumn::make('registrations_count')->label('Pendaftaran')->counts('registrations')->alignCenter(),
                Tables\Columns\ToggleColumn::make('is_active')->label('Aktif'),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->label('Role')->options(['admin' => 'Admin', 'tu' => 'TU', 'user' => 'Pendaftar']),
                Tables\Filters\TernaryFilter::make('is_active')->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn (User $record) => $record->id !== auth()->id()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
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
        return (auth()->user()?->isAdmin() ?? false) && $record->getKey() !== auth()->id();
    }
}
