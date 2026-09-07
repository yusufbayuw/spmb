<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\OperationalReport;
use App\Filament\Admin\Pages\TestSessions;
use App\Filament\Admin\Pages\UnitRegistrationSettings;
use App\Filament\Admin\Resources\AdmissionQuotaResource;
use App\Filament\Admin\Resources\AdmissionTestResource;
use App\Filament\Admin\Resources\AdmissionTestResultResource;
use App\Filament\Admin\Resources\AnnouncementResource;
use App\Filament\Admin\Resources\AuditLogResource;
use App\Filament\Admin\Resources\DocumentResource;
use App\Filament\Admin\Resources\ParentInfoResource;
use App\Filament\Admin\Resources\PaymentResource;
use App\Filament\Admin\Resources\ReRegistrationItemResource;
use App\Filament\Admin\Resources\RegistrationOpeningResource;
use App\Filament\Admin\Resources\RegistrationPathwayResource;
use App\Filament\Admin\Resources\RegistrationResource;
use App\Filament\Admin\Resources\SelectionBatchResource;
use App\Filament\Admin\Resources\SelectionResource;
use App\Filament\Admin\Resources\StudyProgramResource;
use App\Filament\Admin\Resources\UnitResource;
use App\Filament\Admin\Resources\UserResource;
use App\Filament\Admin\Resources\VirtualAccountResource;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNavigationFlowTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('navigationItems')]
    public function test_admin_navigation_follows_business_flow(string $class, string $group, int $sort): void
    {
        $reflection = new ReflectionClass($class);

        $this->assertSame($group, $reflection->getStaticPropertyValue('navigationGroup'));
        $this->assertSame($sort, $reflection->getStaticPropertyValue('navigationSort'));
    }

    public function test_operational_navigation_uses_clear_labels_and_hides_duplicate_parent_menu(): void
    {
        $this->assertSame(
            'Data Pendaftar',
            (new ReflectionClass(RegistrationResource::class))->getStaticPropertyValue('navigationLabel'),
        );
        $this->assertSame(
            'Verifikasi Pembayaran',
            (new ReflectionClass(PaymentResource::class))->getStaticPropertyValue('navigationLabel'),
        );
        $this->assertSame(
            'Sesi Tes',
            (new ReflectionClass(TestSessions::class))->getStaticPropertyValue('navigationLabel'),
        );
        $this->assertFalse(ParentInfoResource::shouldRegisterNavigation());
    }

    public function test_re_registration_menu_follows_latest_published_unit_configuration(): void
    {
        $unit = Unit::create([
            'name' => 'Unit Test',
            'code' => 'UNIT-TEST',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $user->assignRole(Role::firstOrCreate([
            'name' => 'tu',
            'guard_name' => 'web',
        ]));

        $this->actingAs($user);

        $this->configuration($unit, 1, false);
        $this->assertFalse(ReRegistrationItemResource::shouldRegisterNavigation());

        $this->configuration($unit, 2, true);
        $this->assertTrue(ReRegistrationItemResource::shouldRegisterNavigation());

        $this->configuration($unit, 3, false);
        $this->assertFalse(ReRegistrationItemResource::shouldRegisterNavigation());
    }

    /**
     * @return array<string, array{0: class-string, 1: string, 2: int}>
     */
    public static function navigationItems(): array
    {
        return [
            'registration opening' => [RegistrationOpeningResource::class, 'Pendaftaran', 1],
            'registrations' => [RegistrationResource::class, 'Pendaftaran', 2],
            'parents' => [ParentInfoResource::class, 'Pendaftaran', 3],
            'payments' => [PaymentResource::class, 'Verifikasi', 1],
            'documents' => [DocumentResource::class, 'Verifikasi', 2],
            'test sessions' => [TestSessions::class, 'Seleksi & Pengumuman', 1],
            'test results' => [AdmissionTestResultResource::class, 'Seleksi & Pengumuman', 2],
            'selection batches' => [SelectionBatchResource::class, 'Seleksi & Pengumuman', 3],
            'selection decisions' => [SelectionResource::class, 'Seleksi & Pengumuman', 4],
            'announcements' => [AnnouncementResource::class, 'Seleksi & Pengumuman', 5],
            're-registration' => [ReRegistrationItemResource::class, 'Pasca-Pengumuman', 1],
            'operational report' => [OperationalReport::class, 'Laporan', 1],
            'unit registration settings' => [UnitRegistrationSettings::class, 'Konfigurasi SPMB', 1],
            'pathways' => [RegistrationPathwayResource::class, 'Konfigurasi SPMB', 2],
            'study programs' => [StudyProgramResource::class, 'Konfigurasi SPMB', 3],
            'virtual accounts' => [VirtualAccountResource::class, 'Konfigurasi SPMB', 4],
            'test configuration' => [AdmissionTestResource::class, 'Konfigurasi SPMB', 5],
            'admission quota' => [AdmissionQuotaResource::class, 'Konfigurasi SPMB', 6],
            'units' => [UnitResource::class, 'Sistem & Akses', 1],
            'users' => [UserResource::class, 'Sistem & Akses', 2],
            'audit trail' => [AuditLogResource::class, 'Sistem & Akses', 3],
        ];
    }

    private function configuration(Unit $unit, int $version, bool $postAnnouncementEnabled): UnitConfiguration
    {
        return UnitConfiguration::create([
            'unit_id' => $unit->id,
            'version' => $version,
            'status' => 'published',
            'payment_enabled' => true,
            'documents_enabled' => true,
            'tests_enabled' => false,
            'post_announcement_enabled' => $postAnnouncementEnabled,
            'fields' => [],
            'document_requirements' => [],
            'test_definitions' => [],
            're_registration_requirements' => [],
            'published_at' => now(),
        ]);
    }
}
