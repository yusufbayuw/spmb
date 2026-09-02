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
            Forms\Components\Section::make('Pendaftaran')
                ->schema([
                    Forms\Components\Select::make('registration_id')
                        ->label('Calon Siswa')
                        ->relationship('registration', 'registration_number', function (Builder $query): Builder {
                            $user = auth()->user();
                            return $user?->isTU() && $user->unit_id
                                ? $query->where('unit_id', $user->unit_id)
                                : $query;
                        })
                        ->getOptionLabelFromRecordUsing(fn (Registration $record) => ($record->registration_number ?: '#'.$record->id).' — '.$record->full_name)
                        ->searchable(['registration_number', 'full_name', 'nik'])
                        ->preload()
                        ->unique(table: 'parent_infos', column: 'registration_id', ignoreRecord: true)
                        ->required(),
                ]),
            Forms\Components\Section::make('Data Ayah')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('father_name')->label('Nama Ayah')->required()->maxLength(150)->columnSpan(2),
                    Forms\Components\TextInput::make('father_nik')->label('NIK Ayah')->length(16),
                    Forms\Components\TextInput::make('father_birth_place')->label('Tempat Lahir')->maxLength(100),
                    Forms\Components\DatePicker::make('father_birth_date')->label('Tanggal Lahir')->native(false),
                    Forms\Components\TextInput::make('father_education')->label('Pendidikan')->maxLength(50),
                    Forms\Components\TextInput::make('father_occupation')->label('Pekerjaan')->maxLength(100),
                    Forms\Components\TextInput::make('father_phone')->label('No. Telepon')->tel()->maxLength(20),
                    Forms\Components\TextInput::make('father_email')->label('Email')->email()->maxLength(100),
                    Forms\Components\TextInput::make('father_income')->label('Penghasilan / Bulan')->numeric()->prefix('Rp')->minValue(0),
                ]),
            Forms\Components\Section::make('Data Ibu')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('mother_name')->label('Nama Ibu')->required()->maxLength(150)->columnSpan(2),
                    Forms\Components\TextInput::make('mother_nik')->label('NIK Ibu')->length(16),
                    Forms\Components\TextInput::make('mother_birth_place')->label('Tempat Lahir')->maxLength(100),
                    Forms\Components\DatePicker::make('mother_birth_date')->label('Tanggal Lahir')->native(false),
                    Forms\Components\TextInput::make('mother_education')->label('Pendidikan')->maxLength(50),
                    Forms\Components\TextInput::make('mother_occupation')->label('Pekerjaan')->maxLength(100),
                    Forms\Components\TextInput::make('mother_phone')->label('No. Telepon')->tel()->maxLength(20),
                    Forms\Components\TextInput::make('mother_email')->label('Email')->email()->maxLength(100),
                    Forms\Components\TextInput::make('mother_income')->label('Penghasilan / Bulan')->numeric()->prefix('Rp')->minValue(0),
                ]),
            Forms\Components\Section::make('Data Wali')
                ->description('Opsional, diisi bila calon siswa memiliki wali selain orang tua.')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('guardian_name')->label('Nama Wali')->maxLength(150),
                    Forms\Components\TextInput::make('guardian_relationship')->label('Hubungan')->maxLength(50),
                    Forms\Components\TextInput::make('guardian_phone')->label('No. Telepon')->tel()->maxLength(20),
                    Forms\Components\Textarea::make('guardian_address')->label('Alamat')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registration.registration_number')->label('No. Registrasi')->searchable()->default('-'),
                Tables\Columns\TextColumn::make('registration.full_name')->label('Calon Siswa')->searchable(),
                Tables\Columns\TextColumn::make('registration.unit.name')->label('Unit')->badge(),
                Tables\Columns\TextColumn::make('father_name')->label('Ayah')->searchable(),
                Tables\Columns\TextColumn::make('father_phone')->label('Telepon Ayah')->toggleable(),
                Tables\Columns\TextColumn::make('mother_name')->label('Ibu')->searchable(),
                Tables\Columns\TextColumn::make('mother_phone')->label('Telepon Ibu')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit')
                    ->label('Unit')
                    ->relationship('registration.unit', 'name')
                    ->visible(fn () => auth()->user()?->isAdmin() ?? false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn () => auth()->user()?->isAdmin() ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParentInfos::route('/'),
            'create' => Pages\CreateParentInfo::route('/create'),
            'edit' => Pages\EditParentInfo::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['registration.unit']);
        $user = auth()->user();

        if ($user?->isTU() && $user->unit_id) {
            $query->whereHas('registration', fn (Builder $query) => $query->where('unit_id', $user->unit_id));
        }

        return $query;
    }
}
