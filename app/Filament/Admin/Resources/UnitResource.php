<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnitResource extends Resource
{
    protected static ?string $model=Unit::class;protected static ?string $navigationIcon='heroicon-o-building-office-2';protected static ?string $navigationLabel='Unit Sekolah';protected static ?string $navigationGroup='Master Data';
    public static function form(Form $form):Form{return $form->schema([Forms\Components\TextInput::make('name')->required(),Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord:true),Forms\Components\Textarea::make('description'),Forms\Components\Toggle::make('is_active')->default(true)]);}
    public static function table(Table $table):Table{return $table->columns([Tables\Columns\TextColumn::make('name')->searchable(),Tables\Columns\TextColumn::make('code')->badge(),Tables\Columns\TextColumn::make('registrations_count')->counts('registrations')->label('Pendaftar'),Tables\Columns\TextColumn::make('admission_tests_count')->counts('admissionTests')->label('Tes'),Tables\Columns\IconColumn::make('is_active')->boolean()])->actions([Tables\Actions\EditAction::make(),Tables\Actions\DeleteAction::make()]);}
    public static function getPages():array{return ['index'=>Pages\ListUnits::route('/'),'create'=>Pages\CreateUnit::route('/create'),'edit'=>Pages\EditUnit::route('/{record}/edit')];}
    public static function getEloquentQuery():Builder{$q=parent::getEloquentQuery();if(auth()->user()?->isTU())$q->whereKey(auth()->user()->unit_id);return $q;}
}
