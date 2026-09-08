<?php

namespace App\Console\Commands;

use App\Services\IndonesiaRegionImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportIndonesiaRegions extends Command
{
    protected $signature = 'spmb:import-regions {file : CSV master wilayah Indonesia} {--chunk=1000 : Jumlah desa per batch}';

    protected $description = 'Mengimpor master Provinsi, Kabupaten/Kota, Kecamatan, dan Desa/Kelurahan dari CSV';

    public function handle(IndonesiaRegionImportService $importer): int
    {
        $path = (string) $this->argument('file');
        $chunkSize = max(100, (int) $this->option('chunk'));

        try {
            $result = $importer->import($path, $chunkSize);
        } catch (ValidationException $exception) {
            $this->components->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->components->info(
            "{$result['rows']} baris diproses; {$result['provinces']} provinsi, {$result['regencies']} kabupaten/kota, "
            ."{$result['districts']} kecamatan, dan {$result['villages']} desa/kelurahan di-upsert."
        );

        return self::SUCCESS;
    }
}
