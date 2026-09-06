<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\TestSession;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\UnitConfigurationService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PaymentReceiptSeeder;
use Database\Seeders\RegistrationOpeningSeeder;
use Database\Seeders\TestSessionSeeder;
use Database\Seeders\UnitRegistrationConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CurrentApplicationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_seed_creates_current_configurations_and_can_be_repeated_without_resetting_user_changes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(7, UnitConfiguration::count());
        $this->assertSame(0, UnitConfiguration::where('legacy', true)->count());
        $sd = Unit::where('code', 'SD')->firstOrFail();
        $configuration = app(UnitConfigurationService::class)->current($sd->id);
        $this->assertFalse(collect($configuration->document_requirements)->firstWhere('key', 'report_card')['active']);
        $admin = User::where('email', 'admin@tarunabakti.sch.id')->firstOrFail();
        $admin->update(['password' => Hash::make('changed-secret'), 'is_active' => false]);
        $opening = RegistrationOpening::where('unit_id', $sd->id)->firstOrFail();
        $opening->update(['status' => 'closed', 'registration_fee' => 123000, 'opened_at' => now()->subDays(10), 'closed_at' => now()->subDay()]);
        $sd->update(['name' => 'Nama pilihan TU', 'is_active' => false]);
        $pathway = $sd->registrationPathways()->firstOrFail();
        $pathway->update(['is_active' => false]);
        $this->travel(2)->days();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(7, UnitConfiguration::count());
        $this->assertSame(13, RegistrationOpening::count());
        $this->assertSame('closed', $opening->fresh()->status);
        $this->assertSame('123000.00', $opening->fresh()->registration_fee);
        $this->assertSame('Nama pilihan TU', $sd->fresh()->name);
        $this->assertFalse($sd->fresh()->is_active);
        $this->assertFalse($pathway->fresh()->is_active);
        $this->assertTrue(Hash::check('changed-secret', $admin->fresh()->password));
        $this->assertFalse($admin->fresh()->is_active);
    }

    public function test_new_openings_follow_published_payment_configuration_without_changing_its_version(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tbu = Unit::where('code', 'TBU')->firstOrFail();
        $staff = User::where('email', 'tu.tbu@tbu.ac.id')->firstOrFail();
        RegistrationOpening::where('unit_id', $tbu->id)->update(['registration_fee' => 0]);
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($tbu, $staff);
        $published = $service->save($draft, $staff, array_replace($draft->toArray(), ['payment_enabled' => false]), true);
        $program = $tbu->studyPrograms()->create(['code' => 'S1-NEW', 'name' => 'Program Baru', 'degree_level' => 'S1', 'is_active' => true]);
        $this->seed([UnitRegistrationConfigurationSeeder::class, RegistrationOpeningSeeder::class]);
        $opening = RegistrationOpening::where('study_program_id', $program->id)->firstOrFail();
        $this->assertSame('0.00', $opening->registration_fee);
        $this->assertSame($published->id, $service->current($tbu->id)->id);
    }

    public function test_existing_test_gets_a_closed_session_without_rescheduling_it_on_repeat(): void
    {
        $unit = Unit::create(['code' => 'SMA', 'name' => 'SMA', 'is_active' => true]);
        $test = AdmissionTest::create(['unit_id' => $unit->id, 'name' => 'Tes Akademik', 'is_active' => true, 'is_required' => true, 'scheduled_at' => now()->addDays(10), 'location' => 'Ruang A']);
        $this->seed([UnitRegistrationConfigurationSeeder::class, TestSessionSeeder::class]);
        $session = TestSession::firstOrFail();
        $this->assertSame('closed', $session->status);
        $this->assertTrue($session->starts_at->equalTo($test->scheduled_at));
        $this->assertTrue($session->booking_closes_at->equalTo($session->starts_at->copy()->subDay()));
        $session->update(['capacity' => 25, 'location' => 'Ruang B', 'status' => 'active']);
        $this->travel(2)->days();
        $this->seed(TestSessionSeeder::class);
        $this->assertSame(1, TestSession::count());
        $this->assertSame(25, $session->fresh()->capacity);
        $this->assertSame('Ruang B', $session->fresh()->location);
        $this->assertTrue(app(UnitConfigurationService::class)->current($unit->id)->tests_enabled);
    }

    public function test_receipt_backfill_preserves_snapshot_and_does_not_notify_or_change_registration_stage(): void
    {
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
        $unit = Unit::where('code', 'SD')->firstOrFail();
        $registration = Registration::create(['unit_id' => $unit->id, 'user_id' => User::factory()->create()->id, 'registration_opening_id' => RegistrationOpening::where('unit_id', $unit->id)->firstOrFail()->id, 'full_name' => 'Peserta Lama', 'nik' => '3273010101010001', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2020-01-01', 'home_address' => 'Bandung', 'current_stage' => 'applicant_card']);
        $verified = Payment::create(['registration_id' => $registration->id, 'amount' => 385000, 'status' => 'verified', 'verified_at' => now()]);
        Payment::create(['registration_id' => $registration->id, 'amount' => 385000, 'status' => 'pending']);
        Notification::fake();
        $this->seed(PaymentReceiptSeeder::class);
        $verified->update(['amount' => 400000]);
        $this->seed(PaymentReceiptSeeder::class);
        $this->assertSame(1, PaymentReceipt::count());
        $this->assertSame('385000.00', PaymentReceipt::firstOrFail()->details['amount']);
        $this->assertSame('applicant_card', $registration->fresh()->current_stage);
        Notification::assertNothingSent();
    }
}
