<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ParentInfoResource\Pages;
use App\Models\ParentInfo;
use App\Models\Registration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParentInfoResource extends Resource
{
    protected static ?string $model = ParentInfo::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Orang Tua / Wali';
    protected static ?string $modelLabel = 'Data Orang Tua';
    protected static ?string $pluralModelLabel = 'Data Orang Tua / Wali';
    protected static ?string $navigationGroup = 'SPMB';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('registration_id')->label('Calon Siswa')->relationship('registration','registration_number')->getOptionLabelFromRecordUsing(fn (Registration $record) => ($record->registration_number ?: '#'.$record->id).' — '.$record->full_name)->searchable(['registration_number','full_name','nik'])->preload()->unique(table:'parent_infos',column:'registration_id',ignoreRecord:true)->required(),
            Forms\Components\Section::make('Ayah')->columns(3)->schema([Forms\Components\TextInput::make('father_name')->label('Nama Ayah')->required()->columnSpan(2),Forms\Components\TextInput::make('father_nik')->label('NIK')->maxLength(16),Forms\Components\TextInput::make('father_occupation')->label('Pekerjaan'),Forms\Components\TextInput::make('father_phone')->label('Telepon')->tel(),Forms\Components\TextInput::make('father_income')->label('Penghasilan')->prefix('Rp')->numeric()]),
            Forms\Components\Section::make('Ibu')->columns(3)->schema([Forms\Components\TextInput::make('mother_name')->label('Nama Ibu')->required()->columnSpan(2),Forms\Components\TextInput::make('mother_nik')->label('NIK')->maxLength(16),Forms\Components\TextInput::make('mother_occupation')->label('Pekerjaan'),Forms\Components\TextInput::make('mother_phone')->label('Telepon')->tel(),Forms\Components\TextInput::make('mother_income')->label('Penghasilan')->prefix('Rp')->numeric()]),
            Forms\Components\Section::make('Wali')->collapsed()->columns(2)->schema([Forms\Components\TextInput::make('guardian_name')->label('Nama Wali'),Forms\Components\TextInput::make('guardian_relationship')->label('Hubungan'),Forms\Components\TextInput::make('guardian_phone')->label('Telepon')->tel(),Forms\Components\Textarea::make('guardian_address')->label('Alamat')->columnSpanFull()]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([Tables\Columns\TextColumn::make('registration.registration_number')->label('No. Registrasi'),Tables\Columns\TextColumn::make('registration.full_name')->label('Calon Siswa')->searchable(),Tables\Columns\TextColumn::make('registration.unit.name')->label('Unit')->badge(),Tables\Columns\TextColumn::make('father_name')->label('Ayah')->searchable(),Tables\Columns\TextColumn::make('mother_name')->label('Ibu')->searchable()])->actions([Tables\Actions\EditAction::make(),Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index'=>Pages\ListParentInfos::route('/'),'create'=>Pages\CreateParentInfo::route('/create'),'edit'=>Pages\EditParentInfo::route('/{record}/edit')]; }
    public static function getEloquentQuery(): Builder { $q=parent::getEloquentQuery()->with('registration.unit'); if(auth()->user()?->isTU()) $q->whereHas('registration',fn(Builder $x)=>$x->where('unit_id',auth()->user()->unit_id)); return $q; }
}
