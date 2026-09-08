<?php

namespace Database\Seeders;

use App\Services\IndonesiaRegionImportService;
use Illuminate\Database\Seeder;
use RuntimeException;

class IndonesiaRegionSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/indonesia_regions.csv');

        if (! is_file($path)) {
            throw new RuntimeException(
                'File database/data/indonesia_regions.csv belum tersedia. '
                .'Gunakan php artisan spmb:import-regions <file.csv> atau letakkan CSV pada path tersebut.'
            );
        }

        app(IndonesiaRegionImportService::class)->import($path);
    }
}
