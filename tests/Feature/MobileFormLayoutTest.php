<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MobileFormLayoutTest extends TestCase
{
    public function test_filament_forms_do_not_use_fixed_multi_column_mobile_layouts(): void
    {
        $files = collect(File::allFiles(app_path('Filament')))
            ->filter(fn (\SplFileInfo $file): bool => $file->getExtension() === 'php')
            ->push(new \SplFileInfo(app_path('Services/ConfiguredRegistrationForm.php')));

        $violations = [];

        foreach ($files as $file) {
            $content = File::get($file->getPathname());

            if (preg_match('/->(?:columns|columnSpan)\(\s*[2-9]\s*\)/', $content)) {
                $violations[] = $file->getPathname().' uses a fixed multi-column Filament layout.';
            }

            if (preg_match('/Grid::make\(\s*[2-9]\s*\)/', $content)) {
                $violations[] = $file->getPathname().' uses a fixed multi-column Grid.';
            }

            if (preg_match('/->columns\(\[[^\)]*[\'\"]default[\'\"]\s*=>\s*[2-9]/s', $content)) {
                $violations[] = $file->getPathname().' has more than one default mobile column.';
            }

            if (preg_match('/->columns\(\[[^\)]*[\'\"]sm[\'\"]\s*=>\s*[2-9]/s', $content)) {
                $violations[] = $file->getPathname().' switches to multiple columns before the md breakpoint.';
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_filament_custom_views_keep_grid_layouts_single_column_below_md(): void
    {
        $views = File::allFiles(resource_path('views/filament'));
        $violations = [];

        foreach ($views as $view) {
            if ($view->getExtension() !== 'php') {
                continue;
            }

            $content = File::get($view->getPathname());

            if (preg_match('/\bsm:grid-cols-[2-9]\b/', $content)) {
                $violations[] = $view->getPathname().' uses a multi-column grid at the sm breakpoint.';
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }
}
