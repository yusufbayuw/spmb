<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OnlineExamQuestionResource\Pages;
use App\Models\OnlineExamQuestion;
use App\Models\OnlineExamCategory;
use App\Services\OnlineExamBlueprint;
use App\Models\Unit;
use App\Services\OnlineExamAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OnlineExamQuestionResource extends Resource
{
    protected static ?string $model = OnlineExamQuestion::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Bank Soal Online';
    protected static ?string $navigationGroup = 'Tes Online';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool { return OnlineExamResource::canViewAny(); }
    public static function canCreate(): bool { return static::canViewAny(); }

    public static function canView(Model $record): bool
    {
        return app(OnlineExamAccess::class)->canManageUnit(auth()->user(), (int) $record->unit_id);
    }

    public static function canEdit(Model $record): bool { return static::canView($record); }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return app(OnlineExamAccess::class)
            ->staffScope(parent::getEloquentQuery(), auth()->user())
            ->whereHas('unit', fn (Builder $q) => $q->operational());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('unit_id')
                ->label('Unit')
                ->options(fn (): array => Unit::query()->operational()
                    ->when(auth()->user()?->isAdminUnit(), fn (Builder $q) => $q->whereKey(auth()->user()->unit_id))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->default(fn () => auth()->user()?->isAdminUnit() ? auth()->user()->unit_id : null)
                ->disabled(fn (): bool => auth()->user()?->isAdminUnit() ?? false)
                ->dehydrated()->live()->required(),
            Forms\Components\Select::make('online_exam_category_id')
                ->label('Kategori (Opsional untuk paket manual)')
                ->options(fn (Forms\Get $get): array => OnlineExamCategory::query()
                    ->where('unit_id', $get('unit_id'))->where('is_active', true)
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->searchable(),
            Forms\Components\Select::make('difficulty')
                ->label('Tingkat Kesulitan')
                ->options(OnlineExamBlueprint::DIFFICULTIES)->default('medium')->required(),
            Forms\Components\TextInput::make('competency_tag')
                ->label('Tag Kompetensi')->maxLength(150),
            Forms\Components\Select::make('type')->label('Jenis Soal')
                ->options(OnlineExamQuestion::TYPES)->live()->required(),
            Forms\Components\Textarea::make('prompt')
                ->label('Pertanyaan')->rows(4)->required()->columnSpanFull(),
            Forms\Components\TextInput::make('points')->label('Bobot')->numeric()->minValue(0.01)->maxValue(10000)->default(1)->required(),
            Forms\Components\Toggle::make('is_active')->label('Soal Aktif')->default(true),
            Forms\Components\KeyValue::make('options.choices')
                ->label('Pilihan / Daftar Pasangan Jawaban')
                // Refresh the answer-key choices after option codes are edited.
                ->live(onBlur: true)
                ->helperText('Kode unik A, B, C; isian memakai kode untuk kunci jawaban. Tidak perlu untuk Benar/Salah.')
                ->keyLabel('Kode')->valueLabel('Isi Pilihan')
                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['single_choice', 'multiple_choice', 'matching'], true))
                ->columnSpanFull(),
            Forms\Components\KeyValue::make('options.prompts')
                ->label('Pernyataan yang Dijodohkan')
                ->keyLabel('Kode Pernyataan')->valueLabel('Teks Pernyataan')
                ->visible(fn (Forms\Get $get) => $get('type') === 'matching')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('grading_config.correct')
                ->label('Kode Jawaban Benar')
                ->helperText('Gunakan kode pilihan. Untuk Benar/Salah: 1 = Benar, 0 = Salah.')
                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['single_choice', 'true_false'], true)),
            Forms\Components\CheckboxList::make('grading_config.correct_options')
                ->label('Pilihan Benar (Bisa Lebih dari Satu)')
                ->options(fn (Forms\Get $get): array => is_array($get('options.choices'))
                    ? $get('options.choices') : [])
                ->visible(fn (Forms\Get $get) => $get('type') === 'multiple_choice')
                ->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
            Forms\Components\KeyValue::make('grading_config.pairs')
                ->label('Kunci Pasangan: Kode Pernyataan → Kode Jawaban')
                ->keyLabel('Kode Pernyataan')->valueLabel('Kode Pilihan')
                ->visible(fn (Forms\Get $get) => $get('type') === 'matching')
                ->columnSpanFull(),
            Forms\Components\TagsInput::make('grading_config.accepted')
                ->label('Jawaban Singkat yang Diterima')
                ->visible(fn (Forms\Get $get) => $get('type') === 'short_answer')
                ->columnSpanFull(),
            Forms\Components\Toggle::make('grading_config.case_sensitive')
                ->label('Peka Huruf Besar/Kecil')
                ->visible(fn (Forms\Get $get) => $get('type') === 'short_answer'),
            Forms\Components\TextInput::make('grading_config.value')
                ->label('Jawaban Numerik Benar')
                ->numeric()
                ->visible(fn (Forms\Get $get) => $get('type') === 'numerical'),
            Forms\Components\TextInput::make('grading_config.tolerance')
                ->label('Toleransi Absolut')
                ->numeric()->minValue(0)->default(0)
                ->visible(fn (Forms\Get $get) => $get('type') === 'numerical'),
            Forms\Components\Placeholder::make('essay_help')
                ->label('Penilaian Esai')
                ->content('Esai dinilai petugas setelah peserta mengumpulkan ujian. Kunci jawaban tidak diperlukan.')
                ->visible(fn (Forms\Get $get) => $get('type') === 'essay'),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            Tables\Columns\TextColumn::make('unit.name')->label('Unit'),
            Tables\Columns\TextColumn::make('category.name')->label('Kategori')->toggleable(),
            Tables\Columns\TextColumn::make('difficulty')->label('Kesulitan')->badge(),
            Tables\Columns\TextColumn::make('type')->label('Jenis')->formatStateUsing(
                fn (string $state): string => OnlineExamQuestion::TYPES[$state] ?? $state
            )->badge(),
            Tables\Columns\TextColumn::make('prompt')->label('Soal')->limit(95)->searchable(),
            Tables\Columns\TextColumn::make('points')->label('Bobot'),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnlineExamQuestions::route('/'),
            'create' => Pages\CreateOnlineExamQuestion::route('/create'),
            'edit' => Pages\EditOnlineExamQuestion::route('/{record}/edit'),
        ];
    }
}
