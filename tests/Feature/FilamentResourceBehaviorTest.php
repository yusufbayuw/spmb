<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\TestSessions;
use App\Filament\Admin\Resources\AdmissionTestResource\Pages\CreateAdmissionTest;
use App\Filament\Admin\Resources\AdmissionTestResource\Pages\EditAdmissionTest;
use App\Filament\Admin\Resources\AdmissionTestResource\Pages\ListAdmissionTests;
use App\Filament\Admin\Resources\AdmissionTestResultResource\Pages\CreateAdmissionTestResult;
use App\Filament\Admin\Resources\AdmissionTestResultResource\Pages\EditAdmissionTestResult;
use App\Filament\Admin\Resources\AdmissionTestResultResource\Pages\ListAdmissionTestResults;
use App\Filament\Admin\Resources\AnnouncementResource\Pages\CreateAnnouncement;
use App\Filament\Admin\Resources\AnnouncementResource\Pages\EditAnnouncement;
use App\Filament\Admin\Resources\AnnouncementResource\Pages\ListAnnouncements;
use App\Filament\Admin\Resources\DocumentResource\Pages\EditDocument;
use App\Filament\Admin\Resources\DocumentResource\Pages\ListDocuments;
use App\Filament\Admin\Resources\ParentInfoResource\Pages\CreateParentInfo;
use App\Filament\Admin\Resources\ParentInfoResource\Pages\EditParentInfo;
use App\Filament\Admin\Resources\PaymentResource\Pages\CreatePayment;
use App\Filament\Admin\Resources\PaymentResource\Pages\EditPayment;
use App\Filament\Admin\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Admin\Resources\RegistrationOpeningResource\Pages\CreateRegistrationOpening;
use App\Filament\Admin\Resources\RegistrationOpeningResource\Pages\EditRegistrationOpening;
use App\Filament\Admin\Resources\RegistrationOpeningResource\Pages\ListRegistrationOpenings;
use App\Filament\Admin\Resources\RegistrationPathwayResource\Pages\CreateRegistrationPathway;
use App\Filament\Admin\Resources\RegistrationPathwayResource\Pages\EditRegistrationPathway;
use App\Filament\Admin\Resources\RegistrationPathwayResource\Pages\ListRegistrationPathways;
use App\Filament\Admin\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Filament\Admin\Resources\RegistrationResource\Pages\EditRegistration;
use App\Filament\Admin\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Filament\Admin\Resources\SelectionResource\Pages\CreateSelection;
use App\Filament\Admin\Resources\SelectionResource\Pages\EditSelection;
use App\Filament\Admin\Resources\SelectionBatchResource;
use App\Filament\Admin\Resources\SelectionResource\Pages\ListSelections;
use App\Filament\Admin\Resources\StudyProgramResource\Pages\CreateStudyProgram;
use App\Filament\Admin\Resources\StudyProgramResource\Pages\EditStudyProgram;
use App\Filament\Admin\Resources\StudyProgramResource\Pages\ListStudyPrograms;
use App\Filament\Admin\Resources\UnitResource;
use App\Filament\Admin\Resources\UnitResource\Pages\CreateUnit;
use App\Filament\Admin\Resources\UnitResource\Pages\EditUnit;
use App\Filament\Admin\Resources\UnitResource\Pages\ListUnits;
use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Filament\Admin\Resources\VirtualAccountResource\Pages\ListVirtualAccounts;
use App\Filament\Applicant\Resources\RegistrationResource\Pages\ListRegistrations as ApplicantListRegistrations;
use App\Filament\RedirectsToResourceIndex;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilamentResourceBehaviorTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('adminCreateAndEditPages')]
    public function test_admin_create_and_edit_pages_redirect_to_their_resource_index(string $pageClass): void
    {
        $this->assertContains(RedirectsToResourceIndex::class, class_uses_recursive($pageClass));
    }

    public function test_redirect_trait_returns_the_resource_index_url(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $method = new ReflectionMethod(CreateUnit::class, 'getRedirectUrl');
        $method->setAccessible(true);

        $this->assertSame(UnitResource::getUrl('index'), $method->invoke(app(CreateUnit::class)));
    }

    #[DataProvider('statusTabPages')]
    public function test_status_driven_tables_provide_clickable_status_tabs(string $pageClass, array $expectedTabs): void
    {
        $this->assertSame($expectedTabs, array_keys(app($pageClass)->getTabs()));
    }

    public function test_status_tab_filters_its_table_query(): void
    {
        Unit::create(['name' => 'Unit Aktif', 'code' => 'ACT', 'is_active' => true]);
        Unit::create(['name' => 'Unit Nonaktif', 'code' => 'INA', 'is_active' => false]);

        $tabs = app(ListUnits::class)->getTabs();

        $this->assertSame(['ACT'], $tabs['active']->modifyQuery(Unit::query())->pluck('code')->all());
        $this->assertSame(['INA'], $tabs['inactive']->modifyQuery(Unit::query())->pluck('code')->all());
    }

    public function test_selection_batch_pathway_options_only_include_available_pathways_for_opening_unit(): void
    {
        $unit = Unit::create(['name' => 'Unit A', 'code' => 'UNIT-A', 'is_active' => true]);
        $otherUnit = Unit::create(['name' => 'Unit B', 'code' => 'UNIT-B', 'is_active' => true]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'open',
        ]);

        $available = RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);
        RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Nonaktif',
            'is_active' => false,
        ]);
        RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Arsip',
            'is_active' => false,
            'archived_at' => now(),
        ]);
        RegistrationPathway::create([
            'unit_id' => $otherUnit->id,
            'name' => 'Unit Lain',
            'is_active' => true,
        ]);

        $this->assertSame([], SelectionBatchResource::pathwayOptions(null));
        $this->assertSame(
            [$available->id => 'Reguler'],
            SelectionBatchResource::pathwayOptions($opening->id),
        );
    }

    public function test_test_session_status_tab_accepts_only_supported_statuses(): void
    {
        $administrator = User::factory()->create(['is_active' => true]);
        $administrator->assignRole(Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]));
        $this->actingAs($administrator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(TestSessions::class)
            ->call('selectStatusTab', 'closed')
            ->assertSet('statusTab', 'closed');
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function adminCreateAndEditPages(): array
    {
        return [
            'admission test create' => [CreateAdmissionTest::class],
            'admission test edit' => [EditAdmissionTest::class],
            'admission test result create' => [CreateAdmissionTestResult::class],
            'admission test result edit' => [EditAdmissionTestResult::class],
            'announcement create' => [CreateAnnouncement::class],
            'announcement edit' => [EditAnnouncement::class],
            'document edit' => [EditDocument::class],
            'parent information create' => [CreateParentInfo::class],
            'parent information edit' => [EditParentInfo::class],
            'payment create' => [CreatePayment::class],
            'payment edit' => [EditPayment::class],
            'registration opening create' => [CreateRegistrationOpening::class],
            'registration opening edit' => [EditRegistrationOpening::class],
            'registration pathway create' => [CreateRegistrationPathway::class],
            'registration pathway edit' => [EditRegistrationPathway::class],
            'registration create' => [CreateRegistration::class],
            'registration edit' => [EditRegistration::class],
            'selection create' => [CreateSelection::class],
            'selection edit' => [EditSelection::class],
            'study program create' => [CreateStudyProgram::class],
            'study program edit' => [EditStudyProgram::class],
            'unit create' => [CreateUnit::class],
            'unit edit' => [EditUnit::class],
            'user create' => [CreateUser::class],
            'user edit' => [EditUser::class],
        ];
    }

    /**
     * @return array<string, array{0: class-string, 1: array<int, string>}>
     */
    public static function statusTabPages(): array
    {
        return [
            'admission tests' => [ListAdmissionTests::class, ['all', 'active', 'inactive']],
            'admission test results' => [ListAdmissionTestResults::class, ['all', 'scheduled', 'completed', 'absent', 'exempted']],
            'announcements' => [ListAnnouncements::class, ['all', 'draft', 'published']],
            'documents' => [ListDocuments::class, ['all', 'pending', 'rejected', 'verified']],
            'payments' => [ListPayments::class, ['all', 'pending', 'paid', 'verified', 'rejected']],
            'registration openings' => [ListRegistrationOpenings::class, ['all', 'draft', 'scheduled', 'open', 'closed', 'archived']],
            'registration pathways' => [ListRegistrationPathways::class, ['all', 'active', 'inactive', 'archived']],
            'admin registrations' => [ListRegistrations::class, ['all', 'active', 'withdrawn', 'cancelled', 'archived']],
            'selections' => [ListSelections::class, ['all', 'pending', 'accepted', 'rejected', 'waiting_list']],
            'study programs' => [ListStudyPrograms::class, ['all', 'active', 'inactive']],
            'units' => [ListUnits::class, ['all', 'active', 'inactive']],
            'users' => [ListUsers::class, ['all', 'active', 'inactive']],
            'virtual accounts' => [ListVirtualAccounts::class, ['all', 'available', 'assigned', 'paid', 'expired', 'cancelled']],
            'applicant registrations' => [ApplicantListRegistrations::class, ['all', 'active', 'withdrawn', 'cancelled', 'archived']],
        ];
    }
}
