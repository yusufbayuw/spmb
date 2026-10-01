<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CertificationProgramResource\Pages;
use App\Models\CertificationQuestion;
use App\Models\CertificationProgram;
use App\Models\TrainingProgram;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CertificationProgramResource extends Resource
{
    protected static ?string $model = CertificationProgram::class;
    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static ?string $navigationLabel = 'Kelola Sertifikasi';
    protected static ?string $modelLabel = 'Program Sertifikasi';
    protected static ?string $pluralModelLabel = 'Program Sertifikasi';
    protected static ?string $navigationGroup = 'Training & Sertifikasi';
    protected static ?int $navigationSort = 21;

    public static function canViewAny(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Program Sertifikasi')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('code')->label('Kode Sertifikasi')->required()->maxLength(50)->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')->label('Nama Sertifikasi')->required()->maxLength(180),
                    Forms\Components\Select::make('target_role')->label('Role Sasaran')->options(TrainingProgram::ROLE_LABELS)->required(),
                    Forms\Components\TextInput::make('version')->label('Versi')->required()->default('1.0'),
                    Forms\Components\Select::make('training_program_id')
                        ->label('Training Prasyarat')
                        ->options(fn (): array => TrainingProgram::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()->preload(),
                    Forms\Components\TextInput::make('passing_score')->label('Nilai Minimum')->integer()->minValue(1)->maxValue(100)->default(80)->required(),
                    Forms\Components\TextInput::make('valid_months')->label('Masa Berlaku (bulan)')->integer()->minValue(0)->default(24)->required(),
                    Forms\Components\TextInput::make('sort_order')->label('Urutan')->integer()->default(0),
                    Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                    Forms\Components\Textarea::make('description')->label('Deskripsi')->rows(3)->columnSpanFull(),
                ]),
            Forms\Components\Repeater::make('questions')
                ->label('Bank Soal')
                ->relationship('questions')
                ->orderColumn('sort_order')
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => filled($state['question'] ?? null) ? str($state['question'])->limit(80)->toString() : null)
                ->schema([
                    Forms\Components\Select::make('type')->label('Jenis')->options(CertificationQuestion::TYPES)->default('single_choice')->live()->required(),
                    Forms\Components\TextInput::make('weight')->label('Bobot')->numeric()->minValue(0.01)->default(1)->required(),
                    Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                    Forms\Components\Textarea::make('question')->label('Pertanyaan')->required()->rows(3)->columnSpanFull(),
                    Forms\Components\KeyValue::make('options')
                        ->label('Pilihan Jawaban')->keyLabel('Kode')->valueLabel('Teks Pilihan')
                        ->helperText('Contoh kode: A, B, C, D. Tidak perlu diisi untuk soal Benar/Salah.')
                        ->visible(fn (Forms\Get $get): bool => $get('type') === 'single_choice')->columnSpanFull(),
                    Forms\Components\TextInput::make('correct_answer')->label('Kode Jawaban Benar')->helperText('Untuk Benar/Salah gunakan 1 = Benar, 0 = Salah.')->required(),
                    Forms\Components\Textarea::make('explanation')->label('Penjelasan Jawaban')->rows(2)->columnSpanFull(),
                ])
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->badge()->searchable(),
            Tables\Columns\TextColumn::make('name')->label('Sertifikasi')->searchable(),
            Tables\Columns\TextColumn::make('target_role')->label('Role')->badge()->formatStateUsing(fn (string $state): string => TrainingProgram::ROLE_LABELS[$state] ?? $state),
            Tables\Columns\TextColumn::make('passing_score')->label('Lulus ≥'),
            Tables\Columns\TextColumn::make('questions_count')->counts('questions')->label('Soal'),
            Tables\Columns\TextColumn::make('valid_months')->label('Berlaku')->suffix(' bln'),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCertificationPrograms::route('/'),
            'create' => Pages\CreateCertificationProgram::route('/create'),
            'edit' => Pages\EditCertificationProgram::route('/{record}/edit'),
        ];
    }
}
