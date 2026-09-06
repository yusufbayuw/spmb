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

    protected static ?string $navigationGroup = 'Master Data';

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
            Forms\Components\Section::make('Tahapan Pendaftaran')->description('Validasi identitas, kartu pendaftar, seleksi, dan publikasi hasil tetap tersedia.')->schema([
                Forms\Components\Toggle::make('payment_enabled')->label('Pembayaran'),
                Forms\Components\Toggle::make('documents_enabled')->label('Dokumen'),
                Forms\Components\Toggle::make('tests_enabled')->label('Tes'),
            ])->columns(3),
            Forms\Components\Section::make('Formulir Unit')->description('Pilih isian bawaan yang ingin disesuaikan atau tambahkan pertanyaan khusus. Identitas inti tetap wajib.')->schema([
                Forms\Components\Repeater::make('fields')->label('Pengaturan field')->default([])->schema([
                    Forms\Components\Select::make('key')->label('Isian')->options(fn (Forms\Get $get): array => ConfiguredRegistrationForm::fieldLabels() + [(! in_array($get('key'), ConfiguredRegistrationForm::BUILTIN_FIELDS, true) && $get('key') ? $get('key') : 'custom_'.strtolower(Str::random(8))) => 'Pertanyaan tambahan'])->default(fn (): string => 'custom_'.strtolower(Str::random(8)))->searchable()->required(),
                    Forms\Components\TextInput::make('label')->label('Label')->required(),
                    Forms\Components\Select::make('type')->label('Jenis')->options(['text' => 'Teks', 'textarea' => 'Teks panjang', 'number' => 'Angka', 'date' => 'Tanggal', 'select' => 'Pilihan tunggal', 'multiselect' => 'Pilihan jamak', 'boolean' => 'Ya/Tidak'])->default('text')->required(),
                    Forms\Components\TextInput::make('group')->label('Kelompok')->default('Informasi Tambahan'),
                    Forms\Components\Textarea::make('help')->label('Petunjuk'),
                    Forms\Components\TagsInput::make('options')->label('Opsi pilihan')->default([]),
                    Forms\Components\Toggle::make('active')->label('Aktif')->default(true),
                    Forms\Components\Toggle::make('required')->label('Wajib')->default(false),
                ])->columns(2)->collapsible()->itemLabel(fn (array $state): string => $state['label'] ?? 'Field baru'),
            ])->collapsible(),
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
            Forms\Components\Section::make('Tes pada Versi Ini')->description('Nama, status wajib, kriteria nilai, dan program studi disalin dari Konfigurasi Tes saat versi dipublikasikan.')->schema([
                Forms\Components\Repeater::make('test_definitions')->label('Daftar tes')->default([])->schema([
                    Forms\Components\Select::make('uuid')->label('Tes')->options(fn (): array => AdmissionTest::where('unit_id', $this->unitId())->pluck('name', 'uuid')->all())->required(),
                ]),
            ])->collapsible(),
            Forms\Components\Section::make('Daftar Ulang')->description('Persyaratan ini tersimpan pada versi konfigurasi dan hanya berlaku untuk pendaftar yang menggunakan versi tersebut.')->schema([
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

    public function save(bool $publish = false): void
    {
        $configuration = UnitConfiguration::query()->where('uuid', $this->configurationUuid)->firstOrFail();
        $data = $this->form->getState();
        $data['test_definitions'] = collect($data['test_definitions'] ?? [])
            ->map(fn (array $definition): array => ['id' => AdmissionTest::query()->where('unit_id', $configuration->unit_id)->where('uuid', $definition['uuid'] ?? null)->value('id')])
            ->all();
        $saved = app(UnitConfigurationService::class)->save($configuration, auth()->user(), $data, $publish);
        Notification::make()->title($publish ? 'Versi '.$saved->version.' dipublikasikan untuk pendaftar baru' : 'Draft tersimpan')->success()->send();
        if ($publish) {
            $this->loadUnit();
        }
    }

    public function showPreview(): void
    {
        $this->save();
        $unit = Unit::query()->where('uuid', $this->unitUuid)->firstOrFail();
        $this->previewForm->fill(['unit_uuid' => $unit->uuid, 'unit_configuration_uuid' => $this->configurationUuid, 'registration_opening_uuid' => $unit->registrationOpenings()->value('uuid'), 'registrant_type' => 'parent']);
        $this->preview = true;
    }

    private function unitId(): ?int
    {
        return Unit::query()->where('uuid', $this->unitUuid)->value('id');
    }
}
