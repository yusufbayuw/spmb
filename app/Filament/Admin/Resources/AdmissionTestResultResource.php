<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AdmissionTestResultResource\Pages;
use App\Models\AdmissionTestResult;
use App\Services\RegistrationWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdmissionTestResultResource extends Resource
{
    protected static ?string $model=AdmissionTestResult::class;protected static ?string $navigationIcon='heroicon-o-academic-cap';protected static ?string $navigationLabel='Hasil Tes';protected static ?string $navigationGroup='Seleksi';protected static ?int $navigationSort=2;
    public static function form(Form $form):Form{return $form->schema([Forms\Components\Select::make('registration_id')->relationship('registration','registration_number')->label('Pendaftaran')->searchable()->preload()->required(),Forms\Components\Select::make('admission_test_id')->relationship('admissionTest','name')->label('Tes')->preload()->required(),Forms\Components\Select::make('status')->options(['scheduled'=>'Terjadwal','completed'=>'Selesai','absent'=>'Tidak Hadir','exempted'=>'Dibebaskan'])->required(),Forms\Components\TextInput::make('score')->numeric()->label('Nilai'),Forms\Components\Select::make('result')->options(['pending'=>'Belum Dinilai','pass'=>'Lulus','fail'=>'Tidak Lulus'])->required(),Forms\Components\Textarea::make('notes')->label('Catatan')]);}
    public static function table(Table $table):Table{return $table->columns([Tables\Columns\TextColumn::make('registration.registration_number')->label('No. Registrasi'),Tables\Columns\TextColumn::make('registration.full_name')->label('Calon Siswa')->searchable(),Tables\Columns\TextColumn::make('admissionTest.name')->label('Tes'),Tables\Columns\TextColumn::make('score')->label('Nilai'),Tables\Columns\TextColumn::make('result')->badge(),Tables\Columns\TextColumn::make('status')->badge()])->actions([Tables\Actions\Action::make('record')->label('Catat Hasil')->visible(fn()=>auth()->user()?->can('record_result_admissiontestresult'))->form([Forms\Components\Select::make('status')->options(['completed'=>'Selesai','absent'=>'Tidak Hadir','exempted'=>'Dibebaskan'])->required(),Forms\Components\TextInput::make('score')->numeric(),Forms\Components\Select::make('result')->options(['pass'=>'Lulus','fail'=>'Tidak Lulus'])->required(),Forms\Components\Textarea::make('notes')])->action(fn(AdmissionTestResult $record,array $data)=>app(RegistrationWorkflowService::class)->recordTestResult($record,auth()->user(),$data)),Tables\Actions\EditAction::make()]);}
    public static function getPages():array{return ['index'=>Pages\ListAdmissionTestResults::route('/'),'create'=>Pages\CreateAdmissionTestResult::route('/create'),'edit'=>Pages\EditAdmissionTestResult::route('/{record}/edit')];}
    public static function getEloquentQuery():Builder{$q=parent::getEloquentQuery()->with(['registration.unit','admissionTest']);if(auth()->user()?->isTU())$q->whereHas('registration',fn(Builder $x)=>$x->where('unit_id',auth()->user()->unit_id));return $q;}
}
