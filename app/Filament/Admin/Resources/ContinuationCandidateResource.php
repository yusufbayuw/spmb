<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ContinuationCandidateResource\Pages;
use App\Models\ContinuationCandidate;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContinuationCandidateResource extends Resource
{
    protected static ?string $model = ContinuationCandidate::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Terusan';

    protected static ?string $modelLabel = 'Data Terusan';

    protected static ?string $pluralModelLabel = 'Terusan';

    protected static ?string $navigationGroup = 'Pendaftaran';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nama')
                    ->searchable()
                    ->weight('medium')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('birth_date')
                    ->label('Tanggal Lahir')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('source_school_name')
                    ->label('Sekolah Asal')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('academic_year')
                    ->label('Tahun Ajaran')
                    ->sortable(),
                Tables\Columns\TextColumn::make('links_count')
                    ->counts('links')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? 'Sudah Mendaftar' : 'Belum Mendaftar')
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('imported_at')
                    ->label('Diimpor')
                    ->dateTime('d/m/Y H:i', timezone: config('app.timezone'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('academic_year')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => static::scopedQuery()
                        ->select('academic_year')
                        ->distinct()
                        ->orderByDesc('academic_year')
                        ->pluck('academic_year', 'academic_year')
                        ->all()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Data')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (ContinuationCandidate $record): bool => static::canEdit($record))
                    ->fillForm(fn (ContinuationCandidate $record): array => static::editFormState($record))
                    ->form(static::editFormSchema())
                    ->modalHeading('Edit Data Terusan')
                    ->modalDescription('Perubahan pada data autofill akan langsung digunakan untuk pencocokan dan pengisian pendaftaran berikutnya.')
                    ->modalSubmitActionLabel('Simpan Perubahan')
                    ->action(function (ContinuationCandidate $record, array $data): void {
                        abort_unless(static::canEdit($record), 403);

                        static::updateCandidateFromEditForm($record, $data);

                        Notification::make()
                            ->title('Data Terusan berhasil diperbarui')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (ContinuationCandidate $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (ContinuationCandidate $record): string => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn (ContinuationCandidate $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (ContinuationCandidate $record): bool => static::canEdit($record))
                    ->action(fn (ContinuationCandidate $record) => $record->update(['is_active' => ! $record->is_active])),
            ]);
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private static function editFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Identitas Peserta')
                ->schema([
                    Forms\Components\TextInput::make('nik')
                        ->label('NIK')
                        ->required()
                        ->length(16)
                        ->rule('regex:/^\d{16}$/')
                        ->helperText('16 digit. NIK dan tanggal lahir dipakai untuk mencocokkan data Terusan.'),
                    Forms\Components\DatePicker::make('birth_date')
                        ->label('Tanggal Lahir')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    Forms\Components\TextInput::make('full_name')
                        ->label('Nama Lengkap')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Select::make('gender')
                        ->label('Jenis Kelamin')
                        ->options([
                            'L' => 'Laki-laki',
                            'P' => 'Perempuan',
                        ]),
                    Forms\Components\TextInput::make('birth_place')
                        ->label('Tempat Lahir')
                        ->maxLength(150),
                    Forms\Components\Select::make('religion')
                        ->label('Agama')
                        ->options([
                            'Islam' => 'Islam',
                            'Kristen' => 'Kristen',
                            'Katolik' => 'Katolik',
                            'Hindu' => 'Hindu',
                            'Buddha' => 'Buddha',
                            'Konghucu' => 'Konghucu',
                        ]),
                    Forms\Components\TextInput::make('nisn')
                        ->label('NISN')
                        ->maxLength(20),
                    Forms\Components\TextInput::make('nipd')
                        ->label('NIPD')
                        ->maxLength(50),
                ])
                ->columns(['default' => 1, 'md' => 2]),

            Forms\Components\Section::make('Asal & Kontak')
                ->schema([
                    Forms\Components\TextInput::make('source_school_name')
                        ->label('Sekolah Asal')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('academic_year')
                        ->label('Tahun Ajaran')
                        ->required()
                        ->maxLength(20)
                        ->helperText('Contoh: 2027/2028'),
                    Forms\Components\Textarea::make('home_address')
                        ->label('Alamat Rumah')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('rt')
                        ->label('RT')
                        ->maxLength(5),
                    Forms\Components\TextInput::make('rw')
                        ->label('RW')
                        ->maxLength(5),
                    Forms\Components\TextInput::make('phone')
                        ->label('No. HP / Telepon')
                        ->maxLength(30),
                    Forms\Components\TextInput::make('email')
                        ->label('E-Mail')
                        ->email()
                        ->maxLength(150),
                ])
                ->columns(['default' => 1, 'md' => 2]),

            Forms\Components\Section::make('Data Orang Tua')
                ->schema([
                    Forms\Components\TextInput::make('father_name')
                        ->label('Nama Ayah')
                        ->maxLength(150),
                    Forms\Components\TextInput::make('father_nik')
                        ->label('NIK Ayah')
                        ->length(16)
                        ->rule('regex:/^\d{16}$/'),
                    Forms\Components\Select::make('father_education')
                        ->label('Pendidikan Ayah')
                        ->options(static::educationOptions()),
                    Forms\Components\TextInput::make('father_occupation')
                        ->label('Pekerjaan Ayah')
                        ->maxLength(150),
                    Forms\Components\TextInput::make('mother_name')
                        ->label('Nama Ibu')
                        ->maxLength(150),
                    Forms\Components\TextInput::make('mother_nik')
                        ->label('NIK Ibu')
                        ->length(16)
                        ->rule('regex:/^\d{16}$/'),
                    Forms\Components\Select::make('mother_education')
                        ->label('Pendidikan Ibu')
                        ->options(static::educationOptions()),
                    Forms\Components\TextInput::make('mother_occupation')
                        ->label('Pekerjaan Ibu')
                        ->maxLength(150),
                ])
                ->columns(['default' => 1, 'md' => 2]),

            Forms\Components\Toggle::make('is_active')
                ->label('Data aktif untuk autofill')
                ->helperText('Nonaktifkan jika data ini tidak boleh lagi digunakan untuk mencocokkan pendaftar.'),
        ];
    }

    /** @return array<string, string> */
    private static function educationOptions(): array
    {
        return [
            'SD' => 'SD',
            'SMP' => 'SMP',
            'SMA' => 'SMA/SMK',
            'D1' => 'D1',
            'D2' => 'D2',
            'D3' => 'D3',
            'D4' => 'D4',
            'S1' => 'S1',
            'S2' => 'S2',
            'S3' => 'S3',
            'Lainnya' => 'Lainnya',
        ];
    }

    /** @return array<string, mixed> */
    private static function editFormState(ContinuationCandidate $record): array
    {
        $prefill = is_array($record->prefill_data) ? $record->prefill_data : [];

        return [
            'nik' => $record->nik,
            'birth_date' => $record->birth_date,
            'full_name' => $record->full_name ?? ($prefill['full_name'] ?? null),
            'gender' => $prefill['gender'] ?? null,
            'birth_place' => $prefill['birth_place'] ?? null,
            'religion' => $prefill['religion'] ?? null,
            'nisn' => $record->nisn,
            'nipd' => $record->nipd,
            'source_school_name' => $record->source_school_name,
            'academic_year' => $record->academic_year,
            'home_address' => $prefill['home_address'] ?? null,
            'rt' => $prefill['rt'] ?? null,
            'rw' => $prefill['rw'] ?? null,
            'phone' => $prefill['phone'] ?? null,
            'email' => $prefill['email'] ?? null,
            'father_name' => $prefill['parentInfo.father_name'] ?? null,
            'father_nik' => $prefill['parentInfo.father_nik'] ?? null,
            'father_education' => $prefill['parentInfo.father_education'] ?? null,
            'father_occupation' => $prefill['parentInfo.father_occupation'] ?? null,
            'mother_name' => $prefill['parentInfo.mother_name'] ?? null,
            'mother_nik' => $prefill['parentInfo.mother_nik'] ?? null,
            'mother_education' => $prefill['parentInfo.mother_education'] ?? null,
            'mother_occupation' => $prefill['parentInfo.mother_occupation'] ?? null,
            'is_active' => (bool) $record->is_active,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function updateCandidateFromEditForm(ContinuationCandidate $record, array $data): void
    {
        $prefill = is_array($record->prefill_data) ? $record->prefill_data : [];

        $prefillMap = [
            'full_name' => 'full_name',
            'gender' => 'gender',
            'birth_place' => 'birth_place',
            'religion' => 'religion',
            'source_school_name' => 'previous_school',
            'home_address' => 'home_address',
            'rt' => 'rt',
            'rw' => 'rw',
            'phone' => 'phone',
            'email' => 'email',
            'father_name' => 'parentInfo.father_name',
            'father_nik' => 'parentInfo.father_nik',
            'father_education' => 'parentInfo.father_education',
            'father_occupation' => 'parentInfo.father_occupation',
            'mother_name' => 'parentInfo.mother_name',
            'mother_nik' => 'parentInfo.mother_nik',
            'mother_education' => 'parentInfo.mother_education',
            'mother_occupation' => 'parentInfo.mother_occupation',
        ];

        foreach ($prefillMap as $formKey => $prefillKey) {
            $value = $data[$formKey] ?? null;

            if (blank($value)) {
                unset($prefill[$prefillKey]);

                continue;
            }

            $prefill[$prefillKey] = is_string($value) ? trim($value) : $value;
        }

        $record->update([
            'nik' => preg_replace('/\D+/', '', (string) $data['nik']),
            'birth_date' => $data['birth_date'],
            'full_name' => trim((string) $data['full_name']),
            'nisn' => filled($data['nisn'] ?? null) ? preg_replace('/\D+/', '', (string) $data['nisn']) : null,
            'nipd' => filled($data['nipd'] ?? null) ? trim((string) $data['nipd']) : null,
            'source_school_name' => trim((string) $data['source_school_name']),
            'academic_year' => static::normalizeAcademicYear((string) $data['academic_year']),
            'prefill_data' => $prefill,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
    }

    private static function normalizeAcademicYear(string $academicYear): string
    {
        $academicYear = str_replace(['–', '—', '-'], '/', trim($academicYear));

        return preg_replace('/\s+/u', '', $academicYear) ?? $academicYear;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopedQuery()->withCount('links');
    }

    public static function scopedQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (($user?->isAdminUnit() || $user?->isTU()) && $user->unit_id) {
            $query->where('unit_id', $user->unit_id);
        }

        return $query;
    }

    public static function canEdit(Model $record): bool
    {
        if (! $record instanceof ContinuationCandidate || ! parent::canEdit($record)) {
            return false;
        }

        $user = auth()->user();

        if (($user?->isAdminUnit() || $user?->isTU()) && $user->unit_id) {
            return (int) $record->unit_id === (int) $user->unit_id;
        }

        return true;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContinuationCandidates::route('/'),
        ];
    }
}
