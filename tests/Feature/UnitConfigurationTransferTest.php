<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\UnitConfigurationTransfer;
use App\Filament\Admin\Pages\UnitRegistrationSettings;
use App\Models\Faq;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\UnitConfigurationService;
use App\Services\UnitConfigurationTransferService;
use Database\Seeders\AdminUnitRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitConfigurationTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unit_can_export_and_import_portable_unit_configuration(): void
    {
        $this->seed(AdminUnitRoleSeeder::class);

        $source = Unit::create([
            'name' => 'SMP Sumber',
            'code' => 'SMPSRC',
            'institution_type' => 'school',
            'description' => 'Ringkasan sumber',
            'public_headline' => 'Penerimaan SMP Sumber',
            'public_body' => '<p>Profil <strong>sumber</strong>.</p>',
            'pre_registration_heading' => 'Persiapan sumber',
            'pre_registration_body' => '<p>Siapkan data.</p>',
            'pre_registration_items' => [
                ['title' => 'Kartu Keluarga', 'description' => 'File terbaca.'],
            ],
            'is_active' => true,
        ]);

        $sourcePathway = RegistrationPathway::create([
            'unit_id' => $source->id,
            'name' => 'Reguler',
            'description' => 'Jalur reguler',
            'is_active' => true,
        ]);

        Faq::create([
            'unit_id' => $source->id,
            'registration_pathway_id' => $sourcePathway->id,
            'question' => 'Dokumen apa yang disiapkan?',
            'answer' => '<p>Siapkan <strong>dokumen resmi</strong>.</p>',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        app(UnitConfigurationService::class)->initialize($source);

        $target = Unit::create([
            'name' => 'SMP Tujuan',
            'code' => 'SMPTGT',
            'institution_type' => 'school',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $target->id,
            'is_active' => true,
        ]);
        $admin->assignRole('admin_unit');

        $payload = app(UnitConfigurationTransferService::class)->export($source);

        $this->actingAs($admin);

        $result = app(UnitConfigurationTransferService::class)->import($target, $admin, $payload);

        $this->assertTrue($result['configuration']);
        $this->assertSame('Penerimaan SMP Sumber', $target->fresh()->public_headline);
        $this->assertSame('Persiapan sumber', $target->fresh()->pre_registration_heading);
        $this->assertDatabaseHas('registration_pathways', [
            'unit_id' => $target->id,
            'name' => 'Reguler',
        ]);
        $this->assertDatabaseHas('faqs', [
            'unit_id' => $target->id,
            'question' => 'Dokumen apa yang disiapkan?',
        ]);
        $this->assertDatabaseHas('unit_configurations', [
            'unit_id' => $target->id,
            'status' => 'draft',
            'registration_number_prefix' => 'SMPSRC',
        ]);
    }

    public function test_admin_unit_only_sees_its_own_unit_in_configuration_pages(): void
    {
        $this->seed(AdminUnitRoleSeeder::class);

        $unit = Unit::create([
            'name' => 'SMP Sendiri',
            'code' => 'SMPA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $other = Unit::create([
            'name' => 'SMP Lain',
            'code' => 'SMPB',
            'institution_type' => 'school',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $admin->assignRole('admin_unit');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $transfer = Livewire::test(UnitConfigurationTransfer::class);
        $settings = Livewire::test(UnitRegistrationSettings::class);

        $this->assertSame([$unit->uuid => $unit->name], $transfer->instance()->units());
        $this->assertSame([$unit->uuid => $unit->name], $settings->instance()->units());
        $this->assertArrayNotHasKey($other->uuid, $transfer->instance()->units());
        $this->assertArrayNotHasKey($other->uuid, $settings->instance()->units());
    }
}
