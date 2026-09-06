<?php

namespace Database\Seeders;

use App\Models\AdmissionTest;
use App\Models\TestSession;
use Illuminate\Database\Seeder;

class TestSessionSeeder extends Seeder
{
    public function run(): void
    {
        AdmissionTest::query()->where('is_active', true)->each(function (AdmissionTest $test): void {
            if (TestSession::where('admission_test_id', $test->id)->exists()) {
                return;
            }

            $startsAt = $test->scheduled_at ?? now()->addWeek()->setTime(9, 0);
            TestSession::create([
                'admission_test_id' => $test->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHour(),
                'booking_closes_at' => $startsAt->copy()->subDay(),
                'location' => $test->location ?: 'Lokasi belum ditentukan oleh TU',
                'capacity' => 1,
                'status' => 'closed',
                'instructions' => 'Sesi awal. TU perlu memeriksa waktu, durasi, lokasi, dan kuota sebelum membuka pemesanan.',
            ]);
        });
    }
}
