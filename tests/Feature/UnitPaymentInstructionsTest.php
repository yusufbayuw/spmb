<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\UnitPaymentInstructions;
use App\Filament\Applicant\Pages\PaymentUpload;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\UnitPaymentInstruction;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitPaymentInstructionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tu_can_save_rich_instructions_only_for_own_unit_and_sanitizes_html(): void
    {
        $this->seed(ShieldSeeder::class);
        $own = Unit::create(['name' => 'Unit Sendiri', 'code' => 'PAY-OWN', 'is_active' => true]);
        $other = Unit::create(['name' => 'Unit Lain', 'code' => 'PAY-OTHER', 'is_active' => true]);
        $staff = $this->user('tu', $own);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(UnitPaymentInstructions::canAccess());
        $this->assertSame([$own->uuid => $own->name], app(UnitPaymentInstructions::class)->units());

        Livewire::test(UnitPaymentInstructions::class)
            ->fillForm(['content' => '<h2>Transfer</h2><p><strong>Perhatikan nomor rekening.</strong></p><script>alert(1)</script><a href="javascript:alert(1)">Berbahaya</a>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = UnitPaymentInstruction::query()->where('unit_id', $own->id)->firstOrFail();
        $this->assertStringContainsString('<h2>Transfer</h2>', $saved->content);
        $this->assertStringContainsString('<strong>Perhatikan nomor rekening.</strong>', $saved->content);
        $this->assertStringNotContainsString('<script', $saved->content);
        $this->assertStringNotContainsString('javascript:', $saved->content);
        $this->assertDatabaseMissing('unit_payment_instructions', ['unit_id' => $other->id]);

        // Livewire's direct test call propagates ModelNotFoundException.
        // In an HTTP request Laravel renders the same exception as a 404.
        foreach (['loadUnit', 'save'] as $action) {
            try {
                Livewire::test(UnitPaymentInstructions::class)
                    ->set('unitUuid', $other->uuid)
                    ->call($action);

                $this->fail("Cross-unit {$action} must be rejected.");
            } catch (ModelNotFoundException $exception) {
                $this->assertSame(Unit::class, $exception->getModel());
            }
        }

        $this->assertDatabaseMissing('unit_payment_instructions', ['unit_id' => $other->id]);
    }

    public function test_admin_unit_can_edit_own_unit_but_applicant_cannot(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'Unit Pembayaran', 'code' => 'PAY-ADMIN', 'is_active' => true]);
        $adminUnit = $this->user('admin_unit', $unit);
        $this->actingAs($adminUnit);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitPaymentInstructions::class)
            ->fillForm(['content' => '<p>Transfer bank.</p>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('<p>Transfer bank.</p>', UnitPaymentInstruction::where('unit_id', $unit->id)->value('content'));

        $this->actingAs($this->user('pendaftar'));
        $this->assertFalse(UnitPaymentInstructions::canAccess());
    }

    public function test_applicant_sees_only_own_unit_formatted_instructions_without_changing_payment(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'Unit Tujuan', 'code' => 'PAY-APPLICANT', 'is_active' => true]);
        $other = Unit::create(['name' => 'Unit Lain', 'code' => 'PAY-FOREIGN', 'is_active' => true]);
        $applicant = $this->user('pendaftar');
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 350000,
            'status' => 'open',
        ]);
        $registration = Registration::create([
            'user_id' => $applicant->id, 'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'full_name' => 'Calon Peserta', 'nik' => '3273010101010922',
            'gender' => 'L', 'birth_place' => 'Bandung',
            'birth_date' => '2015-01-01', 'home_address' => 'Bandung',
            'status' => 'payment_pending', 'current_stage' => 'payment',
            'lifecycle_status' => 'active',
        ]);
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'amount' => 350000, 'va_number' => '1234567890', 'status' => 'pending',
        ]);
        UnitPaymentInstruction::create([
            'unit_id' => $unit->id,
            'content' => '<h2>Petunjuk Transfer</h2><ol><li>Gunakan transfer bank.</li></ol>',
        ]);
        UnitPaymentInstruction::create([
            'unit_id' => $other->id,
            'content' => '<p>Rahasia unit lain</p>',
        ]);

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(PaymentUpload::class, ['registration' => $registration->uuid])
            ->assertSuccessful()
            ->assertSee('Petunjuk Transfer')
            ->assertSee('<h2>Petunjuk Transfer</h2>', escape: false)
            ->assertSee('<ol>', escape: false)
            ->assertDontSee('Rahasia unit lain')
            ->assertSee('1234567890');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('payment', $registration->fresh()->current_stage);
    }

    public function test_empty_instructions_are_optional_and_admin_can_clear_them(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'Unit Kosong', 'code' => 'PAY-EMPTY', 'is_active' => true]);
        $this->actingAs($this->user('tu', $unit));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitPaymentInstructions::class)
            ->fillForm(['content' => ''])
            ->call('save')->assertHasNoFormErrors();

        $this->assertNull(UnitPaymentInstruction::where('unit_id', $unit->id)->value('content'));
    }

    public function test_registration_admin_table_persists_sort_in_session_without_changing_default_order(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/RegistrationResource.php'));

        $this->assertStringContainsString("->defaultSort('created_at', 'desc')", $source);
        $this->assertStringContainsString('->persistSortInSession()', $source);
    }

    private function user(string $role, ?Unit $unit = null): User
    {
        $user = User::factory()->create([
            'role' => $role === 'pendaftar' ? 'user' : $role,
            'unit_id' => $unit?->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
