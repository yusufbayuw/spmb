<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OnlineExamResource\Pages;
use App\Models\OnlineExam;
use App\Models\AdmissionTest;
use App\Models\OnlineExamCategory;
use App\Models\StudyProgram;
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

class OnlineExamResource extends Resource
{
    protected static ?string $model = OnlineExam::class;
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $navigationLabel = 'Paket Tes Online';
    protected static ?string $navigationGroup = 'Tes Online';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && $user->is_active && $user->hasOperationalUnitAccess()
            && ($user->isAdmin() || $user->isAdminUnit());
    }

    public static function canCreate(): bool { return static::canViewAny(); }

    public static function canView(Model $record): bool
    {
        return app(OnlineExamAccess::class)->canManage(auth()->user(), $record);
    }

    public static function canEdit(Model $record): bool { return static::canView($record); }

    public static function canDelete(Model $record): bool { return false; }

    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return app(OnlineExamAccess::class)
            ->staffScope(parent::getEloquentQuery(), auth()->user())
            ->whereHas('unit', fn (Builder $query) => $query->operational())
            ->withCount(['items', 'assignments', 'attempts']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('unit_id')
                ->label('Unit / Institusi')
                ->options(fn (): array => Unit::query()->operational()
                    ->when(auth()->user()?->isAdminUnit(), fn (Builder $q) =>
                        $q->whereKey(auth()->user()->unit_id))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->default(fn () => auth()->user()?->isAdminUnit() ? auth()->user()->unit_id : null)
                ->disabled(fn (): bool => auth()->user()?->isAdminUnit() ?? false)
                ->dehydrated()
                ->live()
                ->required(),
            Forms\Components\Select::make('study_program_id')
                ->label('Program Studi (Opsional)')
                ->helperText('Khusus unit perguruan tinggi; tidak mengubah penugasan peserta atau alur seleksi.')
                ->options(fn (Forms\Get $get): array => StudyProgram::query()
                    ->where('unit_id', $get('unit_id'))->where('is_active', true)
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->live(),
            Forms\Components\Select::make('admission_test_id')
                ->label('Tes Penerimaan yang Dihubungkan (Opsional)')
                ->helperText('Nilai CBT hanya masuk ke seleksi setelah petugas memilih pendaftaran dan mengesahkan hasil final secara eksplisit.')
                ->options(fn (Forms\Get $get): array => AdmissionTest::query()
                    ->where('unit_id', $get('unit_id'))
                    ->where('is_active', true)
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->searchable(),
            Forms\Components\TextInput::make('title')->label('Nama Paket Tes')->required()->maxLength(180),
            Forms\Components\Textarea::make('description')->label('Instruksi')->columnSpanFull(),
            Forms\Components\TextInput::make('duration_minutes')
                ->label('Durasi (menit)')->numeric()->integer()->minValue(1)->maxValue(360)->default(60)->required(),
            Forms\Components\TextInput::make('max_attempts')
                ->label('Maksimal Percobaan')->numeric()->integer()->minValue(1)->maxValue(20)->default(1)->required(),
            Forms\Components\TextInput::make('passing_percentage')
                ->label('Ambang Kelulusan (%)')->numeric()->minValue(0)->maxValue(100)->default(60)->required(),
            Forms\Components\DateTimePicker::make('opens_at')->label('Mulai Dibuka')->seconds(false),
            Forms\Components\DateTimePicker::make('closes_at')->label('Ditutup')->seconds(false)
                ->after('opens_at'),
            Forms\Components\Toggle::make('shuffle_questions')->label('Acak Urutan Soal')->default(true),
            Forms\Components\Toggle::make('shuffle_options')->label('Acak Pilihan')->default(true),
            Forms\Components\Repeater::make('blueprint')
                ->label('Blueprint Soal Acak (Opsional)')
                ->helperText('Kosong = gunakan Isi Paket Tes manual (kompatibel Fase 1). Jika diisi, paket mengambil sejumlah soal aktif per kategori secara acak dan tidak boleh memiliki Isi Paket Tes manual.')
                ->schema([
                    Forms\Components\Select::make('category_id')
                        ->label('Kategori')
                        ->options(fn (Forms\Get $get): array => OnlineExamCategory::query()
                            ->where('unit_id', $get('../../unit_id'))->where('is_active', true)
                            ->orderBy('name')->pluck('name', 'id')->all())
                        ->required()->searchable(),
                    Forms\Components\Select::make('difficulty')
                        ->label('Kesulitan')
                        ->options(['any' => 'Semua Tingkat', ...OnlineExamBlueprint::DIFFICULTIES])
                        ->default('any')->required(),
                    Forms\Components\TextInput::make('count')
                        ->label('Jumlah Soal')->integer()->minValue(1)->maxValue(200)->required(),
                ])->columns(['default' => 1, 'md' => 3])->defaultItems(0)->reorderable(false)->columnSpanFull(),
            Forms\Components\Toggle::make('is_published')
                ->label('Terbitkan Paket Tes')
                ->helperText('Buat paket dahulu, tambahkan soal pada menu Isi Paket Tes, lalu terbitkan. Tidak mengubah alur SPMB.')
                ->default(false),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            Tables\Columns\TextColumn::make('unit.name')->label('Unit'),
            Tables\Columns\TextColumn::make('studyProgram.name')->label('Prodi')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('title')->label('Paket Tes')->searchable(),
            Tables\Columns\TextColumn::make('admissionTest.name')->label('Tes Seleksi Tertaut')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('items_count')->label('Soal')->badge(),
            Tables\Columns\TextColumn::make('assignments_count')->label('Peserta')->badge(),
            Tables\Columns\TextColumn::make('attempts_count')->label('Pengerjaan')->badge(),
            Tables\Columns\IconColumn::make('is_published')->label('Terbit')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnlineExams::route('/'),
            'create' => Pages\CreateOnlineExam::route('/create'),
            'edit' => Pages\EditOnlineExam::route('/{record}/edit'),
        ];
    }
}
