<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Filament\Admin\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\HigherEducationRegistrationTableService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class HigherEducationRegistrationTableFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_types_and_unit_boundaries(): void
    {
        // Exercise real database JSON expressions without relying on a particular
        // admissions workflow or seeder. Both database CI jobs run this test.
        $schema = DB::connection()->getSchemaBuilder();
        $schema->create('registration_filter_fixture', function ($table) {
            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->json('custom_answers')->nullable();
        });

        try {
            foreach ([
                [1, 1, ['score' => '100', 'ref' => 'Website', 'date' => '2026-11-20', 'flag' => true, 'choices' => ['A', 'B'], 'file' => 'private/a.pdf']],
                [2, 1, ['score' => '9', 'ref' => 'Instagram', 'date' => '2026-01-01', 'flag' => '0', 'choices' => ['B'], 'file' => '']],
                [3, 1, ['score' => 'invalid', 'ref' => '100% literal', 'flag' => null, 'choices' => []]],
                [4, 2, ['score' => '99', 'ref' => 'Website', 'flag' => true, 'choices' => ['A', 'B']]],
                [5, 1, ['score' => 0, 'flag' => false]],
            ] as [$id, $unit, $answers]) {
                DB::table('registration_filter_fixture')->insert(['id' => $id, 'unit_id' => $unit, 'custom_answers' => json_encode($answers)]);
            }

            $service = app(HigherEducationRegistrationTableService::class);
            $query = fn () => Registration::query()->from('registration_filter_fixture as registrations')->withoutEagerLoads();
            $ids = fn ($builder) => $builder->pluck('registrations.id')->map(fn ($id) => (int) $id)->all();

            $this->assertSame([5, 2, 1, 3], $ids($service->sortCustom($query()->where('unit_id', 1), 1, 'score', 'number', 'asc')));
            $this->assertSame([1, 2, 5, 3], $ids($service->sortCustom($query()->where('unit_id', 1), 1, 'score', 'number', 'desc')));
            $this->assertSame([1], $ids($service->filterCustom($query(), 1, 'score', 'number', ['from' => '10'])));
            $this->assertSame([5], $ids($service->filterCustom($query(), 1, 'score', 'number', ['from' => '0', 'to' => '0'])));
            $this->assertSame([3], $ids($service->filterCustom($query(), 1, 'ref', 'text', ['value' => '%'])));
            $this->assertSame([1], $ids($service->filterCustom($query(), 1, 'ref', 'select', ['values' => ['Website']])));
            $this->assertSame([1], $ids($service->filterCustom($query(), 1, 'date', 'date', ['from' => '2026-11-01'])));
            $this->assertSame([1], $ids($service->filterCustom($query(), 1, 'flag', 'boolean', ['values' => ['1']])));
            $this->assertSame([2, 5], $ids($service->filterCustom($query(), 1, 'flag', 'boolean', ['values' => ['0']])->orderBy('id')));
            $this->assertSame([1, 2], $ids($service->filterCustom($query(), 1, 'choices', 'multiselect', ['values' => ['A', 'B'], 'match' => 'any'])->orderBy('id')));
            $this->assertSame([1], $ids($service->filterCustom($query(), 1, 'choices', 'multiselect', ['values' => ['A', 'B'], 'match' => 'all'])));
            $this->assertSame([1], $ids($service->filterCustom($query(), 1, 'file', 'file', ['presence' => 'filled'])));
            $this->assertSame([3, 5], $ids($service->filterCustom($query(), 1, 'choices', 'multiselect', ['presence' => 'empty'])->orderBy('id')));
            $this->assertFalse($service->validCustomDefinition(['key' => "x' OR 1=1", 'type' => 'text']));
        } finally {
            $schema->dropIfExists('registration_filter_fixture');
        }
    }

    public function test_configured_columns_are_sortable_and_filters_do_not_depend_on_temporary_visibility(): void
    {
        config()->set('spmb.operations.mode', 'HIGHER_EDUCATION');
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'University', 'code' => 'UNIF', 'institution_type' => 'university', 'is_active' => true,
            'registration_table_columns' => ['full_name', 'study_program', 'custom_referral', 'custom:referral', 'custom:score']]);
        $staff = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('tu');
        $this->actingAs($staff);
        UnitConfiguration::create([
            'unit_id' => $unit->id, 'version' => 1, 'status' => 'published', 'published_at' => now(),
            'fields' => [
                ['key' => 'referral', 'label' => 'Referral', 'type' => 'text', 'active' => true],
                ['key' => 'score', 'label' => 'Score', 'type' => 'number', 'active' => true],
                ['key' => 'other', 'label' => 'Other', 'type' => 'date', 'active' => true],
            ],
        ] + app(\App\Services\UnitConfigurationService::class)->defaults($unit));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = app(ListRegistrations::class);
        $page->toggledTableColumns = ['custom:referral' => false];
        $table = RegistrationResource::table(Table::make($page));

        foreach ($table->getColumns() as $column) {
            $this->assertTrue($column->isSortable(), $column->getName());
        }
        $this->assertNotNull($table->getFilter('study_program'));
        $this->assertNotNull($table->getFilter('custom_'.$unit->id.'_referral'));
        $this->assertNotNull($table->getFilter('custom_'.$unit->id.'_score'));
        $this->assertNull($table->getFilter('custom_'.$unit->id.'_other'));
    }

    public function test_livewire_unit_switch_reset_sort_hidden_filter_and_real_excel_export(): void
    {
        config()->set('spmb.operations.mode', 'HIGHER_EDUCATION');
        $this->seed(ShieldSeeder::class);
        $a = $this->university('FILTER-A', [
            ['key' => 'score', 'label' => 'Score A', 'type' => 'number', 'active' => true],
            ['key' => 'referral', 'label' => 'Referral A', 'type' => 'text', 'active' => true],
        ]);
        $b = $this->university('FILTER-B');
        $c = $this->university('FILTER-C');
        $aZ = $this->opening($a, 'ZZ', 'Zoologi');
        $aA = $this->opening($a, 'AA', 'Akuntansi');
        $bOpening = $this->opening($b, 'B', 'Bisnis');
        $cOpening = $this->opening($c, 'C', 'Kimia');
        $low = $this->registration($aZ, 1, ['score' => '9', 'referral' => 'Website'], 'valid');
        $high = $this->registration($aA, 2, ['score' => '100', 'referral' => 'Website'], 'valid');
        $middle = $this->registration($aZ, 3, ['score' => '75', 'referral' => 'Teman'], 'pending');
        $foreignB = $this->registration($bOpening, 4, ['score' => '90', 'referral' => 'Website'], 'valid');
        $foreignC = $this->registration($cOpening, 5, ['score' => '90', 'referral' => 'Website'], 'valid');
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $score = 'custom_'.$a->id.'_score';
        $referral = 'custom_'.$a->id.'_referral';

        $list = Livewire::test(ListRegistrations::class)->assertSuccessful()->assertCountTableRecords(5);
        $this->assertNull($list->instance()->getTable()->getFilter($score));

        $list->filterTable('unit_id', $a)
            ->assertSuccessful()->assertCountTableRecords(3)->assertTableFilterExists($score)
            ->assertCanNotSeeTableRecords([$foreignB, $foreignC])
            ->sortTable('custom:score', 'asc')
            ->assertCanSeeTableRecords([$low, $middle, $high], inOrder: true)
            ->sortTable('study_program', 'asc')
            ->assertCanSeeTableRecords([$high, $low], inOrder: true);

        $list->filterTable('study_program', ['values' => [(string) $aA->study_program_id]])
            ->assertCountTableRecords(1)->assertCanSeeTableRecords([$high])
            ->removeTableFilter('study_program')
            ->assertCountTableRecords(3)
            ->set('toggledTableColumns.custom:referral', false)
            ->assertTableFilterExists($referral)
            ->filterTable($score, ['from' => '50'])
            ->filterTable($referral, ['value' => 'Website', 'operator' => 'equals'])
            ->set('activeTab', 'validated')
            ->assertSuccessful()->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$high])
            ->assertCanNotSeeTableRecords([$low, $middle, $foreignB, $foreignC]);

        $this->assertTrue($list->instance()->isTableColumnToggledHidden('custom:referral'));

        // Invoke the actual list export, and inspect the workbook rather than
        // asserting only that a file was returned.
        $response = $list->instance()->exportExcel();
        $path = $response->getFile()->getPathname();
        $reader = new Reader();
        $reader->open($path);
        try {
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'Data Pendaftaran') {
                    continue;
                }
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
            $this->assertCount(2, $rows);
            $this->assertContains($high->registration_number, $rows[1]);
        } finally {
            $reader->close();
            @unlink($path);
        }

        $list->sortTable('custom:score', 'desc')->filterTable('unit_id', $b)
            ->assertSuccessful()->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$foreignB]);
        $this->assertNull($list->instance()->getTable()->getFilter($score));
        $this->assertNull($list->get('tableSortColumn'));
        $this->assertArrayNotHasKey($referral, $list->get('tableFilters'));

        // B and C have no custom fields: the program choices must still
        // be rebuilt when switching between them.
        $list->filterTable('study_program', ['values' => [(string) $bOpening->study_program_id]])
            ->filterTable('unit_id', $c)
            ->assertSuccessful()->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$foreignC]);
        $programOptions = $list->instance()->getTable()->getFilter('study_program')->getFormSchema()[0]->getOptions();
        $this->assertArrayHasKey($cOpening->study_program_id, $programOptions);
        $this->assertArrayNotHasKey($bOpening->study_program_id, $programOptions);

        $list->resetTableFilters()->set('activeTab', 'all')->assertCountTableRecords(5);
        $this->assertNull($list->instance()->getTable()->getFilter($score));
        $list->filterTable('unit_id', $a)->assertTableFilterExists($score)->assertCountTableRecords(3)
            ->removeTableFilter('unit_id')->assertCountTableRecords(5);
        $this->assertNull($list->instance()->getTable()->getFilter($score));
    }

    public function test_staff_cannot_select_other_units_or_use_their_custom_filter_definitions(): void
    {
        config()->set('spmb.operations.mode', 'HIGHER_EDUCATION');
        $this->seed(ShieldSeeder::class);
        $own = $this->university('OWN-F', [['key' => 'score', 'label' => 'Own score', 'type' => 'number', 'active' => true]]);
        $foreign = $this->university('OTHER-F', [['key' => 'secret', 'label' => 'Foreign field', 'type' => 'text', 'active' => true]]);
        $record = $this->registration($this->opening($own, 'OWN', 'Own program'), 11, ['score' => '100'], 'valid');
        $other = $this->registration($this->opening($foreign, 'OTHER', 'Foreign program'), 12, ['secret' => 'restricted'], 'valid');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (['tu', 'admin_unit'] as $role) {
            $actor = User::factory()->create(['role' => $role, 'unit_id' => $own->id, 'is_active' => true, 'email_verified_at' => now()]);
            $actor->assignRole($role);
            $this->actingAs($actor);
            $list = Livewire::test(ListRegistrations::class)->assertSuccessful()
                ->assertCanSeeTableRecords([$record])->assertCanNotSeeTableRecords([$other])
                ->assertTableFilterExists('custom_'.$own->id.'_score');
            $this->assertNull($list->instance()->getTable()->getFilter('custom_'.$foreign->id.'_secret'));
            $list->set('tableFilters.unit_id.value', (string) $foreign->id)
                ->assertSuccessful()->assertCanNotSeeTableRecords([$other]);
            $this->assertNull($list->instance()->getTable()->getFilter('custom_'.$foreign->id.'_secret'));
        }
    }

    private function university(string $code, array $fields = []): Unit
    {
        $unit = Unit::create([
            'name' => 'University '.$code, 'code' => $code, 'institution_type' => 'university', 'is_active' => true,
            'registration_table_columns' => ['full_name', 'study_program', ...array_map(fn ($f) => 'custom:'.$f['key'], $fields)],
        ]);
        UnitConfiguration::create([
            'unit_id' => $unit->id, 'version' => 1, 'status' => 'published', 'published_at' => now(),
            'fields' => $fields,
        ] + app(\App\Services\UnitConfigurationService::class)->defaults($unit));

        return $unit;
    }

    private function opening(Unit $unit, string $code, string $name): RegistrationOpening
    {
        $program = StudyProgram::create([
            'unit_id' => $unit->id, 'code' => $code, 'name' => $name, 'degree_level' => 'S1', 'is_active' => true,
        ]);

        return RegistrationOpening::create([
            'unit_id' => $unit->id, 'study_program_id' => $program->id, 'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1', 'status' => 'open', 'registration_fee' => 0,
        ]);
    }

    private function registration(RegistrationOpening $opening, int $index, array $answers, string $validation): Registration
    {
        $pathway = RegistrationPathway::firstOrCreate(['unit_id' => $opening->unit_id, 'name' => 'Reguler'], ['is_active' => true]);

        return Registration::create([
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'unit_id' => $opening->unit_id, 'registration_opening_id' => $opening->id, 'registration_pathway_id' => $pathway->id,
            'registration_number' => 'FILTER-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'full_name' => 'Synthetic applicant '.$index, 'nik' => '327301010101'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2008-01-01', 'home_address' => 'Synthetic address',
            'status' => 'submitted', 'current_stage' => 'data_validation', 'data_validation_status' => $validation,
            'lifecycle_status' => 'active', 'custom_answers' => $answers,
        ]);
    }
}
