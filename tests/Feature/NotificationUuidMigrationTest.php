<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationUuidMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_notification_payloads_are_backfilled_to_uuid_references(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = Unit::where('code', 'SD')->firstOrFail();
        $user = User::factory()->create(['is_active' => true]);
        $opening = RegistrationOpening::where('unit_id', $unit->id)->firstOrFail();
        $registration = Registration::create(['user_id' => $user->id, 'unit_id' => $unit->id, 'registration_opening_id' => $opening->id, 'full_name' => 'Pendaftar', 'nik' => '3273010101010099', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2020-01-01', 'home_address' => 'Bandung']);
        $payment = Payment::create(['registration_id' => $registration->id, 'amount' => 100000, 'status' => 'pending']);
        $announcement = Announcement::create(['registration_id' => $registration->id, 'status' => 'draft']);
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'legacy',
            'data' => [
                'registration_id' => $registration->id,
                'unit_id' => $unit->id,
                'metadata' => ['payment_id' => $payment->id, 'announcement_id' => $announcement->id, 'changed_by' => $user->id],
            ],
        ]);

        $migration = require database_path('migrations/2026_09_05_220051_migrate_numeric_notification_references_to_uuids.php');
        $migration->up();

        $data = $notification->fresh()->data;
        $this->assertSame($registration->uuid, $data['registration_uuid']);
        $this->assertSame($unit->uuid, $data['unit_uuid']);
        $this->assertSame($payment->uuid, $data['metadata']['payment_uuid']);
        $this->assertSame($announcement->uuid, $data['metadata']['announcement_uuid']);
        $this->assertSame($user->uuid, $data['metadata']['changed_by_uuid']);
        $this->assertArrayNotHasKey('registration_id', $data);
        $this->assertArrayNotHasKey('unit_id', $data);
        $this->assertArrayNotHasKey('payment_id', $data['metadata']);
        $this->assertArrayNotHasKey('announcement_id', $data['metadata']);
        $this->assertArrayNotHasKey('changed_by', $data['metadata']);

        $migration->up();
        $this->assertSame($registration->uuid, $notification->fresh()->data['registration_uuid']);
    }
}
