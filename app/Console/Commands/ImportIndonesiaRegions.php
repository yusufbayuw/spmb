<?php

namespace App\Console\Commands;

use App\Services\IndonesiaRegionImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportIndonesiaRegions extends Command
{
    protected $signature = 'spmb:import-regions
        {source? : CSV master wilayah atau direktori shard; default database/data/indonesia_regions}
        {--chunk=1000 : Jumlah desa per batch}
        {--allow-partial : Izinkan dataset parsial tanpa validasi cakupan seluruh Indonesia}';

    protected $description = 'Mengimpor master Provinsi, Kabupaten/Kota, Kecamatan, dan Desa/Kelurahan';

    public function handle(IndonesiaRegionImportService $importer): int
    {
        $source = (string) ($this->argument('source') ?: database_path('data/indonesia_regions'));
        $chunkSize = max(100, (int) $this->option('chunk'));

        try {
            $result = is_dir($source)
                ? $importer->importDirectory($source, $chunkSize)
                : $importer->import($source, $chunkSize);

            if (! $this->option('allow-partial')) {
                $importer->assertComplete($result);
            }
        } catch (ValidationException $exception) {
            $this->components->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->components->info(
            "{$result['rows']} baris diproses; {$result['provinces']} provinsi, {$result['regencies']} kabupaten/kota, "
            ."{$result['districts']} kecamatan, dan {$result['villages']} desa/kelurahan tersedia."
        );

        return self::SUCCESS;
    }
}
