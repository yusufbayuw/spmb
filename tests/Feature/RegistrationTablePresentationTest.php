<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Filament\Admin\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
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

        $this->assertSame([
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
        ], array_keys($columns));

        // NIK is no longer printed under the sticky participant name.
        $this->assertNull($columns['full_name']->getDescriptionBelow());
        $this->assertSame('NIK', $columns['nik']->getLabel());
        $this->assertStringContainsString(
            'spmb-registration-sticky-identity',
            $columns['full_name']->getExtraHeaderAttributeBag()->get('class'),
        );
        $this->assertArrayNotHasKey('class', $columns['nik']->getExtraCellAttributes());

        foreach ($columns as $name => $column) {
            $this->assertTrue($column->isSortable(), "{$name} should support sorting");
            if ($name === 'full_name') {
                $this->assertFalse($column->isToggleable(), 'Sticky participant identity must remain visible');
            } else {
                $this->assertTrue($column->isToggleable(), "{$name} should support Hide/Show");
                $this->assertFalse($column->isToggledHiddenByDefault(), "{$name} stays visible by default");
            }
        }

        // Lifecycle is an optional table filter, not an automatic restriction.
        $this->assertNull($table->getFilter('lifecycle_status')->getDefaultState());
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
