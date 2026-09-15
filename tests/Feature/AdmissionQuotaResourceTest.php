<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\AdmissionQuotaResource;
use App\Models\AdmissionQuota;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionQuotaResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unit_can_open_edit_quota_page_with_available_pathway_options(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'Unit Kuota',
            'code' => 'KUOTA',
            'is_active' => true,
        ]);
        $adminUnit = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $adminUnit->assignRole('admin_unit');

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'open',
        ]);
        $pathway = RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);
        $quota = AdmissionQuota::create([
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $pathway->id,
            'capacity' => 100,
            'offer_expires_in_hours' => 72,
            're_registration_due_in_days' => 14,
            'is_active' => true,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($adminUnit)
            ->get(AdmissionQuotaResource::getUrl('edit', ['record' => $quota]))
            ->assertOk();
    }
}
