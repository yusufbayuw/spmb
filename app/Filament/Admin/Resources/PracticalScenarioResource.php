<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PracticalScenarioResource\Pages;
use App\Models\CertificationProgram;
use App\Models\PracticalScenario;
use App\Services\PracticalValidators\PracticalValidatorRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PracticalScenarioResource extends Resource
{
    protected static ?string $model = PracticalScenario::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Kelola Practical';
    protected static ?string $modelLabel = 'Practical Scenario';
    protected static ?string $pluralModelLabel = 'Practical Scenario';
    protected static ?string $navigationGroup = 'Training & Sertifikasi';
    protected static ?int $navigationSort = 22;

    public static function canViewAny(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Scenario')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('certification_program_id')
                        ->label('Program Sertifikasi')
                        ->options(fn (): array => CertificationProgram::query()
                            ->orderBy('name')
                            ->orderBy('version')
                            ->get()
                            ->mapWithKeys(fn (CertificationProgram $program): array => [
                                $program->id => $program->name.' · v'.$program->version,
                            ])
                            ->all())
                        ->searchable()->preload()->required(),
                    Forms\Components\TextInput::make('code')
                        ->label('Kode')
                        ->required()
                        ->maxLength(80)
                        ->helperText('Kode scenario unik di dalam versi program sertifikasi terkait.'),
                    Forms\Components\TextInput::make('name')->label('Nama Scenario')->required()->maxLength(180),
                    Forms\Components\TextInput::make('time_limit_minutes')->label('Batas Waktu (menit)')->integer()->minValue(1),
                    Forms\Components\Textarea::make('description')->label('Deskripsi')->rows(2)->columnSpanFull(),
                    Forms\Components\Textarea::make('instructions')->label('Instruksi Peserta')->rows(4)->required()->columnSpanFull(),
                    Forms\Components\TextInput::make('sort_order')->label('Urutan')->integer()->default(0),
                    Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                ]),

            Forms\Components\Repeater::make('records')
                ->label('Data Awal Sandbox')
                ->relationship('records')
                ->orderColumn('sort_order')
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->schema([
                    Forms\Components\TextInput::make('entity_type')->label('Tipe Entity')->required(),
                    Forms\Components\TextInput::make('entity_key')->label('Key Entity')->required(),
                    Forms\Components\TextInput::make('label')->label('Label')->required(),
                    Forms\Components\KeyValue::make('initial_state')
                        ->label('Initial State')
                        ->keyLabel('Field')
                        ->valueLabel('Nilai')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull(),

            Forms\Components\Repeater::make('actions')
                ->label('Aksi Sandbox')
                ->relationship('actions')
                ->orderColumn('sort_order')
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->schema([
                    Forms\Components\TextInput::make('code')->label('Kode Aksi')->required(),
                    Forms\Components\TextInput::make('label')->label('Label Tombol')->required(),
                    Forms\Components\TextInput::make('target_type')->label('Target Type')->required(),
                    Forms\Components\TextInput::make('target_key')->label('Target Key')->required(),
                    Forms\Components\Select::make('button_color')
                        ->label('Warna')
                        ->options([
                            'gray' => 'Abu-abu',
                            'primary' => 'Primary',
                            'success' => 'Hijau',
                            'warning' => 'Kuning',
                            'danger' => 'Merah',
                        ])
                        ->default('gray'),
                    Forms\Components\Toggle::make('requires_confirmation')->label('Minta Konfirmasi'),
                    Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                    Forms\Components\KeyValue::make('mutation')
                        ->label('Perubahan State')
                        ->keyLabel('Path')
                        ->valueLabel('Nilai Baru')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\KeyValue::make('allowed_when')
                        ->label('Syarat State Sebelum Aksi')
                        ->keyLabel('Path')
                        ->valueLabel('Nilai')
                        ->columnSpanFull(),
                ])
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull(),

            Forms\Components\Repeater::make('assertions')
                ->label('Practical Validator')
                ->relationship('assertions')
                ->orderColumn('sort_order')
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->schema([
                    Forms\Components\TextInput::make('code')->label('Kode')->required(),
                    Forms\Components\TextInput::make('name')->label('Nama Assertion')->required(),
                    Forms\Components\Select::make('validator_class')
                        ->label('Validator')
                        ->options(PracticalValidatorRegistry::OPTIONS)
                        ->required(),
                    Forms\Components\TextInput::make('points')->label('Poin')->numeric()->minValue(0.01)->default(1)->required(),
                    Forms\Components\Toggle::make('is_critical')->label('Critical Fail')->default(false),
                    Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                    Forms\Components\KeyValue::make('config')
                        ->label('Konfigurasi Validator')
                        ->keyLabel('Key')
                        ->valueLabel('Nilai')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Kode')->badge()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Scenario')->searchable(),
                Tables\Columns\TextColumn::make('program.code')->label('Sertifikasi')->badge(),
                Tables\Columns\TextColumn::make('program.version')->label('Versi')->badge(),
                Tables\Columns\TextColumn::make('records_count')->counts('records')->label('Record'),
                Tables\Columns\TextColumn::make('actions_count')->counts('actions')->label('Aksi'),
                Tables\Columns\TextColumn::make('assertions_count')->counts('assertions')->label('Validator'),
                Tables\Columns\TextColumn::make('time_limit_minutes')->label('Waktu')->suffix(' mnt')->placeholder('Tanpa batas'),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPracticalScenarios::route('/'),
            'create' => Pages\CreatePracticalScenario::route('/create'),
            'edit' => Pages\EditPracticalScenario::route('/{record}/edit'),
        ];
    }
}
