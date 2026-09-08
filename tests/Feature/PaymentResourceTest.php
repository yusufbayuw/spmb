<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tu_can_open_payment_without_registration_number(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'Sekolah Menengah',
            'code' => 'SMA-PAY',
            'is_active' => true,
        ]);
        $tu = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $tu->assignRole('tu');
        $parent = User::factory()->create(['is_active' => true]);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 350000,
            'status' => 'open',
        ]);
        $registration = Registration::create([
            'user_id' => $parent->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_number' => null,
            'full_name' => 'Jajang Miharjang',
            'nik' => '3273010101010901',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2015-01-01',
            'home_address' => 'Bandung',
            'status' => 'payment_uploaded',
            'current_stage' => 'payment_verification',
            'lifecycle_status' => 'active',
        ]);
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'amount' => 350000,
            'status' => 'paid',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($tu)
            ->get(PaymentResource::getUrl('edit', ['record' => $payment]))
            ->assertOk()
            ->assertSeeText('Nomor registrasi belum diterbitkan · Jajang Miharjang');
    }
}
