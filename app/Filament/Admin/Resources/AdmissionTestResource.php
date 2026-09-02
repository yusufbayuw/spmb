<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AdmissionTestResource\Pages;
use App\Models\AdmissionTest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdmissionTestResource extends Resource
{
    protected static ?string $model=AdmissionTest::class;protected static ?string $navigationIcon='heroicon-o-clipboard-document-check';protected static ?string $navigationLabel='Konfigurasi Tes';protected static ?string $navigationGroup='Seleksi';protected static ?int $navigationSort=1;
    public static function form(Form $form):Form{return $form->schema([Forms\Components\Select::make('unit_id')->relationship('unit','name')->label('Unit')->default(fn()=>auth()->user()?->isTU()?auth()->user()->unit_id:null)->disabled(fn()=>auth()->user()?->isTU()??false)->dehydrated()->required(),Forms\Components\TextInput::make('name')->label('Nama Tes')->required(),Forms\Components\TextInput::make('code')->label('Kode'),Forms\Components\Textarea::make('description')->label('Deskripsi'),Forms\Components\TextInput::make('sort_order')->numeric()->default(0),Forms\Components\Select::make('result_type')->options(['score'=>'Nilai','pass_fail'=>'Lulus/Tidak'])->default('score')->required(),Forms\Components\TextInput::make('passing_score')->numeric()->label('Nilai Minimum'),Forms\Components\DateTimePicker::make('scheduled_at')->label('Jadwal'),Forms\Components\TextInput::make('location')->label('Lokasi'),Forms\Components\Toggle::make('is_required')->default(true),Forms\Components\Toggle::make('is_active')->default(true)]);}
    public static function table(Table $table):Table{return $table->reorderable('sort_order')->columns([Tables\Columns\TextColumn::make('unit.name')->badge(),Tables\Columns\TextColumn::make('name')->searchable(),Tables\Columns\TextColumn::make('scheduled_at')->dateTime('d M Y H:i')->default('-'),Tables\Columns\TextColumn::make('location')->default('-'),Tables\Columns\IconColumn::make('is_required')->boolean(),Tables\Columns\ToggleColumn::make('is_active')])->actions([Tables\Actions\EditAction::make(),Tables\Actions\DeleteAction::make()]);}
    public static function getPages():array{return ['index'=>Pages\ListAdmissionTests::route('/'),'create'=>Pages\CreateAdmissionTest::route('/create'),'edit'=>Pages\EditAdmissionTest::route('/{record}/edit')];}
    public static function getEloquentQuery():Builder{$q=parent::getEloquentQuery();if(auth()->user()?->isTU())$q->where('unit_id',auth()->user()->unit_id);return $q;}
}
