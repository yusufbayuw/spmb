<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\ApplicantFileStorage;
use App\Services\ControlledDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ForcedTestRegistrationDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_remove_attested_test_record_with_verified_payment_receipt_and_tests(): void
    {
        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);
        Storage::fake(ApplicantFileStorage::LEGACY_PUBLIC_DISK);

        [$registration, $unit, $admin] = $this->makeRegistration();
        $registration->update([
            'status' => 'accepted',
            'current_stage' => 're_registration',
            'payment_verified_at' => now(),
            'accepted_at' => now(),
            'registration_number' => 'TEST-2026-0001',
        ]);

        $va = VirtualAccount::create([
            'unit_id' => $unit->id, 'bank' => 'TEST-BANK',
            'va_number' => 'TEST-PAID-VA', 'status' => 'paid',
            'registration_id' => $registration->id, 'assigned_at' => now(),
        ]);

        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put('payment/test-proof.png', 'dummy');
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put('re-registration/test-doc.pdf', 'dummy');
        $payment = Payment::create([
            'registration_id' => $registration->id, 'virtual_account_id' => $va->id,
            'status' => 'verified', 'amount' => 805000,
            'verified_at' => now(), 'proof_path' => 'payment/test-proof.png',
        ]);
        PaymentReceipt::create([
            'payment_id' => $payment->id, 'number' => 'TEST-REC-001',
            'details' => ['purpose' => 'synthetic'], 'issued_at' => now(),
        ]);

        $testId = DB::table('admission_tests')->insertGetId([
            'unit_id' => $unit->id, 'name' => 'Tes Uji',
        ]);
        DB::table('test_bookings')->insert([
            'registration_id' => $registration->id,
            'admission_test_id' => $testId,
        ]);
        DB::table('admission_test_results')->insert([
            'registration_id' => $registration->id,
            'admission_test_id' => $testId,
        ]);
        DB::table('re_registration_items')->insert([
            'uuid' => (string) Str::uuid(),
            'registration_id' => $registration->id,
            'requirement_key' => 'test_doc', 'label' => 'Berkas Uji',
            'file_path' => 're-registration/test-doc.pdf',
        ]);
        DB::table('admission_offers')->insert([
            'uuid' => (string) Str::uuid(),
            'registration_id' => $registration->id,
            'offered_at' => now(), 'expires_at' => now()->addDays(2),
        ]);

        $userId = $registration->user_id;
        $result = app(ControlledDeletionService::class)->deleteTestRegistration(
            $registration, $admin, 'Data sintetis telah diperiksa', 'HAPUS DATA UJI', true
        );

        $this->assertSame(2, $result['file_count']);
        $this->assertSame(0, $result['file_errors']);
        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('payment_receipts', ['payment_id' => $payment->id]);
        $this->assertDatabaseMissing('test_bookings', ['registration_id' => $registration->id]);
        $this->assertDatabaseMissing('admission_test_results', ['registration_id' => $registration->id]);
        $this->assertDatabaseMissing('admission_offers', ['registration_id' => $registration->id]);
        $this->assertDatabaseMissing('re_registration_items', ['registration_id' => $registration->id]);
        $this->assertDatabaseHas('users', ['id' => $userId]);
        $this->assertDatabaseHas('virtual_accounts', [
            'id' => $va->id, 'status' => 'paid', 'registration_id' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'registration.test_data_forced_deleted', 'user_id' => $admin->id,
        ]);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertMissing('payment/test-proof.png');
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertMissing('re-registration/test-doc.pdf');
    }

    public function test_admin_unit_cannot_force_delete_even_when_normal_permission_enabled(): void
    {
        [$registration, $unit] = $this->makeRegistration();
        Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);
        $unit->update(['allow_admin_unit_registration_deletion' => true]);
        $staff = User::factory()->create([
            'role' => 'admin_unit', 'unit_id' => $unit->id, 'is_active' => true,
        ]);
        $staff->assignRole('admin_unit');
        Payment::create(['registration_id' => $registration->id, 'status' => 'verified', 'amount' => 100]);

        try {
            app(ControlledDeletionService::class)->deleteTestRegistration(
                $registration, $staff, 'Menghapus data simulasi', 'HAPUS DATA UJI', true
            );
            $this->fail('Unit admin must not force-delete any record.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseHas('payments', ['registration_id' => $registration->id]);
    }

    public function test_super_admin_requires_explicit_attestation_and_special_phrase(): void
    {
        [$registration, , $admin] = $this->makeRegistration();

        foreach ([
            ['Alasan untuk data dummy', 'HAPUS DATA UJI', false],
            ['Alasan untuk data dummy', 'HAPUS', true],
            ['Singkat', 'HAPUS DATA UJI', true],
        ] as [$reason, $phrase, $attested]) {
            try {
                app(ControlledDeletionService::class)->deleteTestRegistration(
                    $registration, $admin, $reason, $phrase, $attested
                );
                $this->fail('Should require full privileged confirmation.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('delete', $e->errors());
            }
            $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
        }
    }

    public function test_normal_delete_remains_protected_for_verified_payment(): void
    {
        [$registration, , $admin] = $this->makeRegistration();
        Payment::create(['registration_id' => $registration->id, 'status' => 'verified', 'amount' => 100]);

        try {
            app(ControlledDeletionService::class)->deleteRegistration(
                $registration, $admin, 'Alasan data uji', 'HAPUS'
            );
            $this->fail('Verified payment cannot be removed by normal delete.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delete', $e->errors());
        }
        $this->assertDatabaseHas('payments', ['registration_id' => $registration->id]);
    }

    public function test_other_registration_payment_reference_blocks_forced_deletion(): void
    {
        [$registration, $unit, $admin] = $this->makeRegistration();
        $other = $this->registrationFor($unit);
        $va = VirtualAccount::create([
            'unit_id' => $unit->id, 'bank' => 'BANK', 'va_number' => 'VA-CROSS',
            'status' => 'assigned', 'registration_id' => $registration->id,
        ]);
        Payment::create([
            'registration_id' => $other->id, 'virtual_account_id' => $va->id,
            'status' => 'verified', 'amount' => 100,
        ]);

        try {
            app(ControlledDeletionService::class)->deleteTestRegistration(
                $registration, $admin, 'Bersihkan data uji saja', 'HAPUS DATA UJI', true
            );
            $this->fail('Cross-registration payment must not be deleted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delete', $e->errors());
        }
        $this->assertDatabaseHas('virtual_accounts', ['id' => $va->id, 'registration_id' => $registration->id]);
        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    private function makeRegistration(): array
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $unit = Unit::create(['name' => 'Unit Dummy', 'code' => 'UNIT-TESTDEL', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $admin->assignRole('super_admin');

        return [$this->registrationFor($unit), $unit, $admin];
    }

    private function registrationFor(Unit $unit): Registration
    {
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2026/2027',
            'wave' => 'Gelombang '.Str::random(5), 'registration_fee' => 0, 'status' => 'draft',
        ]);

        return Registration::create([
            'user_id' => User::factory()->create()->id,
            'unit_id' => $unit->id, 'registration_opening_id' => $opening->id,
            'nik' => '32730101010'.random_int(10000, 99999),
            'full_name' => 'Synthetic Applicant', 'gender' => 'L',
            'birth_place' => 'Bandung', 'birth_date' => '2018-01-01',
            'home_address' => 'Bandung', 'status' => 'submitted',
            'current_stage' => 'data_validation',
        ]);
    }
}
