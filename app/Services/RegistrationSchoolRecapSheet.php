<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * One row per normalized school, aggregated from precisely the registrations
 * selected by the main export query. All source data remains untouched.
 */
final class RegistrationSchoolRecapSheet
{
    public function write(
        Writer $writer,
        Builder $query,
        Style $headerStyle,
        Style $titleStyle,
        SchoolNameNormalizer $normalizer,
    ): void {
        $groups = [];
        $total = 0;

        // Do not let the admin table's eager-loaded relationships inflate
        // memory for a recap that needs only the original school name.
        $dataQuery = clone $query;
        $dataQuery->withoutEagerLoads();

        foreach ($dataQuery->lazy(500) as $registration) {
            $name = $normalizer->normalize($registration->previous_school);
            $key = 'school:'.$name;

            if (! isset($groups[$key])) {
                $groups[$key] = ['name' => $name, 'count' => 0];
            }

            $groups[$key]['count']++;
            $total++;
        }

        $groups = array_values($groups);
        usort($groups, static fn (array $a, array $b): int => ($b['count'] <=> $a['count'])
            ?: strcmp($a['name'], $b['name']));

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('rekap_sekolah');
        $sheet->setSheetView((new SheetView())->setFreezeRow(5));

        foreach ([9, 52, 22, 20] as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }

        $writer->addRow(Row::fromValues(
            ['REKAPITULASI JUMLAH PENDAFTAR BERDASARKAN SEKOLAH ASAL'],
            $titleStyle,
        ));
        $writer->addRow(Row::fromValues([
            'Total Pendaftar: '.$total.' Siswa | Data Terstandarisasi (hanya untuk rekap)',
        ]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'No',
            'Sekolah Asal (Terstandarisasi)',
            'Jumlah Pendaftar',
            'Persentase (%)',
        ], $headerStyle));

        $percentageStyle = (new Style())->setFormat('0.00%');

        foreach ($groups as $index => $group) {
            $writer->addRow(new Row([
                Cell::fromValue($index + 1),
                Cell::fromValue($group['name']),
                Cell::fromValue($group['count']),
                Cell::fromValue($group['count'] / $total, $percentageStyle),
            ]));
        }

        $writer->getOptions()->mergeCells(0, 1, 3, 1, $sheet->getIndex());
        $writer->getOptions()->mergeCells(0, 2, 3, 2, $sheet->getIndex());
        $sheet->setAutoFilter(new AutoFilter(0, 4, 3, max(4, count($groups) + 4)));
    }
}
