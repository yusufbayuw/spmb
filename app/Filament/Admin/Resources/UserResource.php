<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model=User::class;protected static ?string $navigationIcon='heroicon-o-user-group';protected static ?string $navigationLabel='Pengguna';protected static ?string $navigationGroup='Akses & Keamanan';
    public static function form(Form $form):Form{return $form->schema([Forms\Components\TextInput::make('name')->required(),Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord:true),Forms\Components\TextInput::make('phone')->tel(),Forms\Components\Select::make('roles')->relationship('roles','name')->multiple()->preload()->searchable()->required(),Forms\Components\Select::make('unit_id')->relationship('unit','name')->label('Unit')->preload(),Forms\Components\TextInput::make('password')->password()->revealable()->dehydrated(fn($state)=>filled($state))->required(fn(string $context)=>$context==='create'),Forms\Components\Toggle::make('is_active')->default(true)]);}
    public static function table(Table $table):Table{return $table->columns([Tables\Columns\TextColumn::make('name')->searchable(),Tables\Columns\TextColumn::make('email')->searchable(),Tables\Columns\TextColumn::make('roles.name')->label('Role')->badge(),Tables\Columns\TextColumn::make('unit.name')->label('Unit')->default('-'),Tables\Columns\IconColumn::make('is_active')->boolean()])->actions([Tables\Actions\EditAction::make(),Tables\Actions\DeleteAction::make()]);}
    public static function getPages():array{return ['index'=>Pages\ListUsers::route('/'),'create'=>Pages\CreateUser::route('/create'),'edit'=>Pages\EditUser::route('/{record}/edit')];}
}
