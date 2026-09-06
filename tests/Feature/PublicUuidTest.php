<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\ParentInfo;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PublicUuidTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_routes_preserve_owner_authorization_and_reject_numeric_ids(): void
    {
        [$registration, $owner] = $this->fixture();
        $this->assertTrue(Str::isUuid($registration->uuid));
        $this->assertStringContainsString($registration->uuid, route('registration.card', $registration));
        $this->actingAs($owner)->get('/pendaftar/status/'.$registration->uuid)->assertOk();
        $this->get('/pendaftar/status/'.$registration->id)->assertNotFound();
        $this->get('/registration/'.$registration->id.'/card')->assertNotFound();
        $this->get('/pendaftar/status/'.Str::uuid())->assertNotFound();
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('pendaftar');
        $this->actingAs($other)->get('/pendaftar/status/'.$registration->uuid)->assertNotFound();
    }

    public function test_admin_record_resolution_uses_uuid_and_keeps_unit_scope(): void
    {
        [$registration] = $this->fixture();
        $staff = User::where('email', 'tu.sd@tarunabakti.sch.id')->firstOrFail();
        $this->actingAs($staff);
        $this->assertSame($registration->id, RegistrationResource::resolveRecordRouteBinding($registration->uuid)?->id);
        $this->assertNull(RegistrationResource::resolveRecordRouteBinding($registration->id));
        $otherStaff = User::where('email', 'tu.smp@tarunabakti.sch.id')->firstOrFail();
        $this->actingAs($otherStaff);
        $this->assertNull(RegistrationResource::resolveRecordRouteBinding($registration->uuid));
    }

    public function test_backfill_is_repeatable_and_preserves_numeric_relationships(): void
    {
        [$registration] = $this->fixture();
        $unitId = $registration->unit_id;
        $notification = $registration->user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'legacy',
            'data' => ['actions' => [['url' => url('/pendaftar/status/'.$registration->id)]]],
            'read_at' => now(),
        ]);
        DB::table('registrations')->where('id', $registration->id)->update(['uuid' => null]);
        $migration = require database_path('migrations/2026_09_05_094702_add_public_uuids_to_application_records.php');
        $migration->up();
        $uuid = $registration->fresh()->uuid;
        $migration->up();
        $this->assertTrue(Str::isUuid($uuid));
        $this->assertSame($uuid, $registration->fresh()->uuid);
        $this->assertSame($unitId, $registration->fresh()->unit->id);
        $this->assertSame('data_validation', $registration->fresh()->current_stage);
        $this->assertSame(url('/pendaftar/status/'.$uuid), $notification->fresh()->data['actions'][0]['url']);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_uuid_cannot_be_changed_and_replicated_records_receive_a_new_uuid(): void
    {
        $unit = Unit::create(['name' => 'Unit', 'code' => 'TEST', 'is_active' => true]);
        $copy = $unit->replicate();
        $copy->code = 'COPY';
        $copy->save();
        $this->assertNotSame($unit->uuid, $copy->uuid);
        $this->expectException(ValidationException::class);
        $unit->forceFill(['uuid' => (string) Str::uuid()])->save();
    }

    public function test_applicant_create_form_accepts_only_uuid_references(): void
    {
        [$registration, $owner] = $this->fixture();
        $pathway = RegistrationPathway::create(['unit_id' => $registration->unit_id, 'name' => 'UUID', 'is_active' => true]);
        $opening = RegistrationOpening::where('unit_id', $registration->unit_id)->firstOrFail();
        $opening->update(['status' => 'open', 'opened_at' => now()->subHour(), 'closed_at' => now()->addDay()]);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        $page = Livewire::withQueryParams(['opening' => $opening->uuid])->test(CreateRegistration::class);
        $page->fillForm(['registration_pathway_uuid' => (string) $pathway->id, 'registrant_type' => 'self', 'full_name' => 'Tidak Sah', 'nik' => '3273010101010002', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2020-01-01', 'home_address' => 'Bandung', 'parentInfo' => ['father_name' => 'Ayah', 'mother_name' => 'Ibu']])
            ->call('create')
            ->assertHasErrors();
        $this->assertDatabaseMissing('registrations', ['nik' => '3273010101010002']);
        $page->fillForm(['registration_pathway_uuid' => $pathway->uuid, 'parentInfo' => ['father_name' => 'Ayah', 'mother_name' => 'Ibu']])->call('create')->assertHasNoFormErrors();
    }

    public function test_new_registration_prefills_repeatable_family_and_address_data_but_keeps_it_editable(): void
    {
        [$previousRegistration, $owner] = $this->fixture();
        $previousRegistration->update([
            'home_address' => 'Jl. Melati No. 10',
            'rt' => '001',
            'rw' => '002',
            'village' => 'Sukamaju',
            'district' => 'Cibeunying',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40123',
        ]);
        ParentInfo::create([
            'registration_id' => $previousRegistration->id,
            'father_name' => 'Budi Santoso',
            'father_nik' => '3273010101010001',
            'father_phone' => '081234567890',
            'mother_name' => 'Siti Aminah',
            'mother_nik' => '3273010101010002',
            'mother_phone' => '081298765432',
        ]);

        $opening = RegistrationOpening::query()->where('unit_id', $previousRegistration->unit_id)->firstOrFail();
        $opening->update([
            'status' => 'open',
            'opened_at' => now()->subHour(),
            'closed_at' => now()->addDay(),
        ]);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->assertFormSet([
                'home_address' => 'Jl. Melati No. 10',
                'rt' => '001',
                'city' => 'Bandung',
                'parentInfo.father_name' => 'Budi Santoso',
                'parentInfo.mother_name' => 'Siti Aminah',
                'parentInfo.father_phone' => '081234567890',
            ])
            ->fillForm([
                'home_address' => 'Jl. Anggrek No. 20',
                'parentInfo' => ['father_name' => 'Budi Pratama'],
            ])
            ->assertFormSet([
                'home_address' => 'Jl. Anggrek No. 20',
                'parentInfo.father_name' => 'Budi Pratama',
            ]);
    }

    private function fixture(): array
    {
        $this->seed(DatabaseSeeder::class);
        $unit = Unit::where('code', 'SD')->firstOrFail();
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('pendaftar');
        $registration = Registration::create(['user_id' => $owner->id, 'unit_id' => $unit->id, 'full_name' => 'Peserta UUID', 'nik' => '3273010101010001', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2020-01-01', 'home_address' => 'Bandung', 'current_stage' => 'data_validation']);

        return [$registration, $owner];
    }
}
