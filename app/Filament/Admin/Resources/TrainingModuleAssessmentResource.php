<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TrainingModuleAssessmentResource\Pages;
use App\Models\TrainingModule;
use App\Models\TrainingModuleAssessment;
use App\Models\TrainingModuleQuestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TrainingModuleAssessmentResource extends Resource
{
    protected static ?string $model = TrainingModuleAssessment::class;
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationLabel = 'Checkpoint Training';
    protected static ?string $navigationGroup = 'Training & Sertifikasi';
    protected static ?int $navigationSort = 21;

    public static function canViewAny(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Checkpoint Modul')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('training_module_id')
                        ->label('Modul')
                        ->options(fn (): array => TrainingModule::query()
                            ->with('program')
                            ->orderBy('training_program_id')
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn (TrainingModule $module): array => [
                                $module->id => $module->program->code.' v'.$module->program->version.' · '.$module->title,
                            ])->all())
                        ->searchable()->preload()->required(),
                    Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(180),
                    Forms\Components\TextInput::make('passing_score')->label('Nilai Lulus')->integer()->minValue(1)->maxValue(100)->default(80)->required(),
                    Forms\Components\TextInput::make('question_count')->label('Soal per Attempt')->integer()->minValue(1)->helperText('Kosong = semua soal aktif.'),
                    Forms\Components\TextInput::make('max_attempts')->label('Maksimum Attempt')->integer()->minValue(1)->helperText('Kosong = tidak dibatasi.'),
                    Forms\Components\Toggle::make('is_required')->label('Wajib')->default(true),
                    Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                    Forms\Components\Textarea::make('description')->label('Deskripsi')->rows(2)->columnSpanFull(),
                ]),
            Forms\Components\Repeater::make('questions')
                ->relationship('questions')
                ->label('Bank Soal Checkpoint')
                ->orderColumn('sort_order')
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => filled($state['question'] ?? null)
                    ? str($state['question'])->limit(80)->toString()
                    : null)
                ->schema([
                    Forms\Components\Select::make('type')->label('Jenis')->options(TrainingModuleQuestion::TYPES)->default('single_choice')->live()->required(),
                    Forms\Components\TextInput::make('weight')->label('Bobot')->numeric()->minValue(0.01)->default(1)->required(),
                    Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                    Forms\Components\Textarea::make('question')->label('Pertanyaan')->required()->rows(3)->columnSpanFull(),
                    Forms\Components\KeyValue::make('options')
                        ->label('Pilihan Jawaban')->keyLabel('Kode')->valueLabel('Teks')
                        ->visible(fn (Forms\Get $get): bool => $get('type') === 'single_choice')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('correct_answer')->label('Jawaban Benar')->required(),
                    Forms\Components\Textarea::make('explanation')->label('Penjelasan')->rows(2)->columnSpanFull(),
                ])
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('module.program.code')->label('Program')->badge(),
            Tables\Columns\TextColumn::make('module.program.version')->label('Versi')->badge(),
            Tables\Columns\TextColumn::make('module.title')->label('Modul')->searchable(),
            Tables\Columns\TextColumn::make('passing_score')->label('Lulus ≥'),
            Tables\Columns\TextColumn::make('questions_count')->counts('questions')->label('Soal'),
            Tables\Columns\TextColumn::make('max_attempts')->label('Maks. Attempt')->placeholder('∞'),
            Tables\Columns\IconColumn::make('is_required')->label('Wajib')->boolean(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainingModuleAssessments::route('/'),
            'create' => Pages\CreateTrainingModuleAssessment::route('/create'),
            'edit' => Pages\EditTrainingModuleAssessment::route('/{record}/edit'),
        ];
    }
}
