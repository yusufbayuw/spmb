<?php

namespace Database\Seeders;

use App\Services\IndonesiaRegionImportService;
use Illuminate\Database\Seeder;
use RuntimeException;

class IndonesiaRegionSeeder extends Seeder
{
    public function run(): void
    {
        $directory = database_path('data/indonesia_regions');

        if (! is_dir($directory)) {
            throw new RuntimeException(
                'Direktori database/data/indonesia_regions belum tersedia.'
            );
        }

        $importer = app(IndonesiaRegionImportService::class);
        $result = $importer->importDirectory($directory);
        $importer->assertComplete($result);

        $this->command?->info(
            "{$result['rows']} baris wilayah diproses; "
            ."{$result['provinces']} provinsi, {$result['regencies']} kabupaten/kota, "
            ."{$result['districts']} kecamatan, {$result['villages']} desa/kelurahan."
        );
    }
}
