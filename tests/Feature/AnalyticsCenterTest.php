<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Analytics\Overview;
use App\Filament\Admin\Pages\Analytics\Schools;
use App\Filament\Admin\Support\AnalyticsBreakdownChart;
use App\Filament\Admin\Pages\Analytics\Registration as RegistrationAnalytics;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Selection;
use App\Models\Unit;
use App\Models\User;
use App\Services\AnalyticsCenterService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_tu_and_admin_unit_cannot_leak_other_unit_data_even_with_forged_filters(): void
    {
        $this->seed(ShieldSeeder::class);

        $own = $this->unit('Unit Analytics', 'AN-OWN');
        $other = $this->unit('Unit Lain', 'AN-OTHER');
        $inactive = $this->unit('Unit Nonaktif', 'AN-OFF', false);

        $this->registration($own, 1, 'SD Cemerlang');
        $this->registration($other, 2, 'Sekolah Asing');
        $this->registration($inactive, 3, 'Sekolah Nonaktif');

        foreach ([$own, $other] as $i => $unit) {
            $registration = Registration::query()->where('unit_id', $unit->id)->firstOrFail();
            Payment::create([
                'registration_id' => $registration->id,
                'va_number' => 'VA-'.($i + 1),
                'amount' => 250000,
                'status' => 'verified',
            ]);
            Document::create([
                'registration_id' => $registration->id,
                'type' => 'report_card',
                'file_path' => "test/file-{$i}.pdf",
                'original_name' => "file-{$i}.pdf",
                'file_type' => 'pdf',
                'file_size' => 100,
                'is_verified' => true,
            ]);
            Selection::create([
                'registration_id' => $registration->id,
                'decision' => 'accepted',
            ]);
        }

        $analytics = app(AnalyticsCenterService::class);
        $forged = ['unit_id' => $other->id, 'lifecycle' => 'all'];

        foreach (['tu', 'admin_unit'] as $role) {
            $staff = $this->staff($role, $own);

            $this->assertSame(1, $analytics->registrations($staff, $forged)->count());

            foreach ([
                'overview', 'registration', 'profile', 'schools', 'finance',
                'documents', 'testing', 'selection', 'post-admission', 'operations', 'explorer',
            ] as $section) {
                $json = json_encode($analytics->report($staff, $forged, $section), JSON_THROW_ON_ERROR);
                $this->assertStringNotContainsString('Sekolah Asing', $json, $section);
                $this->assertStringNotContainsString('Sekolah Nonaktif', $json, $section);
            }

            $this->assertSame('1', $analytics->report($staff, $forged, 'overview')['stats'][0]['value']);
            $this->assertSame('1', $analytics->report($staff, $forged, 'finance')['stats'][0]['value']);
            $this->assertSame('1', $analytics->report($staff, $forged, 'documents')['stats'][0]['value']);
            $this->assertSame('1', $analytics->report($staff, $forged, 'selection')['stats'][0]['value']);
        }

        $admin = $this->staff('super_admin');
        $this->assertSame(2, $analytics->registrations($admin)->count());
        $this->assertSame(1, $analytics->registrations($admin, ['unit_id' => $other->id])->count());
    }

    public function test_lifecycle_filters_and_school_name_normalization_are_independent_of_workflow(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = $this->unit('Unit Akademik', 'AN-SCHOOL');
        $active = $this->registration($unit, 4, ' SMAN 1 ');
        $this->registration($unit, 5, 'sman 1');
        $archived = $this->registration($unit, 6, 'SMAN 2', 'archived');
        $staff = $this->staff('tu', $unit);
        $analytics = app(AnalyticsCenterService::class);

        $this->assertSame(2, $analytics->registrations($staff)->count());
        $this->assertSame(3, $analytics->registrations($staff, ['lifecycle' => 'all'])->count());
        $this->assertSame(1, $analytics->registrations($staff, ['lifecycle' => 'archived'])->count());
        $this->assertSame(2, $analytics->registrations($staff, ['lifecycle' => 'invalid'])->count());

        $rows = $analytics->report($staff, [], 'schools')['groups'][0]['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame('SMAN 1', $rows[0]['label']);
        $this->assertSame(2, $rows[0]['count']);

        $this->assertNotNull($active);
        $this->assertNotNull($archived);
    }

    public function test_k12_without_study_program_or_cbt_has_valid_read_only_reports(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = $this->unit('SD tanpa prodi', 'AN-K12');
        $this->registration($unit, 7, 'SD Asal');
        $staff = $this->staff('admin_unit', $unit);
        $analytics = app(AnalyticsCenterService::class);

        $this->assertSame('0', $analytics->report($staff, [], 'testing')['stats'][0]['value']);
        $this->assertSame('0', $analytics->report($staff, [], 'post-admission')['stats'][0]['value']);
        $this->assertSame('1', $analytics->report($staff, [], 'profile')['stats'][0]['value']);

        // Date and unit values from the browser cannot disable the server-side
        // unit scope. An invalid date is ignored rather than interpolated.
        $this->assertSame(1, $analytics->registrations($staff, [
            'unit_id' => 999999, 'date_from' => 'garbage', 'lifecycle' => 'active',
        ])->count());
    }

    public function test_analytics_chart_has_no_background_polling_after_render(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = $this->unit('Unit Tanpa Polling', 'AN-NOPOLL');
        $this->registration($unit, 8, 'SD Asal');
        $staff = $this->staff('tu', $unit);

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AnalyticsBreakdownChart::class, [
            'filters' => ['unit_id' => $unit->id, 'lifecycle' => 'active'],
            'section' => 'overview',
        ])
            ->assertSuccessful()
            ->assertDontSee('wire:poll', false);
    }

    public function test_school_analytics_page_and_chart_render_with_real_aggregations(): void
    {
        $this->seed(ShieldSeeder::class);

        $own = $this->unit('SD Analitik', 'AN-SCH-P');
        $other = $this->unit('SMP Lain', 'AN-SCH-O');

        $one = $this->registration($own, 91, ' SD NUSANTARA ');
        $two = $this->registration($own, 92, 'sd nusantara');
        $this->registration($own, 93, 'SD HARAPAN');
        $this->registration($own, 95, '');
        $this->registration($own, 96, '   ')->update(['previous_school' => null]);
        $this->registration($other, 94, 'SEKOLAH RAHASIA');

        $one->update(['city' => 'Bandung']);
        $two->update(['city' => 'Bandung']);

        $staff = $this->staff('tu', $own);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Replicate the strict SQL mode that produced HTTP 500 on a MariaDB
        // development server, while leaving SQLite test runs unchanged.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ',ONLY_FULL_GROUP_BY')");
        }

        Livewire::test(Schools::class)
            ->assertSuccessful()
            ->assertSee('Asal sekolah terbanyak')
            ->assertSee('SD NUSANTARA')
            ->assertSee('Tidak diisi')
            ->assertDontSee('SEKOLAH RAHASIA');

        Livewire::test(AnalyticsBreakdownChart::class, [
            'filters' => ['unit_id' => $own->id, 'lifecycle' => 'active'],
            'section' => 'schools',
        ])->assertSuccessful();

        $analytics = app(AnalyticsCenterService::class);
        $report = $analytics->report($staff, [], 'schools');
        $this->assertSame('5', $report['stats'][0]['value']);

        $schools = collect($report['groups'][0]['rows'])->keyBy('label');
        $this->assertSame(2, $schools['SD NUSANTARA']['count']);
        $this->assertSame(2, $schools['Tidak diisi']['count']);
        $this->assertSame(1, $schools['SD HARAPAN']['count']);

        // The explorer uses a second school aggregation entry point.
        $explorer = $analytics->report($staff, ['dimension' => 'school'], 'explorer');
        $this->assertSame(2, collect($explorer['groups'][0]['rows'])
            ->keyBy('label')['SD NUSANTARA']['count']);
    }

    public function test_pages_require_staff_role_active_status_and_operational_unit(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = $this->unit('Unit Halaman', 'AN-PAGE');
        $adminUnit = $this->staff('admin_unit', $unit);
        $this->actingAs($adminUnit);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(Overview::canAccess());
        $this->assertTrue(RegistrationAnalytics::canAccess());
        Livewire::test(Overview::class)->assertSuccessful();

        $unit->update(['is_active' => false]);
        $this->assertFalse(Overview::canAccess());

        $applicant = $this->staff('pendaftar');
        $this->actingAs($applicant);
        $this->assertFalse(Overview::canAccess());

        $inactive = $this->staff('super_admin');
        $inactive->update(['is_active' => false]);
        $this->actingAs($inactive);
        $this->assertFalse(Overview::canAccess());
    }

    private function unit(string $name, string $code, bool $active = true): Unit
    {
        return Unit::create([
            'name' => $name, 'code' => $code,
            'institution_type' => 'school', 'is_active' => $active,
        ]);
    }

    private function staff(string $role, ?Unit $unit = null): User
    {
        $user = User::factory()->create([
            'unit_id' => $unit?->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function registration(Unit $unit, int $index, string $school, string $lifecycle = 'active'): Registration
    {
        $opening = RegistrationOpening::firstOrCreate(
            ['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1'],
            ['status' => 'open', 'registration_fee' => 250000],
        );

        return Registration::create([
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_number' => 'AN-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'nik' => '327301010101'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'full_name' => "Peserta {$index}",
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2013-01-01',
            'home_address' => 'Alamat',
            'previous_school' => $school,
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
            'lifecycle_status' => $lifecycle,
        ]);
    }
}
