<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\UnitConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * A read-only, one-registration-per-row view of configured academic scores.
 *
 * The existing "Nilai Akademik" sheet remains unchanged. Column identities
 * include historical labels as well as keys so reusing a key with a different
 * meaning cannot silently mix scores from different configuration versions.
 */
final class RegistrationAcademicRecapSheet
{
    private const IDENTITY_HEADERS = [
        'No.',
        'No. Pendaftaran',
        'NIK',
        'Nama Lengkap',
        'Unit',
    ];

    /**
     * @param Collection<int, UnitConfiguration> $configurations
     */
    public function write(Writer $writer, Builder $query, Collection $configurations, Style $headerStyle): void
    {
        $columns = [];
        $unitIds = [];
        $processedConfigurations = [];

        $layoutQuery = clone $query;
        $layoutQuery->with(['academicScores', 'configuration', 'unit']);

        // Only inspect registrations matched by the original export filters.
        foreach ($layoutQuery->lazy(500) as $registration) {
            /** @var Registration $registration */
            $unitId = (int) $registration->unit_id;
            $unitName = $registration->unit?->name ?: 'Unit '.$unitId;
            $unitIds[$unitId] = true;

            $configuration = $configurations->get($registration->unit_configuration_id) ?? $registration->configuration;
            $settings = is_array($configuration?->academic_score_settings)
                ? $configuration->academic_score_settings
                : [];

            $grades = $this->labels($settings['grades'] ?? []);
            $subjects = $this->labels($settings['subjects'] ?? []);
            $assessments = $this->labels($settings['assessments'] ?? []);

            // Include configured-but-unfilled fields once for each pinned version.
            if ($configuration?->academic_scores_enabled && ! isset($processedConfigurations[$configuration->id])) {
                $processedConfigurations[$configuration->id] = true;

                foreach ($settings['grades'] ?? [] as $grade) {
                    if (! is_array($grade) || ! isset($grade['key'])) {
                        continue;
                    }

                    foreach ($settings['subjects'] ?? [] as $subject) {
                        if (! is_array($subject) || ! isset($subject['key'])) {
                            continue;
                        }

                        foreach ($settings['assessments'] ?? [] as $assessment) {
                            if (! is_array($assessment) || ! isset($assessment['key'])) {
                                continue;
                            }

                            $allowedGrades = $assessment['grade_keys'] ?? [];
                            if (is_array($allowedGrades) && $allowedGrades !== []
                                && ! in_array($grade['key'], $allowedGrades, true)) {
                                continue;
                            }

                            $column = $this->column(
                                $unitId,
                                $unitName,
                                (string) $grade['key'],
                                (string) ($grade['label'] ?? $grade['key']),
                                (string) $subject['key'],
                                (string) ($subject['label'] ?? $subject['key']),
                                (string) $assessment['key'],
                                (string) ($assessment['label'] ?? $assessment['key']),
                            );
                            $columns[$column['id']] = $column;
                        }
                    }
                }
            }

            // Keep actual saved values even if a historical configuration is missing
            // a definition, or its allowed-grade scope was changed later.
            foreach ($registration->academicScores as $score) {
                $column = $this->column(
                    $unitId,
                    $unitName,
                    (string) $score->grade_key,
                    $grades[$score->grade_key] ?? (string) $score->grade_key,
                    (string) $score->subject_key,
                    $subjects[$score->subject_key] ?? (string) $score->subject_key,
                    (string) $score->assessment_key,
                    $assessments[$score->assessment_key] ?? (string) $score->assessment_key,
                );
                $columns[$column['id']] = $column;
            }
        }

        $columns = array_values($columns);
        usort($columns, static fn (array $a, array $b): int => [
            $a['unit_name'],
            $a['unit_id'],
            $a['grade_label'],
            $a['grade_key'],
            $a['subject_label'],
            $a['subject_key'],
            $a['assessment_label'],
            $a['assessment_key'],
        ] <=> [
            $b['unit_name'],
            $b['unit_id'],
            $b['grade_label'],
            $b['grade_key'],
            $b['subject_label'],
            $b['subject_key'],
            $b['assessment_label'],
            $b['assessment_key'],
        ]);

        if (count($columns) + count(self::IDENTITY_HEADERS) > 16384) {
            throw new \RuntimeException('Rekap nilai melebihi batas 16.384 kolom Microsoft Excel.');
        }

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Rekap Nilai');
        $sheet->setSheetView((new SheetView())->setFreezeRow(4));

        $top = self::IDENTITY_HEADERS;
        $middle = array_fill(0, count(self::IDENTITY_HEADERS), null);
        $bottom = array_fill(0, count(self::IDENTITY_HEADERS), null);
        $multiUnit = count($unitIds) > 1;

        $previousGrade = null;
        $previousSubject = null;
        $displayOccurrences = [];

        foreach ($columns as $column) {
            $path = json_encode([
                $column['unit_id'],
                $column['grade_label'],
                $column['subject_label'],
                $column['assessment_label'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $displayOccurrences[$path] = ($displayOccurrences[$path] ?? 0) + 1;
        }

        foreach ($columns as $column) {
            $gradeIdentity = $this->gradeIdentity($column);
            $subjectIdentity = $this->subjectIdentity($column);
            $top[] = $gradeIdentity !== $previousGrade
                ? ($multiUnit ? $column['unit_name'].' / ' : '').$column['grade_label']
                : null;
            $middle[] = $subjectIdentity !== $previousSubject ? $column['subject_label'] : null;

            $path = json_encode([
                $column['unit_id'],
                $column['grade_label'],
                $column['subject_label'],
                $column['assessment_label'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            // Disambiguate different keys that otherwise have identical labels.
            $bottom[] = $displayOccurrences[$path] > 1
                ? $column['assessment_label'].' ['.$column['grade_key'].'/'.$column['subject_key'].'/'.$column['assessment_key'].']'
                : $column['assessment_label'];

            $previousGrade = $gradeIdentity;
            $previousSubject = $subjectIdentity;
        }

        $writer->addRow(Row::fromValues($top, $headerStyle));
        $writer->addRow(Row::fromValues($middle, $headerStyle));
        $writer->addRow(Row::fromValues($bottom, $headerStyle));

        $sheetIndex = $sheet->getIndex();
        foreach (array_keys(self::IDENTITY_HEADERS) as $index) {
            $writer->getOptions()->mergeCells($index, 1, $index, 3, $sheetIndex);
        }

        $gradeStart = 0;
        $subjectStart = 0;
        $columnCount = count($columns);

        for ($index = 1; $index <= $columnCount; $index++) {
            $gradeEnd = $index === $columnCount
                || $this->gradeIdentity($columns[$index]) !== $this->gradeIdentity($columns[$gradeStart]);
            $subjectEnd = $index === $columnCount
                || $this->subjectIdentity($columns[$index]) !== $this->subjectIdentity($columns[$subjectStart]);

            if ($subjectEnd) {
                if ($index - $subjectStart > 1) {
                    $writer->getOptions()->mergeCells(
                        count(self::IDENTITY_HEADERS) + $subjectStart,
                        2,
                        count(self::IDENTITY_HEADERS) + $index - 1,
                        2,
                        $sheetIndex,
                    );
                }
                $subjectStart = $index;
            }

            if ($gradeEnd) {
                if ($index - $gradeStart > 1) {
                    $writer->getOptions()->mergeCells(
                        count(self::IDENTITY_HEADERS) + $gradeStart,
                        1,
                        count(self::IDENTITY_HEADERS) + $index - 1,
                        1,
                        $sheetIndex,
                    );
                }
                $gradeStart = $index;
            }
        }

        foreach ([7, 22, 22, 34, 28] as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }
        foreach ($columns as $index => $column) {
            $sheet->setColumnWidth(24, count(self::IDENTITY_HEADERS) + $index + 1);
        }

        $positions = [];
        foreach ($columns as $index => $column) {
            $positions[$column['id']] = count(self::IDENTITY_HEADERS) + $index;
        }

        $dataQuery = clone $query;
        $dataQuery->with(['academicScores', 'configuration', 'unit']);

        $rowNumber = 0;
        foreach ($dataQuery->lazy(500) as $registration) {
            /** @var Registration $registration */
            $configuration = $configurations->get($registration->unit_configuration_id) ?? $registration->configuration;
            $settings = is_array($configuration?->academic_score_settings)
                ? $configuration->academic_score_settings
                : [];

            $grades = $this->labels($settings['grades'] ?? []);
            $subjects = $this->labels($settings['subjects'] ?? []);
            $assessments = $this->labels($settings['assessments'] ?? []);

            $values = array_fill(0, count(self::IDENTITY_HEADERS) + $columnCount, null);
            $values[0] = ++$rowNumber;
            $values[1] = $registration->registration_number ?: '-';
            $values[2] = $registration->nik;
            $values[3] = $registration->full_name;
            $values[4] = $registration->unit?->name ?: 'Unit '.$registration->unit_id;

            foreach ($registration->academicScores as $score) {
                $column = $this->column(
                    (int) $registration->unit_id,
                    $values[4],
                    (string) $score->grade_key,
                    $grades[$score->grade_key] ?? (string) $score->grade_key,
                    (string) $score->subject_key,
                    $subjects[$score->subject_key] ?? (string) $score->subject_key,
                    (string) $score->assessment_key,
                    $assessments[$score->assessment_key] ?? (string) $score->assessment_key,
                );

                if (isset($positions[$column['id']])) {
                    $values[$positions[$column['id']]] = (float) $score->score;
                }
            }

            $writer->addRow(Row::fromValues($values));
        }
    }

    /**
     * @return array<string, string>
     */
    private function labels(mixed $definitions): array
    {
        $labels = [];

        if (! is_array($definitions)) {
            return $labels;
        }

        foreach ($definitions as $definition) {
            if (! is_array($definition) || ! isset($definition['key'])) {
                continue;
            }

            $key = (string) $definition['key'];
            $labels[$key] = trim((string) ($definition['label'] ?? $key)) ?: $key;
        }

        return $labels;
    }

    /**
     * @return array<string, string|int>
     */
    private function column(
        int $unitId,
        string $unitName,
        string $gradeKey,
        string $gradeLabel,
        string $subjectKey,
        string $subjectLabel,
        string $assessmentKey,
        string $assessmentLabel,
    ): array {
        $gradeLabel = trim($gradeLabel) ?: $gradeKey;
        $subjectLabel = trim($subjectLabel) ?: $subjectKey;
        $assessmentLabel = trim($assessmentLabel) ?: $assessmentKey;

        return [
            'id' => json_encode([
                $unitId,
                $gradeKey, $gradeLabel,
                $subjectKey, $subjectLabel,
                $assessmentKey, $assessmentLabel,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'unit_id' => $unitId,
            'unit_name' => $unitName,
            'grade_key' => $gradeKey,
            'grade_label' => $gradeLabel,
            'subject_key' => $subjectKey,
            'subject_label' => $subjectLabel,
            'assessment_key' => $assessmentKey,
            'assessment_label' => $assessmentLabel,
        ];
    }

    private function gradeIdentity(array $column): string
    {
        return json_encode([
            $column['unit_id'], $column['grade_key'], $column['grade_label'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function subjectIdentity(array $column): string
    {
        return json_encode([
            $column['unit_id'], $column['grade_key'], $column['grade_label'],
            $column['subject_key'], $column['subject_label'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
