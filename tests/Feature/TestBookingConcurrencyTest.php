<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\TestSession;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TestBookingConcurrencyTest extends TestCase
{
    public function test_two_concurrent_requests_cannot_take_the_same_last_seat(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'spmb-booking-');
        $barrier = $database.'.ready';
        $previous = config('database.default');
        config(['database.connections.booking_test' => ['driver' => 'sqlite', 'database' => $database, 'foreign_key_constraints' => true, 'busy_timeout' => 10000], 'database.default' => 'booking_test']);
        try {
            Artisan::call('migrate', ['--database' => 'booking_test', '--force' => true, '--no-interaction' => true]);
            $unit = Unit::create(['name' => 'Concurrent Test', 'code' => 'RACE', 'is_active' => true]);
            $user = User::factory()->create(['is_active' => true]);
            $opening = RegistrationOpening::create(['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Test', 'status' => 'open']);
            $test = AdmissionTest::create(['unit_id' => $unit->id, 'name' => 'Race', 'is_active' => true, 'is_required' => true]);
            $session = TestSession::create(['admission_test_id' => $test->id, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'booking_closes_at' => now()->addDays(2), 'location' => 'Test', 'capacity' => 1, 'status' => 'active']);
            $ids = [];
            foreach (['3273010101010001', '3273010101010002'] as $nik) {
                $ids[] = Registration::create(['user_id' => $user->id, 'unit_id' => $unit->id, 'registration_opening_id' => $opening->id, 'nik' => $nik, 'full_name' => 'Concurrent Test', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2010-01-01', 'home_address' => 'Bandung', 'current_stage' => 'tests'])->id;
            }
            $code = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'booking_test', 'database.connections.booking_test' => ['driver' => 'sqlite', 'database' => getenv('BOOKING_TEST_DB'), 'foreign_key_constraints' => true, 'busy_timeout' => 10000], 'queue.default' => 'sync', 'mail.default' => 'array']);
$registration = App\Models\Registration::findOrFail(getenv('BOOKING_TEST_REGISTRATION'));
$session = App\Models\TestSession::findOrFail(getenv('BOOKING_TEST_SESSION'));
$until = microtime(true) + 10;
while (! file_exists(getenv('BOOKING_TEST_BARRIER')) && microtime(true) < $until) { usleep(10000); }
try {
    app(App\Services\TestBookingService::class)->book($registration, $session, $registration->user);
    echo 'booked';
} catch (Illuminate\Validation\ValidationException $e) {
    echo 'full';
}
CODE;
            $processes = [];
            foreach ($ids as $id) {
                $process = new Process([PHP_BINARY, '-r', $code], base_path(), ['BOOKING_TEST_DB' => $database, 'BOOKING_TEST_REGISTRATION' => (string) $id, 'BOOKING_TEST_SESSION' => (string) $session->id, 'BOOKING_TEST_BARRIER' => $barrier]);
                $process->setTimeout(25);
                $process->start();
                $processes[] = $process;
            }
            touch($barrier);
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $outcomes[] = $process->getOutput();
            }
            sort($outcomes);
            $this->assertSame(['booked', 'full'], $outcomes);
            $this->assertSame(1, $session->bookings()->count());
        } finally {
            DB::purge('booking_test');
            config(['database.default' => $previous]);
            foreach ([$database, $database.'-wal', $database.'-shm', $barrier] as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }
}
