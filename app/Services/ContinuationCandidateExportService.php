<?php

namespace App\Services;

use App\Models\ContinuationCandidate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContinuationCandidateExportService
{
    /**
     * @return array{path:string,filename:string,count:int}
     */
    public function export(User $actor, int $unitId, string $academicYear): array
    {
        abort_unless(
            $actor->isAdmin()
            || (($actor->isAdminUnit() || $actor->isTU()) && (int) $actor->unit_id === $unitId),
            403,
        );

        $unit = Unit::query()->findOrFail($unitId);
        $academicYear = $this->academicYear($academicYear);

        $query = ContinuationCandidate::query()
            ->where('unit_id', $unitId)
            ->where('academic_year', $academicYear)
            ->orderBy('full_name')
            ->orderBy('id');

        $count = (clone $query)->count();

        $directory = storage_path('app/tmp/exports');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.Str::uuid().'.xlsx';
        $filename = 'data-terusan-koreksi-'.(Str::slug($unit->code ?: $unit->name) ?: 'unit').'-'.Str::slug($academicYear).'.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);

        $headerStyle = (new Style())
            ->setFontBold()
            ->setShouldWrapText();

        $titleStyle = (new Style())
            ->setFontBold()
            ->setFontSize(14);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Data Terusan');
        $sheet->setSheetView((new SheetView())->setFreezeRow(2));

        $headers = [
            'ID Data Terusan',
            'Nama',
            'NIPD',
            'JK',
            'NISN',
            'Tempat Lahir',
            'Tanggal Lahir',
            'NIK',
            'Agama',
            'Alamat',
            'RT',
            'RW',
            'Dusun',
            'Kelurahan',
            'Kecamatan',
            'Kode Pos',
            'Jenis Tinggal',
            'Alat Transportasi',
            'Telepon',
            'HP',
            'E-Mail',
            'SKHUN',
            'Penerima KPS',
            'No. KPS',
            'Sekolah Asal',
            'Data Ayah Nama',
            'Data Ayah Tahun Lahir',
            'Data Ayah Jenjang Pendidikan',
            'Data Ayah Pekerjaan',
            'Data Ayah Penghasilan',
            'Data Ayah NIK',
            'Data Ibu Nama',
            'Data Ibu Tahun Lahir',
            'Data Ibu Jenjang Pendidikan',
            'Data Ibu Pekerjaan',
            'Data Ibu Penghasilan',
            'Data Ibu NIK',
            'Data Wali Nama',
            'Data Wali Tahun Lahir',
            'Data Wali Jenjang Pendidikan',
            'Data Wali Pekerjaan',
            'Data Wali Penghasilan',
            'Data Wali NIK',
            'Aktif',
        ];

        $writer->addRow(Row::fromValues($headers, $headerStyle));

        foreach (range(1, count($headers)) as $column) {
            $sheet->setColumnWidth($column === 1 ? 38 : 18, $column);
        }

        foreach ($query->lazy(500) as $candidate) {
            $prefill = is_array($candidate->prefill_data) ? $candidate->prefill_data : [];
            $raw = is_array($candidate->raw_data) ? $candidate->raw_data : [];

            $writer->addRow(Row::fromValues([
                $candidate->uuid,
                $candidate->full_name ?? ($prefill['full_name'] ?? $this->raw($raw, ['nama', 'nama lengkap'])),
                $candidate->nipd ?? $this->raw($raw, ['nipd']),
                $prefill['gender'] ?? $this->raw($raw, ['jk', 'jenis kelamin']),
                $candidate->nisn ?? $this->raw($raw, ['nisn']),
                $prefill['birth_place'] ?? $this->raw($raw, ['tempat lahir']),
                $candidate->birth_date?->format('d/m/Y'),
                $candidate->nik,
                $prefill['religion'] ?? $this->raw($raw, ['agama']),
                $prefill['home_address'] ?? $this->raw($raw, ['alamat', 'alamat rumah']),
                $prefill['rt'] ?? $this->raw($raw, ['rt']),
                $prefill['rw'] ?? $this->raw($raw, ['rw']),
                $this->raw($raw, ['dusun']),
                $this->raw($raw, ['kelurahan']),
                $this->raw($raw, ['kecamatan']),
                $this->raw($raw, ['kode pos']),
                $this->raw($raw, ['jenis tinggal']),
                $this->raw($raw, ['alat transportasi']),
                $this->raw($raw, ['telepon']),
                $prefill['phone'] ?? $this->raw($raw, ['hp', 'no hp', 'nomor hp']),
                $prefill['email'] ?? $this->raw($raw, ['e-mail', 'email']),
                $this->raw($raw, ['skhun']),
                $this->raw($raw, ['penerima kps']),
                $this->raw($raw, ['no. kps', 'no kps']),
                $candidate->source_school_name,
                $prefill['parentInfo.father_name'] ?? $this->raw($raw, ['data ayah nama']),
                $this->raw($raw, ['data ayah tahun lahir']),
                $prefill['parentInfo.father_education'] ?? $this->raw($raw, ['data ayah jenjang pendidikan', 'data ayah pendidikan']),
                $prefill['parentInfo.father_occupation'] ?? $this->raw($raw, ['data ayah pekerjaan']),
                $this->raw($raw, ['data ayah penghasilan']),
                $prefill['parentInfo.father_nik'] ?? $this->raw($raw, ['data ayah nik']),
                $prefill['parentInfo.mother_name'] ?? $this->raw($raw, ['data ibu nama']),
                $this->raw($raw, ['data ibu tahun lahir']),
                $prefill['parentInfo.mother_education'] ?? $this->raw($raw, ['data ibu jenjang pendidikan', 'data ibu pendidikan']),
                $prefill['parentInfo.mother_occupation'] ?? $this->raw($raw, ['data ibu pekerjaan']),
                $this->raw($raw, ['data ibu penghasilan']),
                $prefill['parentInfo.mother_nik'] ?? $this->raw($raw, ['data ibu nik']),
                $this->raw($raw, ['data wali nama']),
                $this->raw($raw, ['data wali tahun lahir']),
                $this->raw($raw, ['data wali jenjang pendidikan', 'data wali pendidikan']),
                $this->raw($raw, ['data wali pekerjaan']),
                $this->raw($raw, ['data wali penghasilan']),
                $this->raw($raw, ['data wali nik']),
                $candidate->is_active ? 'Ya' : 'Tidak',
            ]));
        }

        $instructionSheet = $writer->addNewSheetAndMakeItCurrent();
        $instructionSheet->setName('Petunjuk');
        $instructionSheet->setColumnWidth(32, 1);
        $instructionSheet->setColumnWidth(90, 2);

        $writer->addRow(Row::fromValues(['KOREKSI DATA TERUSAN'], $titleStyle));
        $writer->addRow(Row::fromValues(['Ketentuan', 'Keterangan'], $headerStyle));
        $writer->addRow(Row::fromValues([
            'ID Data Terusan',
            'Jangan diubah atau dikosongkan. ID ini dipakai untuk memperbarui record yang sama walaupun NIK atau tanggal lahir diganti.',
        ]));
        $writer->addRow(Row::fromValues([
            'NIK & Tanggal Lahir',
            'Boleh diperbaiki. NIK harus 16 digit dan Tanggal Lahir disarankan berformat DD/MM/YYYY.',
        ]));
        $writer->addRow(Row::fromValues([
            'Sekolah Asal',
            'Wajib diisi karena digunakan sebagai bagian dari data autofill pendaftaran.',
        ]));
        $writer->addRow(Row::fromValues([
            'Import kembali',
            'Unggah file ini melalui aksi Import Data pada menu Terusan dengan Unit dan Tahun Ajaran yang sama.',
        ]));
        $writer->addRow(Row::fromValues([
            'Keamanan',
            'ID yang tidak ditemukan, berasal dari unit/tahun ajaran lain, atau menghasilkan identitas duplikat akan dilewati dan dilaporkan sebagai kesalahan.',
        ]));

        $writer->close();

        return [
            'path' => $path,
            'filename' => $filename,
            'count' => $count,
        ];
    }

    public function download(User $actor, int $unitId, string $academicYear): BinaryFileResponse
    {
        $export = $this->export($actor, $unitId, $academicYear);

        return response()
            ->download(
                $export['path'],
                $export['filename'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ],
            )
            ->deleteFileAfterSend(true);
    }

    /** @param array<string,mixed> $raw @param list<string> $keys */
    private function raw(array $raw, array $keys): mixed
    {
        foreach ($keys as $key) {
            foreach ($raw as $rawKey => $value) {
                if (mb_strtolower(trim((string) $rawKey)) === mb_strtolower($key) && ! blank($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function academicYear(string $value): string
    {
        $value = str_replace(['–', '—', '-'], '/', trim($value));

        return preg_replace('/\s+/u', '', $value) ?? $value;
    }
}
