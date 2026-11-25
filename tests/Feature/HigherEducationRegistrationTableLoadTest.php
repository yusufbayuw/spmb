<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Services\HigherEducationRegistrationTableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HigherEducationRegistrationTableLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_typed_json_filter_and_paginated_sort_on_ten_thousand_synthetic_rows(): void
    {
        if (getenv('SPMB_REGISTRATION_TABLE_LOAD_TEST') !== '1'
            || ! app()->environment('testing')
            || DB::getDriverName() !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'spmb_test') {
            $this->markTestSkipped('Synthetic registration load check runs only on isolated MySQL CI.');
        }

        $schema = DB::connection()->getSchemaBuilder();
        $schema->create('registration_filter_load_fixture', function ($table) {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->json('custom_answers')->nullable();
        });

        try {
            $batch = [];
            for ($id = 1; $id <= 10000; $id++) {
                $batch[] = [
                    'id' => $id,
                    'unit_id' => $id <= 8000 ? 1 : 2,
                    'custom_answers' => json_encode([
                        'score' => (string) ($id % 100),
                        'referral' => $id % 2 === 0 ? 'Website' : 'Teman',
                        'choice' => ['A', 'B'],
                        'notes' => str_repeat('Synthetic payload ', 25),
                    ], JSON_THROW_ON_ERROR),
                ];
                if (count($batch) === 500) {
                    DB::table('registration_filter_load_fixture')->insert($batch);
                    $batch = [];
                }
            }

            $service = app(HigherEducationRegistrationTableService::class);
            $times = [];
            $count = 0;
            for ($run = 0; $run < 7; $run++) {
                $query = Registration::query()->from('registration_filter_load_fixture as registrations')->withoutEagerLoads();
                $start = hrtime(true);
                $service->filterCustom($query, 1, 'score', 'number', ['from' => '50']);
                $service->filterCustom($query, 1, 'referral', 'text', ['value' => 'Website', 'operator' => 'equals']);
                $service->filterCustom($query, 1, 'choice', 'multiselect', ['values' => ['A', 'B'], 'match' => 'all']);
                $count = (clone $query)->count();
                $page = $service->sortCustom($query, 1, 'score', 'number', 'desc')
                    ->orderBy('registrations.id')->limit(25)->offset(25)->pluck('registrations.id')->all();
                $times[] = round((hrtime(true) - $start) / 1_000_000, 2);

                $this->assertSame(2000, $count);
                $this->assertCount(25, $page);
                // 80 rows have score 98; the second page starts at its 26th row.
                $this->assertSame(range(2598, 4998, 100), array_map('intval', $page));
            }

            sort($times);
            $report = [
                'fixture' => 'synthetic_isolated_mysql',
                'rows' => 10000,
                'selected_unit_rows' => 8000,
                'matching_rows' => $count,
                'page_size' => 25,
                'runs' => count($times),
                'median_ms' => $times[3],
                'max_ms' => max($times),
                'php_peak_memory_bytes' => memory_get_peak_usage(true),
                'checks_passed' => true,
                'note' => 'Count plus second-page database query only; no concurrent browser traffic, real production records, or ORM rendering.',
            ];
            // Generous CI smoke-test guard, not a production latency SLA.
            $this->assertLessThan(5000, max($times));
            $this->assertLessThan(512 * 1024 * 1024, $report['php_peak_memory_bytes']);
            file_put_contents(base_path('registration-table-load-synthetic.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            fwrite(STDOUT, PHP_EOL.json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL);
        } finally {
            DB::statement('DROP TEMPORARY TABLE IF EXISTS registration_filter_load_fixture');
        }
    }
}
