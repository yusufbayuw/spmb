<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Applicant\Resources\RegistrationResource;
use App\Models\AdmissionTest;
use App\Models\Registration;
use App\Models\StudyProgram;
use App\Models\TestSession;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Services\ConfiguredRegistrationForm;
use App\Services\TestBookingService;
use App\Services\UnitConfigurationService;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        return (bool) auth()->user()?->is_active
            && (auth()->user()->isAdmin() || auth()->user()->isAdminUnit());
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
        $data = app(UnitConfigurationService::class)->normalizeEditorData($draft->toArray());
        $data['workflow_stage_labels'] = array_replace(
            Registration::STAGES,
            is_array($data['workflow_stage_labels'] ?? null) ? $data['workflow_stage_labels'] : [],
        );
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
            'show_year' => false,
            'show_organizer' => false,
            'show_description' => false,
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
            Forms\Components\Section::make('Tahapan Pendaftaran')->description('Validasi identitas, kartu pendaftar, seleksi, dan publikasi hasil tetap tersedia. Tahap setelah pengumuman dapat diaktifkan saat diperlukan. Pada perguruan tinggi, label dan urutan progres ditentukan per Program Studi.')->schema([
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
                    ->helperText('Aktifkan workflow lanjutan setelah pengumuman. Untuk perguruan tinggi, nama dan urutan progres dapat diatur per Program Studi, misalnya Pembayaran Registrasi, Daftar Ulang, lalu Perwalian. Jika nonaktif, setelah pengumuman proses langsung selesai.'),
            ])->columns(5),
            Forms\Components\Section::make('Urutan Proses Pra-Seleksi')
                ->description('Atur urutan operasional Kartu Pendaftar dan Berkas. Pembayaran tetap menjadi gate sebelum keduanya, sedangkan Tes dan Seleksi tetap mengikuti seluruh prasyarat. Perubahan hanya berlaku pada versi konfigurasi baru.')
                ->schema([
                    Forms\Components\Repeater::make('workflow_blocks')
                        ->label('Urutan proses')
                        ->schema([
                            Forms\Components\Select::make('key')
                                ->label('Tahap')
                                ->options(Registration::WORKFLOW_BLOCK_LABELS)
                                ->disabled()
                                ->dehydrated()
                                ->required(),
                        ])
                        ->reorderable()
                        ->addable(false)
                        ->deletable(false)
                        ->itemLabel(fn (array $state): string => Registration::WORKFLOW_BLOCK_LABELS[$state['key'] ?? ''] ?? 'Tahap'),
                ])
                ->collapsible(),
            Forms\Components\Section::make('Nama Tahapan di Portal Pendaftar')
                ->description('Ubah nama tampilan setiap tahapan template tanpa mengubah kunci maupun logika workflow. Pada perguruan tinggi, pengaturan per Program Studi tetap menjadi override yang lebih spesifik.')
                ->schema(
                    collect(Registration::STAGES)
                        ->map(fn (string $label, string $stage) => Forms\Components\TextInput::make('workflow_stage_labels.'.$stage)
                            ->label($label)
                            ->required()
                            ->maxLength(120))
                        ->values()
                        ->all(),
                )
                ->columns(2)
                ->collapsible(),
            Forms\Components\Section::make('Formulir Unit')
                ->description('Tentukan kebijakan umum untuk isian bawaan. Repeater di bawah cukup digunakan untuk field yang perlu menjadi pengecualian atau dikustomisasi. Identitas inti tetap wajib.')
                ->schema([
                Forms\Components\Repeater::make('form_groups')
                    ->label('Kelompok Pertanyaan Tambahan')
                    ->helperText('Kelompok memiliki identitas tetap sehingga namanya dapat diubah tanpa memutus relasi field. Urutkan dengan drag & drop. Setelah menambah kelompok baru, simpan draft agar kelompok muncul pada Struktur Formulir.')
                    ->default([
                        ['key' => 'group_additional', 'label' => 'Informasi Tambahan'],
                    ])
                    ->schema([
                        Forms\Components\Hidden::make('key')
                            ->default(fn (): string => 'group_'.strtolower(Str::random(10)))
                            ->required(),
                        Forms\Components\TextInput::make('label')
                            ->label('Nama Kelompok')
                            ->required()
                            ->maxLength(100),
                    ])
                    ->reorderable()
                    ->live()
                    ->itemLabel(fn (array $state): string => $state['label'] ?? 'Kelompok baru'),
                Forms\Components\Repeater::make('form_layout')
                    ->label('Struktur Formulir')
                    ->helperText('Pilihan Pendaftaran selalu dikunci sebagai bagian pertama. Drag & drop bagian lain. Kelompok baru akan ditambahkan otomatis setelah draft disimpan.')
                    ->schema([
                        Forms\Components\Select::make('key')
                            ->label('Bagian')
                            ->options(fn (): array => $this->formLayoutOptions())
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                    ])
                    ->reorderable()
                    ->addable(false)
                    ->deletable(false)
                    ->itemLabel(fn (array $state): string => $this->formLayoutOptions()[$state['key'] ?? ''] ?? 'Bagian formulir'),
                Forms\Components\Select::make('builtin_field_policy')
                    ->label('Kebijakan Isian Bawaan')
                    ->options(ConfiguredRegistrationForm::BUILTIN_FIELD_POLICIES)
                    ->default('system_default')
                    ->required()
                    ->native(false)
                    ->helperText('Pilih "Wajibkan semua isian bawaan" agar seluruh field bawaan aktif dan wajib tanpa mengatur satu per satu. Tambahkan field pada bagian pengecualian hanya bila perlu dibedakan.'),
                Forms\Components\Repeater::make('fields')
                    ->label('Kustomisasi / Pengecualian Field')
                    ->helperText('Kosongkan bila semua field cukup mengikuti kebijakan di atas. Field yang ditambahkan di sini akan mengoverride status Aktif/Wajib, label, dan petunjuk untuk field tersebut.')
                    ->default([])->schema([
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
                    Forms\Components\Hidden::make('group'),
                    Forms\Components\Select::make('group_key')
                        ->label('Kelompok')
                        ->options(fn (): array => collect($this->data['form_groups'] ?? [])->pluck('label', 'key')->all())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Forms\Get $get): bool => ! in_array($get('key'), ConfiguredRegistrationForm::BUILTIN_FIELDS, true))
                        ->required(fn (Forms\Get $get): bool => ! in_array($get('key'), ConfiguredRegistrationForm::BUILTIN_FIELDS, true)),
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
                            Forms\Components\Hidden::make('key')
                                ->default(fn (): string => 'grade_'.strtolower(Str::random(10)))
                                ->required(),
                            Forms\Components\TextInput::make('label')
                                ->label('Label')
                                ->placeholder('Kelas VII')
                                ->required(),
                        ])
                        ->columns(1)
                        ->itemLabel(fn (array $state): string => $state['label'] ?? 'Kelas / tingkat')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('academic_scores_enabled')),
                    Forms\Components\Repeater::make('academic_score_settings.subjects')
                        ->label('Mata Pelajaran')
                        ->default([])
                        ->schema([
                            Forms\Components\Hidden::make('key')
                                ->default(fn (): string => 'subject_'.strtolower(Str::random(10)))
                                ->required(),
                            Forms\Components\TextInput::make('label')
                                ->label('Nama Mata Pelajaran')
                                ->required(),
                        ])
                        ->columns(1)
                        ->itemLabel(fn (array $state): string => $state['label'] ?? 'Mata pelajaran')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('academic_scores_enabled')),
                    Forms\Components\Repeater::make('academic_score_settings.assessments')
                        ->label('Komponen Nilai')
                        ->default([])
                        ->schema([
                            Forms\Components\Hidden::make('key')
                                ->default(fn (): string => 'assessment_'.strtolower(Str::random(10)))
                                ->required(),
                            Forms\Components\TextInput::make('label')
                                ->label('Label')
                                ->placeholder('Nilai Rapor S-1')
                                ->required(),
                        ])
                        ->columns(1)
                        ->itemLabel(fn (array $state): string => $state['label'] ?? 'Komponen nilai')
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
                    Forms\Components\Toggle::make('achievement_settings.show_year')
                        ->label('Tampilkan Tahun Prestasi')
                        ->default(false)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('achievements_enabled')),
                    Forms\Components\Toggle::make('achievement_settings.show_organizer')
                        ->label('Tampilkan Penyelenggara')
                        ->default(false)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('achievements_enabled')),
                    Forms\Components\Toggle::make('achievement_settings.show_description')
                        ->label('Tampilkan Keterangan Prestasi')
                        ->default(false)
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
                ->description('Pilih master tes yang berlaku pada versi ini. Sesi tetap merupakan data operasional: dapat ditambah atau diubah tanpa membuat versi konfigurasi baru.')
                ->schema([
                    Forms\Components\Repeater::make('test_definitions')
                        ->label('Daftar tes')
                        ->default([])
                        ->schema([
                            Forms\Components\Select::make('uuid')
                                ->label('Tes')
                                ->options(fn (): array => AdmissionTest::where('unit_id', $this->unitId())
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'uuid')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->live()
                                ->createOptionForm($this->admissionTestCreateFields())
                                ->createOptionUsing(fn (array $data): string => $this->createAdmissionTest($data))
                                ->createOptionAction(fn (Action $action): Action => $action
                                    ->modalHeading('Buat Tes Baru')
                                    ->modalWidth(MaxWidth::ThreeExtraLarge))
                                ->required(),
                        ])
                        ->extraItemActions([
                            Action::make('manageSessions')
                                ->label('Kelola Sesi')
                                ->icon('heroicon-m-calendar-days')
                                ->color('gray')
                                ->visible(function (array $arguments, Forms\Components\Repeater $component): bool {
                                    $item = $component->getRawItemState($arguments['item']);

                                    return filled($item['uuid'] ?? null);
                                })
                                ->modalHeading('Kelola Sesi Tes')
                                ->modalDescription('Perubahan sesi berlaku langsung sebagai data operasional dan tidak mengubah versi konfigurasi pendaftaran.')
                                ->modalWidth(MaxWidth::SevenExtraLarge)
                                ->slideOver()
                                ->modalSubmitActionLabel('Simpan Sesi')
                                ->form($this->sessionManagerFields())
                                ->mountUsing(function (Form $form, array $arguments, Forms\Components\Repeater $component): void {
                                    $test = $this->testFromRepeaterItem($arguments, $component);
                                    $form->fill(['sessions' => $this->sessionRows($test)]);
                                })
                                ->action(function (array $data, array $arguments, Forms\Components\Repeater $component): void {
                                    $test = $this->testFromRepeaterItem($arguments, $component);
                                    $this->saveManagedSessions($test, $data['sessions'] ?? []);

                                    Notification::make()
                                        ->title('Sesi tes tersimpan')
                                        ->body('Perubahan sesi '.$test->name.' berlaku langsung tanpa membuat versi konfigurasi baru.')
                                        ->success()
                                        ->send();
                                }),
                        ])
                        ->collapsible(),
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
        $this->loadUnit();

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

        try {
            $draft = $service->save($configuration, auth()->user(), $data);
            $published = $service->save($draft->fresh(), auth()->user(), $data, true);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('data.'.$key, $message);
                }
            }

            Notification::make()
                ->title('Publikasi konfigurasi gagal')
                ->body(collect($exception->errors())->flatten()->first() ?: 'Periksa kembali konfigurasi sebelum mempublikasikan.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $publishedVersion = $published->version;
        $this->loadUnit();

        Notification::make()
            ->title('Publikasi v'.$publishedVersion.' berhasil')
            ->body('Konfigurasi v'.$publishedVersion.' telah diaktifkan untuk pendaftar baru.')
            ->success()
            ->send();
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
            ->body("Perubahan Nilai/Prestasi hanya diterapkan ke pendaftar yang masih di tahap Validasi Data. Konfigurasi workflow lain tetap mengikuti pengaman proses yang sudah ada. {$result['skipped']} pendaftaran dilewati.")
            ->success()
            ->send();
    }

    public function showPreview(): void
    {
        $this->save();

        $unit = Unit::query()->where('uuid', $this->unitUuid)->firstOrFail();
        $configuration = UnitConfiguration::query()->where('uuid', $this->configurationUuid)->firstOrFail();
        $configuredPathwayUuid = collect([
            ...($configuration->academic_score_settings['pathway_uuids'] ?? []),
            ...($configuration->achievement_settings['pathway_uuids'] ?? []),
        ])->filter()->first();

        $pathwayUuid = $configuredPathwayUuid
            ?: $unit->registrationPathways()->where('is_active', true)->whereNull('archived_at')->value('uuid');

        $this->previewForm->fill([
            'unit_uuid' => $unit->uuid,
            'unit_configuration_uuid' => $this->configurationUuid,
            'registration_opening_uuid' => $unit->registrationOpenings()->value('uuid'),
            'registration_pathway_uuid' => $pathwayUuid,
            'registrant_type' => 'parent',
        ]);
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

    private function admissionTestCreateFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Nama Tes')
                ->required()
                ->maxLength(150),
            Forms\Components\TextInput::make('code')
                ->label('Kode')
                ->maxLength(50),
            Forms\Components\Textarea::make('description')
                ->label('Deskripsi')
                ->columnSpanFull(),
            Forms\Components\Select::make('study_program_id')
                ->label('Program Studi')
                ->placeholder('Semua program studi pada institusi')
                ->options(fn (): array => StudyProgram::query()
                    ->where('unit_id', $this->unitId())
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->mapWithKeys(fn (StudyProgram $program): array => [$program->id => $program->label()])
                    ->all())
                ->visible(fn (): bool => Unit::query()->whereKey($this->unitId())->where('institution_type', 'university')->exists())
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('result_type')
                ->label('Jenis Hasil')
                ->options(['score' => 'Nilai', 'pass_fail' => 'Lulus/Tidak'])
                ->default('score')
                ->required(),
            Forms\Components\TextInput::make('passing_score')
                ->label('Nilai Minimum')
                ->numeric(),
            Forms\Components\TextInput::make('sort_order')
                ->label('Urutan')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('is_required')
                ->label('Wajib')
                ->default(true),
        ];
    }

    private function createAdmissionTest(array $data): string
    {
        $unitId = $this->unitId();
        abort_unless($unitId, 404);
        app(UnitConfigurationService::class)->authorize(auth()->user(), $unitId);

        $test = AdmissionTest::create($data + ['unit_id' => $unitId, 'is_active' => true]);

        return $test->uuid;
    }

    private function sessionManagerFields(): array
    {
        return [
            Forms\Components\Repeater::make('sessions')
                ->label('Sesi')
                ->default([])
                ->schema([
                    Forms\Components\Hidden::make('uuid'),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Mulai')
                        ->timezone(config('app.timezone'))
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->seconds(false)
                        ->required(),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('Selesai')
                        ->timezone(config('app.timezone'))
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->seconds(false)
                        ->required(),
                    Forms\Components\DateTimePicker::make('booking_closes_at')
                        ->label('Batas pemesanan/perpindahan')
                        ->timezone(config('app.timezone'))
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->seconds(false)
                        ->helperText('Kosongkan untuk otomatis 24 jam sebelum mulai.'),
                    Forms\Components\TextInput::make('location')
                        ->label('Lokasi')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('capacity')
                        ->label('Kuota')
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Aktif',
                            'closed' => 'Pemesanan ditutup',
                            'cancelled' => 'Dibatalkan',
                        ])
                        ->default('active')
                        ->required(),
                    Forms\Components\Textarea::make('instructions')
                        ->label('Petunjuk Peserta')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->addActionLabel('Tambah Sesi')
                ->deletable(false)
                ->reorderable(false)
                ->collapsible()
                ->itemLabel(fn (array $state): string => filled($state['starts_at'] ?? null)
                    ? Carbon::parse($state['starts_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i').' · '.($state['location'] ?: 'Lokasi belum diisi')
                    : 'Sesi baru'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function sessionRows(AdmissionTest $test): array
    {
        $timezone = config('app.timezone');

        return $test->sessions()
            ->orderBy('starts_at')
            ->get()
            ->map(fn (TestSession $session): array => [
                'uuid' => $session->uuid,
                'starts_at' => $session->starts_at?->copy()->timezone($timezone)->format('Y-m-d H:i:s'),
                'ends_at' => $session->ends_at?->copy()->timezone($timezone)->format('Y-m-d H:i:s'),
                'booking_closes_at' => $session->booking_closes_at?->copy()->timezone($timezone)->format('Y-m-d H:i:s'),
                'location' => $session->location,
                'capacity' => $session->capacity,
                'status' => $session->status,
                'instructions' => $session->instructions,
            ])
            ->values()
            ->all();
    }

    private function testFromRepeaterItem(array $arguments, Forms\Components\Repeater $component): AdmissionTest
    {
        $item = $component->getRawItemState($arguments['item']);

        return AdmissionTest::query()
            ->where('unit_id', $this->unitId())
            ->where('uuid', $item['uuid'] ?? null)
            ->firstOrFail();
    }

    /** @param list<array<string, mixed>> $rows */
    private function saveManagedSessions(AdmissionTest $test, array $rows): void
    {
        app(UnitConfigurationService::class)->authorize(auth()->user(), (int) $test->unit_id);

        DB::transaction(function () use ($test, $rows): void {
            foreach ($rows as $row) {
                $session = filled($row['uuid'] ?? null)
                    ? TestSession::query()
                        ->where('admission_test_id', $test->id)
                        ->where('uuid', $row['uuid'])
                        ->firstOrFail()
                    : null;

                $payload = [
                    'admission_test_id' => $test->id,
                    'starts_at' => $this->normalizeSessionDate($row['starts_at'] ?? null),
                    'ends_at' => $this->normalizeSessionDate($row['ends_at'] ?? null),
                    'booking_closes_at' => $this->normalizeSessionDate($row['booking_closes_at'] ?? null),
                    'location' => $row['location'] ?? null,
                    'capacity' => $row['capacity'] ?? null,
                    'instructions' => $row['instructions'] ?? null,
                    'status' => $row['status'] ?? 'active',
                ];

                app(TestBookingService::class)->saveSession($session, $payload, auth()->user());
            }
        });
    }

    private function normalizeSessionDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)
            ->timezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }

    private function formLayoutOptions(): array
    {
        $options = [
            'registration_choice' => 'Pilihan Pendaftaran (tetap pertama)',
            'identity' => 'Identitas Calon Siswa / Mahasiswa',
            'parents' => 'Data Orang Tua',
        ];

        foreach ($this->data['form_groups'] ?? [] as $group) {
            if (filled($group['key'] ?? null) && filled($group['label'] ?? null)) {
                $options['group:'.$group['key']] = $group['label'];
            }
        }

        $options['academic_scores'] = 'Data Nilai';
        $options['achievements'] = 'Prestasi';

        return $options;
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
