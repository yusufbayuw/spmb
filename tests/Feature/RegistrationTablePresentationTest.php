<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Filament\Admin\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Services\UnitConfigurationService;
use App\Models\User;
use App\Services\RegistrationTableColumnService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTablePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_and_nik_have_separate_columns_with_sorting_and_optional_column_visibility(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $table = RegistrationResource::table(Table::make(app(ListRegistrations::class)));
        $columns = $table->getColumns();

        $expected = [
            'full_name',
            'nik',
            'registration_number',
            'unit.name',
            'opening.academic_year',
            'is_alumni',
            'opening.wave',
            'pathway.name',
            'opening.registration_fee',
            'current_stage',
            'lifecycle_status',
            'created_at',
        ];
        if (\App\Support\SpmbOperationalMode::allowsHigherEducation()) {
            $expected[] = 'study_program';
        }
        $expected = array_merge($expected, [
            'nisn', 'nickname', 'gender', 'birth_place', 'birth_date',
            'phone', 'email', 'previous_school', 'graduation_year', 'data_validation_status',
        ]);
        $this->assertSame($expected, array_keys($columns));

        // NIK is no longer printed under the sticky participant name.
        $this->assertNull($columns['full_name']->getDescriptionBelow());
        $this->assertSame('NIK', $columns['nik']->getLabel());
        $this->assertStringContainsString(
            'spmb-registration-sticky-identity',
            $columns['full_name']->getExtraHeaderAttributeBag()->get('class'),
        );
        $this->assertArrayNotHasKey('class', $columns['nik']->getExtraCellAttributes());

        foreach ($columns as $name => $column) {
            if ($name !== 'study_program') {
                $this->assertTrue($column->isSortable(), "{$name} should support sorting");
            }
            if ($name === 'full_name') {
                $this->assertFalse($column->isToggleable(), 'Sticky participant identity must remain visible');
            } else {
                $this->assertTrue($column->isToggleable(), "{$name} should support Hide/Show");
                $this->assertSame(
                    ! in_array($name, RegistrationTableColumnService::DEFAULT_COLUMNS, true),
                    $column->isToggledHiddenByDefault(),
                    "{$name} should respect the legacy visible-column baseline",
                );
            }
        }

        // Lifecycle is an optional table filter, not an automatic restriction.
        $this->assertNull($table->getFilter('lifecycle_status')->getDefaultState());
    }

    public function test_unit_column_defaults_and_overrides_preserve_existing_table_columns(): void
    {
        config()->set('spmb.operations.mode', 'MIXED');
        $school = Unit::create([
            'name' => 'SD Kolom', 'code' => 'SDC',
            'institution_type' => 'school', 'is_active' => true,
        ]);
        $higher = Unit::create([
            'name' => 'Universitas Kolom', 'code' => 'UNIK',
            'institution_type' => 'university', 'is_active' => true,
        ]);
        $service = app(RegistrationTableColumnService::class);

        $this->assertSame(RegistrationTableColumnService::DEFAULT_COLUMNS, $service->visibleColumns($school));
        $this->assertContains('study_program', $service->visibleColumns($higher));
        $this->assertArrayNotHasKey('study_program', $service->options($school));

        $school->update(['registration_table_columns' => ['nik', 'nisn']]);
        $this->assertSame(['full_name', 'nik', 'nisn'], $service->visibleColumns($school->fresh()));
        $this->assertFalse($service->showByDefault($school->fresh(), 'registration_number'));
        $this->assertTrue($service->showByDefault($school->fresh(), 'nisn'));
        $this->assertSame(['full_name', 'nisn'], $service->validateColumns($school, ['nisn']));
    }

    public function test_super_admin_sees_enabled_custom_columns_from_all_units_without_a_filter(): void
    {
        config()->set('spmb.operations.mode', 'MIXED');
        $this->seed(ShieldSeeder::class);

        $first = Unit::create([
            'name' => 'Universitas Satu',
            'code' => 'UONE',
            'institution_type' => 'university',
            'is_active' => true,
        ]);
        $second = Unit::create([
            'name' => 'Universitas Dua',
            'code' => 'UTWO',
            'institution_type' => 'university',
            'is_active' => true,
        ]);

        foreach ([$first, $second] as $unit) {
            UnitConfiguration::create([
                ...app(UnitConfigurationService::class)->defaults($unit),
                'unit_id' => $unit->id,
                'version' => 1,
                'status' => 'published',
                'fields' => [
                    [
                        'key' => 'custom_referal',
                        'label' => 'Referal',
                        'type' => 'text',
                        'active' => true,
                        'required' => false,
                        'group_key' => 'group_additional',
                    ],
                    [
                        'key' => 'custom_inactive',
                        'label' => 'Tidak Aktif',
                        'type' => 'text',
                        'active' => false,
                        'required' => false,
                    ],
                ],
            ]);
        }

        $first->update(['registration_table_columns' => [
            'full_name', 'nik', 'custom:custom_referal',
        ]]);
        $second->update(['registration_table_columns' => [
            'full_name', 'nik',
        ]]);

        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $table = RegistrationResource::table(Table::make(app(ListRegistrations::class)));
        $columns = $table->getColumns();

        $name = 'custom:'.$first->id.':custom_referal';
        $this->assertArrayHasKey($name, $columns);
        $this->assertSame('Referal (Universitas Satu)', $columns[$name]->getLabel());
        $this->assertFalse($columns[$name]->isToggledHiddenByDefault());
        $this->assertArrayNotHasKey('custom:'.$second->id.':custom_referal', $columns);
        $this->assertArrayNotHasKey('custom:'.$first->id.':custom_inactive', $columns);
    }

    public function test_unit_staff_can_see_own_custom_column_without_filter(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create([
            'name' => 'Sekolah Referal',
            'code' => 'SREF',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        UnitConfiguration::create([
            ...app(UnitConfigurationService::class)->defaults($unit),
            'unit_id' => $unit->id,
            'version' => 1,
            'status' => 'published',
            'fields' => [[
                'key' => 'custom_referal',
                'label' => 'Referal',
                'type' => 'text',
                'active' => true,
                'required' => false,
                'group_key' => 'group_additional',
            ]],
        ]);
        $unit->update(['registration_table_columns' => [
            'full_name', 'nik', 'custom:custom_referal',
        ]]);

        $staff = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('admin_unit');
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $table = RegistrationResource::table(Table::make(app(ListRegistrations::class)));
        $columns = $table->getColumns();
        $this->assertArrayHasKey('custom:custom_referal', $columns);
        $this->assertSame('Referal', $columns['custom:custom_referal']->getLabel());
        $this->assertFalse($columns['custom:custom_referal']->isToggledHiddenByDefault());

        $unit->update(['registration_table_columns' => ['full_name']]);
        $table = RegistrationResource::table(Table::make(app(ListRegistrations::class)));
        $this->assertTrue($table->getColumns()['custom:custom_referal']->isToggledHiddenByDefault());
    }

    public function test_every_column_sort_executes_without_changing_registration_visibility(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'Unit Sort',
            'code' => 'SORT',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('tu');

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 50000,
        ]);
        $pathway = RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);
        $records = [];

        foreach (['Budi', 'Ani'] as $index => $name) {
            $records[] = Registration::create([
                'user_id' => User::factory()->create(['is_active' => true])->id,
                'unit_id' => $unit->id,
                'registration_opening_id' => $opening->id,
                'registration_pathway_id' => $pathway->id,
                'registration_number' => 'SORT-0'.($index + 1),
                'full_name' => $name,
                'nik' => '327301010101'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2012-01-01',
                'home_address' => 'Alamat Test',
                'status' => 'submitted',
                'current_stage' => 'data_validation',
                'data_validation_status' => 'pending',
                'lifecycle_status' => 'active',
            ]);
        }

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $list = Livewire::test(ListRegistrations::class)->assertSuccessful();

        foreach ([
            'full_name',
            'nik',
            'registration_number',
            'unit.name',
            'opening.academic_year',
            'is_alumni',
            'opening.wave',
            'pathway.name',
            'opening.registration_fee',
            'current_stage',
            'lifecycle_status',
            'created_at',
        ] as $column) {
            $list->sortTable($column, 'asc')
                ->assertSuccessful()
                ->assertCanSeeTableRecords($records);
        }

        // Hiding a column must not delete, mutate or hide the underlying records.
        $list->set('toggledTableColumns.nik', false)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($records);
        $this->assertTrue($list->instance()->isTableColumnToggledHidden('nik'));
        $this->assertSame('Budi', $records[0]->fresh()->full_name);
        $this->assertSame('3273010101010001', $records[0]->fresh()->nik);
    }
}
