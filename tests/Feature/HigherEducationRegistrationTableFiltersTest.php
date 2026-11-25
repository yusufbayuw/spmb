<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Filament\Admin\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Models\Registration;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\HigherEducationRegistrationTableService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
