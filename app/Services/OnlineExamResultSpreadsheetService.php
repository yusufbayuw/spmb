<?php

namespace App\Services;

use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\OnlineExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class OnlineExamResultSpreadsheetService
{
    public const SUMMARY_HEADERS = [
        'ASSIGNMENT_UUID', 'NAMA_PESERTA', 'EMAIL', 'AKSES', 'JUMLAH_PERCOBAAN',
        'STATUS_TERAKHIR', 'PERCOBAAN_TERAKHIR', 'SKOR_TERAKHIR_PERSEN',
        'HASIL_TERAKHIR', 'MULAI_TERAKHIR', 'BATAS_WAKTU_TERAKHIR',
        'DIKUMPULKAN_TERAKHIR',
    ];

    public const ATTEMPT_HEADERS = [
        'ATTEMPT_UUID', 'ASSIGNMENT_UUID', 'NAMA_PESERTA', 'EMAIL', 'AKSES_SAAT_EXPORT',
        'PERCOBAAN_KE', 'STATUS', 'POIN_DIDAPAT', 'POIN_MAKSIMAL', 'SKOR_PERSEN',
        'HASIL', 'MULAI', 'BATAS_WAKTU', 'DIKUMPULKAN', 'SELESAI_DIKOREKSI',
    ];

    /**
     * Generate a private report scoped to one optional CBT package.
     *
     * @return array{path:string,filename:string,assignments:int,attempts:int}
     */
    public function generate(OnlineExam $exam, User $actor): array
    {
        $this->authorize($exam, $actor);

        $disk = Storage::disk('local');
        $directory = 'exports/online-exam-results';
        $disk->makeDirectory($directory);
        $relativePath = $directory.'/'.Str::uuid().'.xlsx';
        $absolutePath = $disk->path($relativePath);

        try {
            $stats = $this->writeWorkbook($exam, $actor, $absolutePath);

            app(AuditTrail::class)->record(
                'online_exam.results_exported',
                $exam,
                metadata: [
                    'assignment_count' => $stats['assignments'],
                    'attempt_count' => $stats['attempts'],
                ],
                actor: $actor,
                unitId: $exam->unit_id,
                description: 'Petugas mengekspor laporan hasil Tes Online mandiri.',
            );
        } catch (Throwable $exception) {
            $disk->delete($relativePath);
            throw $exception;
        }

        $slug = Str::slug($exam->title) ?: 'paket';
        $filename = 'hasil-cbt-'.mb_substr($slug, 0, 64).'-'.$exam->id.'.xlsx';

        return [
            'path' => $absolutePath,
            'filename' => $filename,
            ...$stats,
        ];
    }

    public function download(OnlineExam $exam, User $actor): BinaryFileResponse
    {
        $report = $this->generate($exam, $actor);

        return response()->download(
            $report['path'],
            $report['filename'],
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        )->deleteFileAfterSend(true);
    }

    /** @return array{assignments:int,attempts:int} */
    public function writeWorkbook(OnlineExam $exam, User $actor, string $path): array
    {
        $this->authorize($exam, $actor);
        $exam->loadMissing('unit');

        $writer = new Writer();
        $writer->openToFile($path);

        $assignmentCount = 0;
        $attemptCount = 0;

        try {
            $writer->getCurrentSheet()->setName('Ringkasan Peserta');
            $writer->addRow(Row::fromValues(self::SUMMARY_HEADERS));

            // Export all assignments, including unstarted and revoked ones.
            // Use bounded batches so large packages do not exhaust memory.
            OnlineExamAssignment::query()
                ->where('online_exam_id', $exam->id)
                ->with(['user:id,name,email', 'latestAttempt'])
                ->withCount('attempts')
                ->chunkById(250, function ($assignments) use ($writer, &$assignmentCount): void {
                    foreach ($assignments as $assignment) {
                        $attempt = $assignment->latestAttempt;
                        $graded = $attempt?->status === 'graded';
                        $writer->addRow(Row::fromValues([
                            $assignment->uuid,
                            $this->safeText($assignment->user?->name),
                            $this->safeText($assignment->user?->email),
                            $assignment->is_active ? 'Aktif' : 'Dicabut',
                            (int) $assignment->attempts_count,
                            $attempt?->status ?? 'belum_mulai',
                            $attempt?->attempt_no,
                            $graded ? (float) $attempt->percentage : null,
                            $graded ? ($attempt->passed ? 'Lulus' : 'Tidak lulus') : 'Belum final',
                            $attempt?->started_at?->toIso8601String(),
                            $attempt?->expires_at?->toIso8601String(),
                            $attempt?->submitted_at?->toIso8601String(),
                        ]));
                        $assignmentCount++;
                    }
                });

            $writer->addNewSheetAndMakeItCurrent()->setName('Riwayat Percobaan');
            $writer->addRow(Row::fromValues(self::ATTEMPT_HEADERS));

            // Keep the complete attempt history. Never show partial essay
            // marks until review has produced a final graded result.
            OnlineExamAttempt::query()
                ->where('online_exam_id', $exam->id)
                ->with(['user:id,name,email', 'assignment:id,uuid,is_active'])
                ->chunkById(250, function ($attempts) use ($writer, &$attemptCount): void {
                    foreach ($attempts as $attempt) {
                        $graded = $attempt->status === 'graded';
                        $writer->addRow(Row::fromValues([
                            $attempt->uuid,
                            $attempt->assignment?->uuid,
                            $this->safeText($attempt->user?->name),
                            $this->safeText($attempt->user?->email),
                            $attempt->assignment?->is_active ? 'Aktif' : 'Dicabut',
                            (int) $attempt->attempt_no,
                            $attempt->status,
                            $graded ? (float) $attempt->earned_points : null,
                            $graded ? (float) $attempt->maximum_points : null,
                            $graded ? (float) $attempt->percentage : null,
                            $graded ? ($attempt->passed ? 'Lulus' : 'Tidak lulus') : 'Belum final',
                            $attempt->started_at?->toIso8601String(),
                            $attempt->expires_at?->toIso8601String(),
                            $attempt->submitted_at?->toIso8601String(),
                            $attempt->reviewed_at?->toIso8601String(),
                        ]));
                        $attemptCount++;
                    }
                });

            $writer->addNewSheetAndMakeItCurrent()->setName('Petunjuk');
            foreach ([
                ['LAPORAN TES ONLINE (CBT) MANDIRI'],
                ['Paket', $this->safeText($exam->title)],
                ['Paket UUID', $exam->uuid],
                ['Unit', $this->safeText($exam->unit?->name)],
                ['Waktu ekspor', now()->toIso8601String()],
                ['Jumlah akun ditugaskan', $assignmentCount],
                ['Jumlah seluruh percobaan', $attemptCount],
                [],
                ['Cakupan', 'Satu paket dan unit. Ringkasan termasuk peserta belum mulai dan akses dicabut.'],
                ['Riwayat', 'Memuat semua percobaan, bukan hanya percobaan terakhir.'],
                ['Skor', 'Hanya muncul ketika graded. Kolom kosong bukan nilai nol.'],
                ['Status', 'in_progress yang melewati tenggat dapat menunggu finalisasi scheduler.'],
                ['Privasi', 'Nama dan email peserta adalah informasi rahasia. Simpan file secara aman.'],
                ['Keamanan', 'Tidak menyertakan soal, jawaban peserta, atau kunci jawaban.'],
                ['SPMB', 'CBT adalah fasilitas opsional; ekspor tidak mengubah alur pendaftaran/seleksi.'],
                ['Catatan', 'Laporan tidak bisa diimpor sebagai nilai seleksi.'],
            ] as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        } finally {
            $writer->close();
        }

        return ['assignments' => $assignmentCount, 'attempts' => $attemptCount];
    }

    private function authorize(OnlineExam $exam, User $actor): void
    {
        abort_unless(app(OnlineExamAccess::class)->canManage($actor, $exam), 403);
    }

    // Protect viewers that handle XLSX text less strictly than OpenSpout.
    private function safeText(?string $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^\s*[=+\-@]/u', $text) === 1 ? "'".$text : $text;
    }
}
