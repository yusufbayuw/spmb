<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperationalReportService
{
    public function query(User $staff, array $filters = []): Builder
    {
        return Registration::query()
            ->with(['unit', 'opening.studyProgram', 'pathway', 'latestPayment', 'selection'])
            ->when($staff->isTU(), fn (Builder $q) => $q->where('registrations.unit_id', $staff->unit_id))
            ->when(! $staff->isTU() && filled($filters['unit_id'] ?? null), fn (Builder $q) => $q->where('registrations.unit_id', $filters['unit_id']))
            ->when(filled($filters['study_program_id'] ?? null), fn (Builder $q) => $q->whereHas('opening', fn (Builder $opening) => $opening->where('study_program_id', $filters['study_program_id'])))
            ->when(filled($filters['registration_opening_id'] ?? null), fn (Builder $q) => $q->where('registrations.registration_opening_id', $filters['registration_opening_id']))
            ->when(filled($filters['current_stage'] ?? null), fn (Builder $q) => $q->where('registrations.current_stage', $filters['current_stage']))
            ->when(filled($filters['lifecycle_status'] ?? null), fn (Builder $q) => $q->where('registrations.lifecycle_status', $filters['lifecycle_status']))
            ->when(filled($filters['payment_status'] ?? null), fn (Builder $q) => $q->whereHas('latestPayment', fn (Builder $p) => $p->where('status', $filters['payment_status'])))
            ->when(filled($filters['decision'] ?? null), fn (Builder $q) => $q->whereHas('selection', fn (Builder $s) => $s->where('decision', $filters['decision'])))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $q) => $q->whereDate('registrations.created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_until'] ?? null), fn (Builder $q) => $q->whereDate('registrations.created_at', '<=', $filters['date_until']));
    }

    public function summary(User $staff, array $filters = []): array
    {
        $base = $this->query($staff, $filters);
        $ids = (clone $base)->select('registrations.id');

        $expectedFee = (float) (clone $base)
            ->leftJoin('registration_openings', 'registration_openings.id', '=', 'registrations.registration_opening_id')
            ->sum('registration_openings.registration_fee');

        $verifiedRevenue = (float) Payment::query()
            ->whereIn('registration_id', $ids)
            ->where('status', 'verified')
            ->sum('amount');

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('registrations.lifecycle_status', 'active')->count(),
            'completed' => (clone $base)->where('registrations.current_stage', 'completed')->count(),
            'accepted' => (clone $base)->where('registrations.status', 'accepted')->count(),
            'payment_verified' => (clone $base)->whereHas('latestPayment', fn (Builder $q) => $q->where('status', 'verified'))->count(),
            'expected_fee' => $expectedFee,
            'verified_revenue' => $verifiedRevenue,
        ];
    }

    public function preview(User $staff, array $filters = [], int $limit = 100)
    {
        return $this->query($staff, $filters)
            ->latest('registrations.created_at')
            ->limit($limit)
            ->get();
    }

    public function download(User $staff, array $filters = []): BinaryFileResponse
    {
        $storedPath = 'exports/operational/'.Str::uuid().'.xlsx';
        Storage::disk('local')->makeDirectory('exports/operational');
        $absolutePath = Storage::disk('local')->path($storedPath);

        $writer = new Writer;
        $writer->openToFile($absolutePath);
        $writer->getCurrentSheet()->setName('Pendaftaran');
        $writer->addRow(Row::fromValues([
            'No. Registrasi', 'Peserta', 'NIK', 'Unit / Institusi', 'Jenis Institusi', 'Program Studi', 'Jenjang',
            'Tahun Ajaran', 'Gelombang', 'Jalur', 'Biaya Pendaftaran', 'Lifecycle', 'Tahap', 'Validasi',
            'VA', 'Status Pembayaran', 'Nominal Pembayaran', 'Keputusan Seleksi', 'Tanggal Daftar',
        ]));

        $this->query($staff, $filters)
            ->orderBy('registrations.id')
            ->chunkById(500, function ($registrations) use ($writer): void {
                foreach ($registrations as $registration) {
                    $writer->addRow(Row::fromValues([
                        $registration->registration_number,
                        $registration->full_name,
                        $registration->nik,
                        $registration->unit?->name,
                        $registration->unit?->institutionTypeLabel(),
                        $registration->opening?->studyProgram?->name,
                        $registration->opening?->studyProgram?->degree_level,
                        $registration->opening?->academic_year,
                        $registration->opening?->wave,
                        $registration->pathway?->name,
                        (float) ($registration->opening?->registration_fee ?? 0),
                        $registration->lifecycleLabel(),
                        $registration->stageLabel(),
                        $registration->data_validation_status,
                        $registration->latestPayment?->va_number,
                        $registration->latestPayment?->status,
                        (float) ($registration->latestPayment?->amount ?? 0),
                        $registration->selection?->decision,
                        $registration->created_at?->format('Y-m-d H:i:s'),
                    ]));
                }
            }, column: 'registrations.id', alias: 'id');

        $summary = $this->summary($staff, $filters);
        $summarySheet = $writer->addNewSheetAndMakeItCurrent();
        $summarySheet->setName('Ringkasan');
        foreach ([
            ['Total Pendaftaran', $summary['total']],
            ['Aktif', $summary['active']],
            ['Selesai', $summary['completed']],
            ['Diterima', $summary['accepted']],
            ['Pembayaran Terverifikasi', $summary['payment_verified']],
            ['Potensi Biaya', $summary['expected_fee']],
            ['Pembayaran Terverifikasi (Rp)', $summary['verified_revenue']],
        ] as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        app(AuditTrail::class)->record(
            'report.operational_exported',
            metadata: ['filters' => $filters, 'rows' => $summary['total']],
            actor: $staff,
            unitId: $staff->isTU() ? $staff->unit_id : null,
            description: 'Laporan operasional penerimaan diekspor ke XLSX',
        );

        return response()
            ->download(
                $absolutePath,
                'laporan-operasional-penerimaan-'.now()->format('Ymd-His').'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }
}
