<?php

namespace Tests\Feature;

use App\Filament\Applicant\Resources\RegistrationResource;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Services\ConfiguredRegistrationForm;
use App\Services\UnitConfigurationService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreIdentityPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_configuration_gets_safe_defaults(): void
    {
        $config = app(UnitConfigurationService::class)->initialize(
            Unit::create(['name' => 'Unit Contoh', 'code' => 'UNIT-T', 'is_active' => true])
        );
        $data = app(UnitConfigurationService::class)->normalizeEditorData($config->toArray());
        $this->assertSame(array_keys(ConfiguredRegistrationForm::CORE_PRESENTATION_FIELDS), array_column($data['core_identity_fields'], 'key'));
    }

    public function test_ordering_and_text_are_presentation_only(): void
    {
        $config = new UnitConfiguration([
            'core_identity_fields' => [
                ['key' => 'home_address', 'label' => 'Alamat Siswa', 'help' => 'Masukkan alamat lengkap', 'placeholder' => 'Nama jalan'],
                ['key' => 'full_name', 'label' => 'Nama Calon Siswa', 'help' => 'Sesuai dokumen', 'placeholder' => 'Nama lengkap'],
                ['key' => 'gender', 'label' => 'Jenis Kelamin'],
                ['key' => 'birth_place', 'label' => 'Kota Lahir'],
            ],
        ]);
        $form = app(ConfiguredRegistrationForm::class);
        $fields = $form->applyCoreIdentityPresentation([
            TextInput::make('full_name')->label('Nama Lengkap')->required()->maxLength(150),
            TextInput::make('nisn')->label('NISN'),
            TextInput::make('birth_place')->label('Tempat Lahir')->required(),
            TextInput::make('gender')->label('Jenis Kelamin')->required(),
            TextInput::make('home_address')->label('Alamat Rumah')->required(),
        ], $config);

        $this->assertSame(['home_address', 'nisn', 'full_name', 'gender', 'birth_place'], array_map(fn ($field) => $field->getName(), array_values($fields)));
        $this->assertSame('Alamat Siswa', $fields[0]->getLabel());
        $this->assertSame('Nama jalan', $fields[0]->getPlaceholder());
        $this->assertTrue($fields[0]->isRequired());
        $this->assertSame('Nama Calon Siswa', $fields[2]->getLabel());
        $this->assertSame('Nama lengkap', $fields[2]->getPlaceholder());
        $this->assertTrue($fields[2]->isRequired());
        $this->assertSame(150, $fields[2]->getMaxLength());
    }

    public function test_unknown_and_duplicate_identity_keys_are_ignored(): void
    {
        $normalized = ConfiguredRegistrationForm::normalizeCoreIdentityFields([
            ['key' => 'nik', 'label' => 'Jangan ubah NIK'],
            ['key' => 'full_name', 'label' => 'Nama Peserta'],
            ['key' => 'full_name', 'label' => 'Duplikat'],
        ]);
        $this->assertCount(5, $normalized);
        $this->assertSame('Nama Peserta', collect($normalized)->firstWhere('key', 'full_name')['label']);
        $this->assertContains('nik', array_column($normalized, 'key'));
    }

    public function test_lookup_fields_are_not_modified_by_core_identity_presentation(): void
    {
        $form = app(ConfiguredRegistrationForm::class);
        $configuration = new UnitConfiguration(['core_identity_fields' => [
            ['key' => 'full_name', 'label' => 'Nama Siswa'],
        ]]);
        $lookup = $form->applyCoreIdentityPresentation([
            TextInput::make('nik')->label('NIK')->required()->rule('digits:16'),
            DatePicker::make('birth_date')->label('Tanggal Lahir')->required(),
        ], $configuration);
        $this->assertSame(['nik', 'birth_date'], array_map(fn ($field) => $field->getName(), $lookup));
        $this->assertSame('NIK', $lookup[0]->getLabel());
        $this->assertTrue($lookup[0]->isRequired());
        $this->assertSame('Tanggal Lahir', $lookup[1]->getLabel());
    }
}
