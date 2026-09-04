<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\SpmbDatabaseNotification;
use App\Services\RegistrationWorkflowService;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilamentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_filament_panels_enable_database_notifications_with_polling(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));

        $admin = Filament::getPanel('admin');
        $applicant = Filament::getPanel('pendaftar');

        $this->assertTrue($admin->hasDatabaseNotifications());
        $this->assertTrue($applicant->hasDatabaseNotifications());
        $this->assertSame('15s', $admin->getDatabaseNotificationsPollingInterval());
        $this->assertSame('15s', $applicant->getDatabaseNotificationsPollingInterval());
    }

    public function test_notification_payload_is_queueable_filament_native_and_action_marks_read(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $notification = new SpmbDatabaseNotification(
            event: 'test.event',
            category: 'workflow',
            title: 'Judul notifikasi',
            body: 'Isi notifikasi',
            status: 'success',
            icon: 'heroicon-o-check-circle',
            actionLabel: 'Buka',
            actionUrl: url('/pendaftar'),
            registrationId: 10,
            unitId: 2,
            metadata: ['source' => 'test'],
        );

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(['database'], $notification->via($user));
        $this->assertSame('notifications', $notification->viaQueues()['database']);
        $this->assertSame([30, 120, 300, 900], $notification->backoff());

        $data = $notification->toDatabase($user);
        $this->assertSame('test.event', $data['spmb_event']);
        $this->assertSame('workflow', $data['category']);
        $this->assertSame(10, $data['registration_id']);
        $this->assertSame(2, $data['unit_id']);
        $this->assertSame('Judul notifikasi', $data['title']);
        $this->assertTrue($data['actions'][0]['shouldMarkAsRead']);
        $this->assertStringContainsString('/pendaftar', $data['actions'][0]['url']);

        $stored = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => SpmbDatabaseNotification::class,
            'data' => $data,
        ]);

        $this->assertNull($stored->read_at);
        $stored->markAsRead();
        $this->assertNotNull($stored->fresh()->read_at);
        $stored->markAsUnread();
        $this->assertNull($stored->fresh()->read_at);
    }

    public function test_new_registration_notifies_owner_same_unit_tu_and_super_admin_only(): void
    {
        NotificationFacade::fake();

        $unitA = $this->unit('SMA');
        $unitB = $this->unit('SMP');
        $applicant = $this->userWithRole('pendaftar');
        $tuA = $this->userWithRole('tu', ['unit_id' => $unitA->id, 'role' => 'tu']);
        $tuB = $this->userWithRole('tu', ['unit_id' => $unitB->id, 'role' => 'tu']);
        $admin = $this->userWithRole('super_admin', ['role' => 'super_admin']);
        $opening = $this->opening($unitA);

        $registration = $this->registration($applicant, $unitA, $opening, '3273010101019911');

        NotificationFacade::assertSentTo(
            $applicant,
            SpmbDatabaseNotification::class,
            fn (SpmbDatabaseNotification $notification): bool => $notification->event === 'registration.submitted'
                && $notification->registrationId === $registration->id,
        );

        foreach ([$tuA, $admin] as $staff) {
            NotificationFacade::assertSentTo(
                $staff,
                SpmbDatabaseNotification::class,
                fn (SpmbDatabaseNotification $notification): bool => $notification->event === 'registration.submitted_staff'
                    && $notification->unitId === $unitA->id,
            );
        }

        NotificationFacade::assertNotSentTo($tuB, SpmbDatabaseNotification::class);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'notification.queued',
            'registration_id' => $registration->id,
            'unit_id' => $unitA->id,
        ]);
    }

    public function test_revision_and_lifecycle_events_generate_contextual_applicant_notifications(): void
    {
        NotificationFacade::fake();

        $unit = $this->unit('SMA');
        $applicant = $this->userWithRole('pendaftar');
        $staff = $this->userWithRole('tu', ['unit_id' => $unit->id, 'role' => 'tu']);
        $opening = $this->opening($unit);
        $registration = $this->registration($applicant, $unit, $opening, '3273010101019912');

        app(RegistrationWorkflowService::class)->validateData(
            $registration,
            $staff,
            false,
            'Alamat harus disesuaikan dengan dokumen resmi.',
        );

        NotificationFacade::assertSentTo(
            $applicant,
            SpmbDatabaseNotification::class,
            fn (SpmbDatabaseNotification $notification): bool => $notification->event === 'registration.data_revision_required'
                && str_contains((string) $notification->body, 'Alamat harus disesuaikan'),
        );

        $registration->fresh()->changeLifecycle('withdrawn', $applicant, 'Tidak melanjutkan pendaftaran.');

        NotificationFacade::assertSentTo(
            $applicant,
            SpmbDatabaseNotification::class,
            fn (SpmbDatabaseNotification $notification): bool => $notification->event === 'registration.lifecycle_changed'
                && ($notification->metadata['lifecycle_status'] ?? null) === 'withdrawn',
        );

        NotificationFacade::assertSentTo(
            $staff,
            SpmbDatabaseNotification::class,
            fn (SpmbDatabaseNotification $notification): bool => $notification->event === 'registration.lifecycle_changed_staff',
        );
    }

    private function unit(string $code): Unit
    {
        return Unit::create([
            'name' => $code.' Taruna Bakti',
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function opening(Unit $unit): RegistrationOpening
    {
        return RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 350000,
            'status' => 'open',
        ]);
    }

    private function registration(User $applicant, Unit $unit, RegistrationOpening $opening, string $nik): Registration
    {
        return Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => $nik,
            'full_name' => 'Calon Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2015-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
        ]);
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create($attributes + ['is_active' => true, 'role' => 'user']);
        $user->assignRole($role);

        return $user;
    }
}
