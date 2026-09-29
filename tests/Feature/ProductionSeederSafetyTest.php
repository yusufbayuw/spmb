<?php

namespace Tests\Feature;

use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeding_does_not_create_or_modify_operational_demo_data(): void
    {
        $unit = Unit::create([
            'name' => 'Unit Existing',
            'code' => 'EXIST',
            'institution_type' => 'school',
            'description' => 'Data operasional yang harus dipertahankan.',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Operator Existing',
            'email' => 'operator-existing@example.test',
            'is_active' => true,
        ]);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Existing',
            'registration_fee' => 123456,
            'status' => 'draft',
        ]);

        $originalEnvironment = app()->environment();

        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertExitCode(0);
            $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true])->assertExitCode(0);
            $this->artisan('db:seed', ['--class' => UnitSeeder::class, '--force' => true])->assertExitCode(0);
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }

        $this->assertSame(1, Unit::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, RegistrationOpening::query()->count());

        $this->assertSame('Unit Existing', $unit->fresh()->name);
        $this->assertSame('Data operasional yang harus dipertahankan.', $unit->fresh()->description);
        $this->assertSame('Operator Existing', $user->fresh()->name);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame('123456.00', $opening->fresh()->registration_fee);
        $this->assertSame('draft', $opening->fresh()->status);

        $this->assertDatabaseMissing('units', ['code' => 'DC']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
        $this->assertDatabaseHas('roles', ['name' => 'super_admin']);
    }
}
