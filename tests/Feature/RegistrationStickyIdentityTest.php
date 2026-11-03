<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationResource;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RegistrationStickyIdentityTest extends TestCase
{
    public function test_registration_identity_column_uses_filament_cell_and_header_attributes(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $table = RegistrationResource::table(Table::make(
            app(\App\Filament\Admin\Resources\RegistrationResource\Pages\ListRegistrations::class),
        ));
        $columns = $table->getColumns();

        $this->assertSame('full_name', array_key_first($columns));
        $this->assertSame('nik', array_keys($columns)[1]);
        $this->assertSame('registration_number', array_keys($columns)[2]);

        $identity = $columns['full_name'];
        $this->assertStringContainsString('spmb-registration-sticky-identity', $identity->getExtraHeaderAttributeBag()->get('class'));
        $this->assertStringContainsString('spmb-registration-sticky-identity', $identity->getExtraCellAttributeBag()->get('class'));

        $this->assertArrayNotHasKey('class', $columns['nik']->getExtraCellAttributes());
        $this->assertArrayNotHasKey('class', $columns['registration_number']->getExtraCellAttributes());
    }

    public function test_sticky_css_is_desktop_only_and_scoped_to_marked_table(): void
    {
        $stylesheet = File::get(resource_path('views/filament/admin/registration-sticky-styles.blade.php'));
        $provider = File::get(app_path('Providers/Filament/AdminPanelProvider.php'));

        $this->assertStringContainsString('@media (min-width: 1024px)', $stylesheet);
        $this->assertStringContainsString(':has(.spmb-registration-sticky-identity)', $stylesheet);
        $this->assertStringContainsString('.fi-ta-selection-cell:first-child', $stylesheet);
        $this->assertStringContainsString("view('filament.admin.registration-sticky-styles')", $provider);
    }
}
