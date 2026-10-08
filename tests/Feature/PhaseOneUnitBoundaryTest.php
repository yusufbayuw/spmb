<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Services\UnitRecordAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneUnitBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_staff_cannot_access_foreign_records(): void
    {
        $one = Unit::create(['name' => 'One', 'code' => 'ONE', 'is_active' => true]);
        $two = Unit::create(['name' => 'Two', 'code' => 'TWO', 'is_active' => true]);
        $staff = User::factory()->create(['unit_id' => $one->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');
        $access = app(UnitRecordAccess::class);

        $this->assertTrue($access->allows($staff, $one));
        $this->assertFalse($access->allows($staff, $two));
        $this->assertFalse($access->allows($staff, new Payment));
    }
}
