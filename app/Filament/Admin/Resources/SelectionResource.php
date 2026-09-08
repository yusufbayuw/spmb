<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SelectionResource\Pages;
use App\Models\Selection;
use App\Models\SelectionBatch;
use App\Services\AdmissionDecisionService;
use App\Services\RegistrationWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SelectionResource extends Resource
{
    protected static ?string $model = Selection::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Penetapan Hasil';

    protected static ?string $modelLabel = 'Hasil Seleksi';

    protected static ?string $pluralModelLabel = 'Penetapan Hasil';

    protected static ?string $navigationGroup = 'Pendaftaran';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('registration_id')
                ->relationship('registration', 'registration_number')
                ->label('Pendaftaran')
                ->searchable()
                ->preload()
                ->disabled(),
            Forms\Components\Select::make('decision')
                ->label('Keputusan')
                ->options([
                    'pending' => 'Belum Diputuskan',
                    'accepted' => 'Diterima',
                    'rejected' => 'Ditolak',
                    'waiting_list' => 'Daftar Tunggu',
                ])
                ->disabled(),
            Forms\Components\TextInput::make('final_score')
                ->label('Nilai Akhir')
                ->numeric()
                ->disabled(),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('registration.registration_number')
                    ->label('No. Registrasi'),
                Tables\Columns\TextColumn::make('registration.full_name')
                    ->label('Calon Siswa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('registration.unit.name')
                    ->label('Unit')
                    ->badge(),
                Tables\Columns\TextColumn::make('batch.name')
                    ->label('Batch')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('final_score')
                    ->label('Nilai Akhir'),
                Tables\Columns\TextColumn::make('rank')
                    ->label('Peringkat')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('system_recommendation')
                    ->label('Rekomendasi Sistem')
                    ->badge()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('waitlist_rank')
                    ->label('Urutan Tunggu')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('decision')
                    ->label('Keputusan')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'accepted' => 'Diterima',
                        'rejected' => 'Ditolak',
                        'waiting_list' => 'Daftar Tunggu',
                        default => 'Belum Diputuskan',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'waiting_list' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('registration.announcement.status')
                    ->label('Publikasi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'published' => 'Sudah Dipublikasikan',
                        'draft' => 'Belum Dipublikasikan',
                        default => 'Belum Tersedia',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('finalizeBatch')
                    ->label('Finalkan Batch')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('finalize_selectionbatch')
                        && static::rankedBatchOptions() !== [])
                    ->form([
                        Forms\Components\Select::make('selection_batch_id')
                            ->label('Batch Seleksi')
                            ->options(fn (): array => static::rankedBatchOptions())
                            ->searchable()
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Finalkan hasil batch seleksi?')
                    ->modalDescription('Rekomendasi sistem akan digunakan untuk kandidat yang tidak dioverride. Setelah final, seluruh kandidat batch berpindah ke Publikasi Hasil dan ranking tidak dapat diubah.')
                    ->action(function (array $data): void {
                        $batch = SelectionBatch::query()
                            ->whereKey((int) $data['selection_batch_id'])
                            ->where('status', 'ranked')
                            ->firstOrFail();

                        app(AdmissionDecisionService::class)->finalize($batch, auth()->user());

                        Notification::make()
                            ->title('Hasil batch difinalkan dan siap dipublikasikan')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('decideDirectly')
                    ->label('Tetapkan Hasil')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Selection $record): bool => (bool) auth()->user()?->can('decide_selection')
                        && $record->registration?->current_stage === 'selection'
                        && ! $record->selection_batch_id
                        && (bool) $record->registration?->allowsManualSelection()
                    )
                    ->form([
                        Forms\Components\Select::make('decision')
                            ->label('Keputusan')
                            ->options([
                                'accepted' => 'Diterima',
                                'rejected' => 'Ditolak',
                                'waiting_list' => 'Daftar Tunggu',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('final_score')
                            ->label('Nilai Akhir')
                            ->numeric()
                            ->default(fn (Selection $record) => $record->final_score),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan'),
                    ])
                    ->action(fn (Selection $record, array $data) => app(RegistrationWorkflowService::class)->decide(
                        $record->registration,
                        auth()->user(),
                        $data['decision'],
                        $data['final_score'] ?? null,
                        $data['notes'] ?? null,
                    )),
                Tables\Actions\Action::make('reviewDecision')
                    ->label('Review Keputusan')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Selection $record): bool => (bool) auth()->user()?->can('decide_selection')
                        && $record->registration?->current_stage === 'selection'
                        && $record->batch?->status === 'ranked'
                    )
                    ->fillForm(fn (Selection $record): array => [
                        'decision' => $record->decision !== 'pending'
                            ? $record->decision
                            : $record->system_recommendation,
                        'final_score' => $record->final_score,
                        'notes' => $record->notes,
                    ])
                    ->form([
                        Forms\Components\Select::make('decision')
                            ->label('Keputusan')
                            ->options([
                                'accepted' => 'Diterima',
                                'rejected' => 'Ditolak',
                                'waiting_list' => 'Daftar Tunggu',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('final_score')
                            ->label('Nilai Akhir')
                            ->numeric(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan / Alasan Override')
                            ->helperText('Wajib diisi jika keputusan berbeda dari rekomendasi sistem.'),
                    ])
                    ->action(fn (Selection $record, array $data) => app(RegistrationWorkflowService::class)->reviewDecision(
                        $record->registration,
                        auth()->user(),
                        $data['decision'],
                        $data['final_score'] ?? null,
                        $data['notes'] ?? null,
                    )),
                Tables\Actions\Action::make('correctDraftDecision')
                    ->label('Koreksi Keputusan')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Selection $record): bool => (bool) auth()->user()?->can('decide_selection')
                        && $record->registration?->current_stage === 'announcement'
                        && $record->registration?->announcement?->status === 'draft'
                    )
                    ->fillForm(fn (Selection $record): array => [
                        'decision' => $record->decision,
                        'final_score' => $record->final_score,
                        'reason' => null,
                    ])
                    ->form([
                        Forms\Components\Select::make('decision')
                            ->label('Keputusan Baru')
                            ->options([
                                'accepted' => 'Diterima',
                                'rejected' => 'Ditolak',
                                'waiting_list' => 'Daftar Tunggu',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('final_score')
                            ->label('Nilai Akhir')
                            ->numeric(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan Koreksi')
                            ->helperText('Wajib diisi dan akan dicatat pada Audit Log.')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Koreksi keputusan sebelum publikasi?')
                    ->modalDescription('Keputusan dapat dikoreksi selama pengumuman masih draft. Ranking dan rekomendasi sistem tidak diubah.')
                    ->action(function (Selection $record, array $data): void {
                        app(RegistrationWorkflowService::class)->correctDraftDecision(
                            $record,
                            auth()->user(),
                            $data['decision'],
                            isset($data['final_score']) ? (float) $data['final_score'] : null,
                            $data['reason'],
                        );

                        Notification::make()
                            ->title('Keputusan berhasil dikoreksi')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulkDecision')
                    ->label('Tetapkan / Koreksi Hasil')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('decide_selection'))
                    ->form([
                        Forms\Components\Select::make('decision')
                            ->label('Keputusan untuk peserta terpilih')
                            ->options([
                                'accepted' => 'Diterima',
                                'rejected' => 'Ditolak',
                                'waiting_list' => 'Daftar Tunggu',
                                'system' => 'Ikuti Rekomendasi Sistem',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Catatan / Alasan')
                            ->helperText('Satu alasan akan dicatat untuk seluruh peserta terpilih.')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Terapkan keputusan ke peserta terpilih?')
                    ->modalDescription('Operasi ini bersifat atomik: jika satu peserta tidak dapat diproses, seluruh perubahan dibatalkan.')
                    ->action(function (Collection $records, array $data): void {
                        $count = app(RegistrationWorkflowService::class)->bulkSetDecisions(
                            $records,
                            auth()->user(),
                            $data['decision'],
                            $data['reason'],
                        );

                        Notification::make()
                            ->title($count.' hasil seleksi berhasil diperbarui')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function rankedBatchOptions(): array
    {
        return SelectionBatch::query()
            ->with(['opening.unit', 'opening.studyProgram'])
            ->where('status', 'ranked')
            ->when(auth()->user()?->isTU(), fn (Builder $query) => $query
                ->whereHas('opening', fn (Builder $opening) => $opening->where('unit_id', auth()->user()->unit_id)))
            ->orderByDesc('ranked_at')
            ->get()
            ->mapWithKeys(fn (SelectionBatch $batch): array => [
                $batch->id => $batch->name.' · '.($batch->opening?->label() ?? 'Pembukaan'),
            ])
            ->all();
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->whereHas('registration', fn (Builder $query) => $query
                ->where('lifecycle_status', 'active')
                ->where('current_stage', 'selection'))
            ->where(function (Builder $query): void {
                $query->whereHas('batch', fn (Builder $batch) => $batch->where('status', 'ranked'))
                    ->orWhere(function (Builder $manual): void {
                        $manual->whereNull('selection_batch_id')
                            ->whereHas('registration', fn (Builder $registration) => $registration
                                ->where(function (Builder $mode): void {
                                    $mode->whereDoesntHave('configuration')
                                        ->orWhereHas('configuration', fn (Builder $configuration) => $configuration
                                            ->whereIn('selection_mode', ['manual', 'flexible']));
                                }));
                    });
            })
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSelections::route('/'),
            'create' => Pages\CreateSelection::route('/create'),
            'edit' => Pages\EditSelection::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'registration.unit',
            'registration.configuration',
            'registration.announcement',
            'batch',
        ]);

        if (auth()->user()?->isTU()) {
            $query->whereHas(
                'registration',
                fn (Builder $registrationQuery) => $registrationQuery->where('unit_id', auth()->user()->unit_id),
            );
        }

        return $query;
    }
}
