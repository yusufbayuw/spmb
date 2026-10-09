<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\RegistrationWorkflowService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseOneAuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_staff_cannot_view_foreign_registration_or_payment_by_record_policy(): void
    {
        $own = Unit::create(['name' => 'Own School', 'code' => 'OWN-SEC', 'is_active' => true]);
        $foreign = Unit::create(['name' => 'Foreign School', 'code' => 'FOR-SEC', 'is_active' => true]);

        $role = Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
        foreach (['view_registration', 'view_payment', 'update_registration', 'update_payment'] as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        $staff = User::factory()->create(['unit_id' => $own->id, 'role' => 'tu', 'is_active' => true]);
        $staff->assignRole($role);
        $applicant = User::factory()->create();
        $ownRegistration = $this->registration($own, $applicant, '3273010101010001');
        $foreignRegistration = $this->registration($foreign, $applicant, '3273010101010002');
        $payment = Payment::create([
            'registration_id' => $foreignRegistration->id,
            'status' => 'pending',
            'va_number' => 'TEST-FOREIGN',
            'amount' => 100000,
        ]);

        $this->assertTrue(Gate::forUser($staff)->allows('view', $ownRegistration));
        $this->assertFalse(Gate::forUser($staff)->allows('view', $foreignRegistration));
        $this->assertFalse(Gate::forUser($staff)->allows('view', $payment));
        $this->assertFalse(Gate::forUser($staff)->allows('update', $foreignRegistration));
        $this->assertFalse(Gate::forUser($staff)->allows('update', $payment));
    }

    public function test_tu_cannot_configure_unit_even_when_it_is_own_unit(): void
    {
        $own = Unit::create(['name' => 'Unit 1', 'code' => 'UNIT-1', 'is_active' => true]);
        $foreign = Unit::create(['name' => 'Unit 2', 'code' => 'UNIT-2', 'is_active' => true]);
        $tuRole = Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
        $tuRole->givePermissionTo(Permission::findOrCreate('record_result_admissiontestresult', 'web'));
        Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);

        $tu = User::factory()->create(['unit_id' => $own->id, 'role' => 'tu', 'is_active' => true]);
        $tu->assignRole('tu');
        $admin = User::factory()->create(['unit_id' => $own->id, 'role' => 'admin_unit', 'is_active' => true]);
        $admin->assignRole('admin_unit');

        $this->assertFalse(Gate::forUser($tu)->allows('configureRegistration', $own));
        $this->assertTrue(Gate::forUser($tu)->allows('manageTestOperations', $own));
        $this->assertFalse(Gate::forUser($tu)->allows('manageTestOperations', $foreign));
        $this->assertFalse(Gate::forUser($tu)->allows('configureRegistration', $foreign));
        $this->assertTrue(Gate::forUser($admin)->allows('configureRegistration', $own));
        $this->assertFalse(Gate::forUser($admin)->allows('configureRegistration', $foreign));
    }

    public function test_payment_verification_service_rejects_cross_unit_staff(): void
    {
        $own = Unit::create(['name' => 'Verifier Unit', 'code' => 'VFY-OWN', 'is_active' => true]);
        $foreign = Unit::create(['name' => 'Payment Unit', 'code' => 'VFY-OTH', 'is_active' => true]);
        $role = Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('verify_payment_payment', 'web'));
        $staff = User::factory()->create(['unit_id' => $own->id, 'role' => 'tu', 'is_active' => true]);
        $staff->assignRole($role);
        $registration = $this->registration($foreign, User::factory()->create(), '3273010101010091');
        $registration->update(['current_stage' => 'payment_verification', 'status' => 'payment_uploaded']);
        $payment = Payment::create(['registration_id' => $registration->id, 'status' => 'paid', 'amount' => 100000]);

        try {
            app(RegistrationWorkflowService::class)->verifyPayment($payment, $staff, true);
            $this->fail('Cross-unit payment verification must be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_payment_verification_service_rejects_replayed_verification(): void
    {
        $unit = Unit::create(['name' => 'Verifier Unit', 'code' => 'VFY-RPL', 'is_active' => true]);
        $role = Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('verify_payment_payment', 'web'));
        $staff = User::factory()->create(['unit_id' => $unit->id, 'role' => 'tu', 'is_active' => true]);
        $staff->assignRole($role);
        $registration = $this->registration($unit, User::factory()->create(), '3273010101010092');
        $registration->update(['current_stage' => 'payment_verification', 'status' => 'payment_uploaded']);
        $payment = Payment::create(['registration_id' => $registration->id, 'status' => 'verified', 'amount' => 100000]);

        try {
            app(RegistrationWorkflowService::class)->verifyPayment($payment, $staff, true);
            $this->fail('Replayed payment verification must be denied.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $this->assertSame('verified', $payment->fresh()->status);
    }

    private function registration(Unit $unit, User $applicant, string $nik): Registration
    {
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 100000,
            'status' => 'open',
        ]);

        return Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'nik' => $nik,
            'full_name' => 'Testing Candidate',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2018-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
        ]);
    }
}
