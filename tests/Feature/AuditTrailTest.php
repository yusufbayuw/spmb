<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Registration;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApplicantFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);
        Storage::fake(ApplicantFileStorage::LEGACY_PUBLIC_DISK);
    }

    public function test_sensitive_model_update_records_actor_context_and_diff(): void
    {
        $unit = Unit::create(['name' => 'SMA', 'code' => 'SMA', 'is_active' => true]);
        $staff = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $registration = $this->registration($unit);

        DB::table('audit_logs')->delete();

        $this->actingAs($staff);

        $registration->update([
            'data_validation_status' => 'revision',
            'data_validation_notes' => 'Perbaiki data',
        ]);

        $log = AuditLog::query()
            ->where('event', 'registration.updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame($unit->id, $log->unit_id);
        $this->assertSame($registration->id, $log->registration_id);
        $this->assertSame('pending', $log->old_values['data_validation_status']);
        $this->assertSame('revision', $log->new_values['data_validation_status']);
        $this->assertNotNull($log->request_id);
    }

    public function test_password_values_are_redacted_from_audit_log(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        DB::table('audit_logs')->delete();

        $this->actingAs($user);
        $user->update(['password' => Hash::make('new-secret-password')]);

        $log = AuditLog::query()
            ->where('event', 'user.updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('[REDACTED]', $log->old_values['password']);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertStringNotContainsString('new-secret-password', json_encode($log->new_values));
    }

    public function test_audit_log_cannot_be_updated_or_deleted_through_model(): void
    {
        $unit = Unit::create(['name' => 'SMP', 'code' => 'SMP', 'is_active' => true]);
        $log = AuditLog::query()->where('event', 'unit.created')->latest('id')->firstOrFail();

        try {
            $log->update(['description' => 'tampered']);
            $this->fail('Audit log update must be blocked.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_authorized_private_file_preview_is_audited(): void
    {
        $unit = Unit::create(['name' => 'SD', 'code' => 'SD', 'is_active' => true]);
        $owner = $this->applicant();
        $registration = $this->registration($unit, $owner);
        $path = 'documents/'.$registration->id.'/kk.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, '%PDF-1.4 private');

        $document = Document::create([
            'registration_id' => $registration->id,
            'type' => 'family_card',
            'file_path' => $path,
            'original_name' => 'kk.pdf',
            'file_type' => 'pdf',
            'file_size' => 16,
        ]);

        DB::table('audit_logs')->delete();

        $this->actingAs($owner)
            ->get(route('files.applicant.documents.show', $document))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document.viewed',
            'user_id' => $owner->id,
            'unit_id' => $unit->id,
            'registration_id' => $registration->id,
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
        ]);
    }

    public function test_tu_audit_resource_is_scoped_to_own_unit(): void
    {
        $unitA = Unit::create(['name' => 'SMA', 'code' => 'SMA-A', 'is_active' => true]);
        $unitB = Unit::create(['name' => 'SMK', 'code' => 'SMK-B', 'is_active' => true]);

        $tuRole = Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
        foreach (['view_auditlog', 'view_any_auditlog'] as $name) {
            $tuRole->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $tu = User::factory()->create(['unit_id' => $unitA->id, 'is_active' => true]);
        $tu->assignRole($tuRole);

        DB::table('audit_logs')->delete();

        AuditLog::query()->create([
            'unit_id' => $unitA->id,
            'event' => 'test.unit_a',
            'created_at' => now(),
        ]);
        AuditLog::query()->create([
            'unit_id' => $unitB->id,
            'event' => 'test.unit_b',
            'created_at' => now(),
        ]);

        $this->actingAs($tu);

        $events = AuditLogResource::getEloquentQuery()->pluck('event')->all();

        $this->assertContains('test.unit_a', $events);
        $this->assertNotContains('test.unit_b', $events);
    }

    private function registration(Unit $unit, ?User $owner = null): Registration
    {
        $owner ??= User::factory()->create(['is_active' => true]);

        return Registration::create([
            'user_id' => $owner->id,
            'unit_id' => $unit->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => (string) random_int(1000000000000000, 9999999999999999),
            'full_name' => 'Calon Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2019-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
        ]);
    }

    private function applicant(): User
    {
        $role = Role::firstOrCreate(['name' => 'pendaftar', 'guard_name' => 'web']);
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
