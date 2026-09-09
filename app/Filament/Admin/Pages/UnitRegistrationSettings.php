<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Applicant\Resources\RegistrationResource;
use App\Models\AdmissionTest;
use App\Models\Registration;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Services\ConfiguredRegistrationForm;
use App\Services\UnitConfigurationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class UnitRegistrationSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Pengaturan Pendaftaran Unit';

    protected static ?string $title = 'Pengaturan Pendaftaran Unit';

    protected static ?string $navigationGroup = 'Konfigurasi SPMB';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.admin.pages.unit-registration-settings';

    public ?string $unitUuid = null;

    #[Locked]
    public ?string $configurationUuid = null;

    public array $data = [];

    public array $previewData = [];

    public bool $preview = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active && (auth()->user()->isAdmin() || auth()->user()->isTU());
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->unitUuid = auth()->user()->isTU() ? auth()->user()->unit?->uuid : Unit::query()->value('uuid');
        if ($this->unitUuid) {
            $this->loadUnit();
        }
    }

    public function units(): array
    {
        return Unit::query()->when(auth()->user()->isTU(), fn ($q) => $q->whereKey(auth()->user()->unit_id))->pluck('name', 'uuid')->all();
    }

    public function loadUnit(): void
    {
        $unit = Unit::query()->where('uuid', $this->unitUuid)->firstOrFail();
        $draft = app(UnitConfigurationService::class)->draft($unit, auth()->user());
        $this->configurationUuid = $draft->uuid;
        $this->preview = false;
        $data = $draft->toArray();
        $data['test_definitions'] = collect($data['test_definitions'] ?? [])
            ->map(fn (array $definition): array => ['uuid' => AdmissionTest::query()->whereKey($definition['id'] ?? null)->value('uuid')])
            ->filter(fn (array $definition): bool => filled($definition['uuid']))
            ->values()
            ->all();

        if (($data['tests_enabled'] ?? false) && $data['test_definitions'] === []) {
            $data['test_definitions'] = $this->activeTestDefinitions();
        }

        $data['academic_score_settings'] = array_replace([
            'required' => false,
            'min_score' => 0,
            'max_score' => 100,
            'pathway_uuids' => [],
            'grades' => [],
            'subjects' => [],
            'assessments' => [],
        ], is_array($data['academic_score_settings'] ?? null) ? $data['academic_score_settings'] : []);
        $data['achievement_settings'] = array_replace([
            'required' => false,
            'max_entries' => 3,
            'pathway_uuids' => [],
            'levels' => ['Sekolah', 'Kecamatan', 'Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'],
        ], is_array($data['achievement_settings'] ?? null) ? $data['achievement_settings'] : []);

        $this->form->fill($data);
    }

    protected function getForms(): array
    {
        return ['form', 'previewForm'];
    }

    public function previewForm(Form $form): Form
    {
        return RegistrationResource::form($form)
            ->model(Registration::class)->statePath('previewData')->disabled();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Tahapan Pendaftaran')->description('Validasi identitas, kartu pendaftar, seleksi, dan publikasi hasil tetap tersedia. Tahap setelah pengumuman dapat diaktifkan saat diperlukan.')->schema([
                Forms\Components\Toggle::make('payment_enabled')->label('Pembayaran'),
                Forms\Components\Toggle::make('documents_enabled')->label('Dokumen'),
                Forms\Components\Toggle::make('tests_enabled')
                    ->label('Tes')
                    ->helperText('Jika aktif, minimal satu tes wajib harus dipilih pada bagian Tes pada Versi Ini.')
                    ->live()
                    ->afterStateUpdated(function (bool $state, Forms\Get $get, Forms\Set $set): void {
                        if ($state && empty($get('test_definitions'))) {
                            $set('test_definitions', $this->activeTestDefinitions());
                        }
                    }),
                Forms\Components\Select::make('selection_mode')
                    ->label('Metode Penetapan Hasil')
                    ->options([
                        'flexible' => 'Fleksibel — batch opsional',
                        'manual' => 'Manual — tanpa batch',
                        'batch' => 'Batch — wajib ranking',
                    ])
                    ->default('flexible')
                    ->helperText('Fleksibel memungkinkan TU menetapkan hasil langsung atau menggunakan Batch Seleksi untuk ranking.')
                    ->required(),
                Forms\Components\Toggle::make('post_announcement_enabled')
                    ->label('Proses Pasca-Pengumuman')
                    ->helperText('Aktifkan Penawaran Penerimaan, Daftar Tunggu, Daftar Ulang, dan Enrollment. Jika nonaktif, setelah pengumuman proses langsung selesai.'),
            ])->columns(5),
            Forms\Components\Section::make('Formulir Unit')->description('Pilih isian bawaan yang ingin disesuaikan atau tambahkan pertanyaan khusus. Identitas inti tetap wajib.')->schema([
                Forms\Components\Repeater::make('fields')->label('Pengaturan field')->default([])->schema([
                    Forms\Components\Select::make('key')
                        ->label('Isian')
                        ->options(fn (Forms\Get $get): array => ConfiguredRegistrationForm::fieldLabels() + [(! in_array($get('key'), ConfiguredRegistrationForm::BUILTIN_FIELDS, true) && $get('key') ? $get('key') : 'custom_'.strtolower(Str::random(8))) => 'Pertanyaan tambahan'])
                        ->default(fn (): string => 'custom_'.strtolower(Str::random(8)))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                            if (! in_array($state, ConfiguredRegistrationForm::REGION_FIELDS, true)) {
                                return;
                            }

                            $set('label', ConfiguredRegistrationForm::fieldLabels()[$state] ?? $state);
                            $set('type', 'select');
                            $set('options', []);
                            $set('required', false);
                        })
                        ->required(),
                    Forms\Components\TextInput::make('label')->label('Label')->required(),
                    Forms\Components\Select::make('type')
                        ->label('Jenis')
                        ->options(['text' => 'Teks', 'textarea' => 'Teks panjang', 'number' => 'Angka', 'date' => 'Tanggal', 'select' => 'Pilihan tunggal', 'multiselect' => 'Pilihan jamak', 'boolean' => 'Ya/Tidak'])
                        ->default('text')
                        ->disabled(fn (Forms\Get $get): bool => in_array($get('key'), ConfiguredRegistrationForm::REGION_FIELDS, true))
                        ->dehydrated()
                        ->required(),
                    Forms\Components\TextInput::make('group')->label('Kelompok')->default('Informasi Tambahan'),
                    Forms\Components\Textarea::make('help')->label('Petunjuk'),
                    Forms\Components\TagsInput::make('options')
                        ->label('Opsi pilihan')
                        ->helperText(fn (Forms\Get $get): ?string => in_array($get('key'), ConfiguredRegistrationForm::REGION_FIELDS, true) ? 'Opsi wilayah diambil otomatis dari master wilayah Indonesia.' : null)
                        ->hidden(fn (Forms\Get $get): bool => in_array($get('key'), ConfiguredRegistrationForm::REGION_FIELDS, true))
                        ->default([]),
                    Forms\Components\Toggle::make('active')->label('Aktif')->default(true),
                    Forms\Components\Toggle::make('required')->label('Wajib')->default(false),
                ])->columns(2)->collapsible()->itemLabel(fn (array $state): string => $state['label'] ?? 'Field baru'),
            ])->collapsible(),
            Forms\Components\Section::make('Data Nilai')
                ->description('Default nonaktif. Jika diaktifkan, pendaftar mengisi nilai sesuai kelas, mata pelajaran, dan komponen yang ditentukan unit. Dapat dibatasi hanya untuk jalur tertentu.')
                ->schema([
                    Forms\Components\Toggle::make('academic_scores_enabled')
                        ->label('Aktifkan Data Nilai')
                        ->default(false)
                        ->live(),
                    Forms\Components\Toggle::make('academic_score_settings.required')
                        ->label('Semua nilai wajib diisi')
                        ->default(false)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('academic_scores_enabled')),
                    Forms\Components\Select::make('academic_score_settings.pathway_uuids')
                        ->label('Berlaku untuk Jalur')
                        ->helperText('Kosongkan untuk berlaku pada semua jalur pendaftaran unit.')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => $this->pathwayOptions())
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('academic_scores_enabled')),
                    Forms\Components\TextInput::make('academic_score_settings.min_score')
                        ->label('Nilai Minimum')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('academic_scores_enabled')),
                    Forms\Components\TextInput::make('academic_score_settings.max_score')
                        ->label('Nilai Maksimum')
                        ->numeric()
                        ->default(100)
                        ->required()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('academic_scores_enabled')),
                    Forms\Components\Repeater::make('academic_score_settings.grades')
                        ->label('Kelas / Tingkat')
                        ->default([])
                        ->schema([
                            Forms\Components\TextInput::make('key')
                                ->label('Kode')
                                ->helperText('Contoh: vii, viii, ix')
                                ->required()
                                ->regex('/^[a-z0-9_]+$/'),
                            Forms\Components\TextInput::make('label')
                                ->label('Label')
                                ->placeholder('Kelas VII')
                                ->required(),
                        ])
                        ->columns(2)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('academic_scores_enabled')),
                    Forms\Components\Repeater::make('academic_score_settings.subjects')
                        ->label('Mata Pelajaran')
                        ->default([])
                        ->schema([
                            Forms\Components\TextInput::make('key')
                                ->label('Kode')
                                ->helperText('Contoh: matematika')
                                ->required()
                                ->regex('/^[a-z0-9_]+$/'),
                            Forms\Components\TextInput::make('label')
                                ->label('Nama Mata Pelajaran')
                                ->required(),
                        ])
                        ->columns(2)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('academic_scores_enabled')),
                    Forms\Components\Repeater::make('academic_score_settings.assessments')
                        ->label('Komponen Nilai')
                        ->default([])
                        ->schema([
                            Forms\Components\TextInput::make('key')
                                ->label('Kode')
                                ->helperText('Contoh: rapor_s1, kkm_s1')
                                ->required()
                                ->regex('/^[a-z0-9_]+$/'),
                            Forms\Components\TextInput::make('label')
                                ->label('Label')
                                ->placeholder('Nilai Rapor S-1')
                                ->required(),
                        ])
                        ->columns(2)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('academic_scores_enabled')),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Prestasi')
                ->description('Default nonaktif. Prestasi menggunakan daftar dinamis dan dapat dibatasi hanya untuk jalur tertentu.')
                ->schema([
                    Forms\Components\Toggle::make('achievements_enabled')
                        ->label('Aktifkan Prestasi')
                        ->default(false)
                        ->live(),
                    Forms\Components\Toggle::make('achievement_settings.required')
                        ->label('Minimal satu prestasi wajib')
                        ->default(false)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('achievements_enabled')),
                    Forms\Components\TextInput::make('achievement_settings.max_entries')
                        ->label('Maksimal Prestasi')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(3)
                        ->required()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('achievements_enabled')),
                    Forms\Components\Select::make('achievement_settings.pathway_uuids')
                        ->label('Berlaku untuk Jalur')
                        ->helperText('Kosongkan untuk berlaku pada semua jalur pendaftaran unit.')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => $this->pathwayOptions())
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('achievements_enabled')),
                    Forms\Components\TagsInput::make('achievement_settings.levels')
                        ->label('Tingkat Prestasi')
                        ->default(['Sekolah', 'Kecamatan', 'Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'])
                        ->helperText('Contoh: Sekolah, Kabupaten/Kota, Provinsi, Nasional, Internasional.')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('achievements_enabled')),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Persyaratan Dokumen')->schema([
                Forms\Components\Repeater::make('document_requirements')->label('Dokumen')->schema([
                    Forms\Components\Hidden::make('key')->default(fn (): string => 'document_'.strtolower(Str::random(10)))->required(),
                    Forms\Components\TextInput::make('label')->label('Nama dokumen')->required(),
                    Forms\Components\Textarea::make('instructions')->label('Petunjuk'),
                    Forms\Components\TextInput::make('max_files')->label('Maksimum lampiran')->integer()->minValue(1)->maxValue(20)->default(1)->required(),
                    Forms\Components\Select::make('formats')->label('Format jawaban')->multiple()->options(['pdf' => 'PDF', 'docx' => 'DOCX', 'jpg' => 'JPG', 'png' => 'PNG'])->default(['pdf', 'jpg', 'png'])->required(),
                    Forms\Components\FileUpload::make('template_path')->label('Template PDF/DOCX')->disk('applicant-private')->directory(fn (): string => 'templates/'.$this->unitId())->visibility('private')->previewable(false)->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->maxSize(5120),
                    Forms\Components\Toggle::make('active')->label('Aktif')->default(true),
                    Forms\Components\Toggle::make('required')->label('Wajib')->default(false),
                ])->columns(2)->collapsible()->itemLabel(fn (array $state): string => $state['label'] ?? 'Dokumen baru'),
            ])->collapsible(),
            Forms\Components\Section::make('Tes pada Versi Ini')
                ->description('Toggle Tes hanya mengaktifkan tahapnya. Daftar di bawah menentukan tes yang benar-benar masuk ke versi pendaftaran. Saat Tes baru diaktifkan, tes aktif dari Konfigurasi Tes akan dimasukkan otomatis.')
                ->schema([
                    Forms\Components\Repeater::make('test_definitions')->label('Daftar tes')->default([])->schema([
                        Forms\Components\Select::make('uuid')
                            ->label('Tes')
                            ->options(fn (): array => AdmissionTest::where('unit_id', $this->unitId())
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->pluck('name', 'uuid')
                                ->all())
                            ->required(),
                    ]),
                ])->collapsible(),
            Forms\Components\Section::make('Daftar Ulang')->description('Persyaratan ini tersimpan pada versi konfigurasi dan hanya berlaku ketika Proses Pasca-Pengumuman diaktifkan.')->schema([
                Forms\Components\Repeater::make('re_registration_requirements')->label('Persyaratan daftar ulang')->default([])->schema([
                    Forms\Components\Hidden::make('key')->default(fn (): string => 'reregistration_'.strtolower(Str::random(10)))->required(),
                    Forms\Components\TextInput::make('label')->label('Nama persyaratan')->required(),
                    Forms\Components\Select::make('type')->label('Jenis')->options(['checklist' => 'Konfirmasi', 'document' => 'Dokumen', 'payment' => 'Pembayaran', 'information' => 'Informasi'])->default('checklist')->required(),
                    Forms\Components\Textarea::make('instructions')->label('Petunjuk'),
                    Forms\Components\Toggle::make('active')->label('Aktif')->default(true),
                    Forms\Components\Toggle::make('required')->label('Wajib')->default(true),
                ])->columns(2)->collapsible()->itemLabel(fn (array $state): string => $state['label'] ?? 'Persyaratan baru'),
            ])->collapsible(),
        ])->statePath('data');
    }

    public function save(): void
    {
        $configuration = UnitConfiguration::query()->where('uuid', $this->configurationUuid)->firstOrFail();
        $data = $this->configurationFormData($configuration);

        app(UnitConfigurationService::class)->save($configuration, auth()->user(), $data);

        Notification::make()
            ->title('Draft tersimpan')
            ->success()
            ->send();
    }

    public function publish(): void
    {
        $configuration = UnitConfiguration::query()->where('uuid', $this->configurationUuid)->firstOrFail();
        $data = $this->configurationFormData($configuration);
        $service = app(UnitConfigurationService::class);

        $draft = $service->save($configuration, auth()->user(), $data);
        $published = $service->save($draft->fresh(), auth()->user(), $data, true);

        Notification::make()
            ->title('Versi '.$published->version.' dipublikasikan untuk pendaftar baru')
            ->body('Perubahan form telah disimpan sebagai draft lalu dipublikasikan.')
            ->success()
            ->send();

        $this->loadUnit();
    }

    private function configurationFormData(UnitConfiguration $configuration): array
    {
        $data = $this->form->getState();
        $data['test_definitions'] = collect($data['test_definitions'] ?? [])
            ->map(fn (array $definition): array => [
                'id' => AdmissionTest::query()
                    ->where('unit_id', $configuration->unit_id)
                    ->where('uuid', $definition['uuid'] ?? null)
                    ->value('id'),
            ])
            ->all();

        return $data;
    }

    public function applyPublishedToActiveRegistrations(): void
    {
        $unit = Unit::query()->where('uuid', $this->unitUuid)->firstOrFail();

        $result = app(UnitConfigurationService::class)
            ->applyCurrentToEligibleActiveRegistrations($unit, auth()->user());

        Notification::make()
            ->title("{$result['updated']} pendaftaran aktif diperbarui")
            ->body("Hanya pendaftaran yang masih berada pada tahap Validasi Data yang diperbarui. {$result['skipped']} pendaftaran lain tetap memakai versi konfigurasi sebelumnya.")
            ->success()
            ->send();
    }

    public function showPreview(): void
    {
        $this->save();
        $unit = Unit::query()->where('uuid', $this->unitUuid)->firstOrFail();
        $this->previewForm->fill(['unit_uuid' => $unit->uuid, 'unit_configuration_uuid' => $this->configurationUuid, 'registration_opening_uuid' => $unit->registrationOpenings()->value('uuid'), 'registrant_type' => 'parent']);
        $this->preview = true;
    }

    /** @return list<array{uuid: string}> */
    private function activeTestDefinitions(): array
    {
        $unitId = $this->unitId();

        if (! $unitId) {
            return [];
        }

        return AdmissionTest::query()
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (AdmissionTest $test): array => ['uuid' => $test->uuid])
            ->values()
            ->all();
    }

    private function pathwayOptions(): array
    {
        $unitId = $this->unitId();

        if (! $unitId) {
            return [];
        }

        return \App\Models\RegistrationPathway::query()
            ->where('unit_id', $unitId)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->pluck('name', 'uuid')
            ->all();
    }

    private function unitId(): ?int
    {
        return Unit::query()->where('uuid', $this->unitUuid)->value('id');
    }
}
