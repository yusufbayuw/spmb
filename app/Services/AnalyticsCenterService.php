<?php

namespace App\Services;

use App\Models\AdmissionOffer;
use App\Models\AdmissionQuota;
use App\Models\AdmissionTestResult;
use App\Models\AuditLog;
use App\Models\ContinuationRegistrationLink;
use App\Models\Document;
use App\Models\MailDeliveryAttempt;
use App\Models\OnlineExamAttempt;
use App\Models\ParentInfo;
use App\Models\Payment;
use App\Models\ReRegistrationItem;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Selection;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsCenterService
{
    /**
     * Single, read-only scope for every admissions metric. Never trust a
     * browser-supplied unit ID: admin_unit and TU always use their own unit.
     * Unlike the operational-only registration scope, lifecycle can be chosen
     * explicitly so archived and withdrawn records can be analysed.
     */
    public function registrations(User $viewer, array $filters = []): Builder
    {
        abort_unless(
            $viewer->is_active
                && $viewer->hasAnyRole(['super_admin', 'admin_unit', 'tu'])
                && $viewer->hasOperationalUnitAccess(),
            403
        );

        $query = Registration::query()
            ->whereHas('unit', fn (Builder $q): Builder => $q->operational());

        if ($viewer->isTU()) {
            $query->where('registrations.unit_id', $viewer->unit_id);
        } elseif (filled($filters['unit_id'] ?? null)) {
            $query->where('registrations.unit_id', (int) $filters['unit_id']);
        }

        $lifecycle = $filters['lifecycle'] ?? 'active';
        if ($lifecycle !== 'all') {
            $query->where('registrations.lifecycle_status',
                array_key_exists((string) $lifecycle, Registration::LIFECYCLE_STATUSES)
                    ? $lifecycle : 'active'
            );
        }

        if (filled($filters['academic_year'] ?? null)) {
            $query->whereHas('opening', fn (Builder $q): Builder => $q
                ->where('academic_year', $filters['academic_year']));
        }

        if (filled($filters['registration_opening_id'] ?? null)) {
            $query->where('registrations.registration_opening_id', (int) $filters['registration_opening_id']);
        }

        if (filled($filters['registration_pathway_id'] ?? null)) {
            $query->where('registrations.registration_pathway_id', (int) $filters['registration_pathway_id']);
        }

        if (filled($filters['study_program_id'] ?? null)) {
            $query->whereHas('opening', fn (Builder $q): Builder => $q
                ->where('study_program_id', (int) $filters['study_program_id']));
        }

        foreach (['date_from' => '>=', 'date_until' => '<='] as $key => $operator) {
            $date = $filters[$key] ?? null;
            if ($this->validDate($date)) {
                $query->whereDate('registrations.created_at', $operator, $date);
            }
        }

        return $query;
    }

    private function validDate(mixed $date): bool
    {
        return is_string($date)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
            && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
    }

    public function report(User $viewer, array $filters, string $section): array
    {
        $base = $this->registrations($viewer, $filters);

        return match ($section) {
            'registration' => $this->registrationReport($base),
            'profile' => $this->profileReport($base),
            'schools' => $this->schoolReport($base),
            'finance' => $this->financeReport($base, $viewer, $filters),
            'documents' => $this->documentReport($base),
            'testing' => $this->testingReport($base, $viewer, $filters),
            'selection' => $this->selectionReport($base, $viewer, $filters),
            'post-admission' => $this->postAdmissionReport($base),
            'operations' => $this->operationsReport($base),
            'explorer' => $this->explorerReport($base, $filters),
            default => $this->overviewReport($base, $viewer),
        };
    }

    public function chart(User $viewer, array $filters, string $section): array
    {
        // Uses the same security scope and definitions as the figures and
        // breakdown tables, avoiding chart/table inconsistencies.
        $report = $this->report($viewer, $filters, $section);
        $first = collect($report['groups'])->first(fn (array $group): bool => count($group['rows']) > 0);
        $rows = array_slice($first['rows'] ?? [], 0, 12);

        return [
            'heading' => $first['title'] ?? 'Distribusi data',
            'labels' => array_column($rows, 'label') ?: ['Belum ada data'],
            'values' => array_column($rows, 'count') ?: [0],
        ];
    }

    private function overviewReport(Builder $base, User $viewer): array
    {
        $total = (clone $base)->count();
        $validation = (clone $base)->where('registrations.current_stage', 'data_validation')
            ->whereIn('registrations.data_validation_status', ['pending', 'revision'])->count();
        $payments = $this->payments($base);
        $documents = $this->documents($base);
        $verified = (clone $base)->whereHas('payments', fn (Builder $q): Builder => $q
            ->where('status', 'verified'))->count();
        $uploadedProof = (clone $payments)->where('status', 'paid')->count();
        $pendingDocuments = (clone $documents)->where('is_verified', false)
            ->whereNull('rejection_reason')->count();

        $groups = [
            $this->group('Tahapan pendaftar saat ini',
                $this->distribution($base, 'registrations.current_stage', Registration::STAGES),
                'Distribusi posisi terkini, bukan riwayat perpindahan tahap.'),
        ];

        if (! $viewer->isTU()) {
            $groups[] = $this->group('Sebaran per unit', $this->byUnit($base));
        }

        return $this->pack([
            $this->metric('Total pendaftaran', $total, 'Sesuai filter dan hak akses'),
            $this->metric('Perlu validasi data', $validation, 'Antrean tahap validasi', 'warning'),
            $this->metric('Bukti bayar menunggu', $uploadedProof, 'Transaksi yang perlu diverifikasi', 'warning'),
            $this->metric('Berkas menunggu', $pendingDocuments, 'Jumlah berkas, bukan jumlah peserta', 'warning'),
            $this->metric('Pernah bayar terverifikasi', $verified, 'Peserta unik dengan transaksi terverifikasi', 'success'),
            $this->metric('Sudah enrollment', (clone $base)->where('registrations.status', 'enrolled')->count()),
        ], $groups, [
            'Klik menu operasional terkait untuk menindaklanjuti antrean. Angka ini tidak menggantikan keputusan petugas.',
            'Tahap yang dinonaktifkan dalam konfigurasi unit tidak diwajibkan dalam perhitungan konversi.',
        ]);
    }

    private function registrationReport(Builder $base): array
    {
        $total = (clone $base)->count();
        $validated = (clone $base)->where('registrations.data_validation_status', 'valid')->count();
        $recent = (clone $base)->where('registrations.created_at', '>=', now()->subDays(30))->count();
        $completed = (clone $base)->where('registrations.current_stage', 'completed')->count();

        $byYear = (clone $base)
            ->join('registration_openings as analytics_openings', 'analytics_openings.id', '=', 'registrations.registration_opening_id')
            ->selectRaw('analytics_openings.academic_year as bucket, COUNT(*) as aggregate')
            ->groupBy('analytics_openings.academic_year')->orderByDesc('aggregate')->limit(20)->get();

        $trend = (clone $base)->where('registrations.created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(registrations.created_at) as bucket, COUNT(*) as aggregate')
            ->groupByRaw('DATE(registrations.created_at)')->orderBy('bucket')->limit(30)->get();

        return $this->pack([
            $this->metric('Pendaftaran', $total),
            $this->metric('Validasi disetujui', $validated),
            $this->metric('Rasio validasi', $this->rate($validated, $total), 'Validasi valid / seluruh pendaftaran'),
            $this->metric('Pendaftaran 30 hari', $recent),
            $this->metric('Tahap selesai', $completed, 'Posisi saat ini, bukan seluruh riwayat'),
        ], [
            $this->group('Distribusi tahapan', $this->distribution($base, 'registrations.current_stage', Registration::STAGES)),
            $this->group('Menurut tahun ajaran', $this->rows($byYear, $total)),
            $this->group('Pendaftar harian (30 hari terakhir)', $this->rows($trend, $recent)),
        ], [
            'Ini adalah sebaran tahap saat ini. Funnel historis dan durasi setiap perpindahan tahap tidak disimpulkan dari snapshot status.',
        ]);
    }

    private function profileReport(Builder $base): array
    {
        $total = (clone $base)->count();
        $parents = ParentInfo::query()->whereIn('registration_id', $this->ids($base));

        return $this->pack([
            $this->metric('Pendaftaran', $total),
            $this->metric('Memiliki data orang tua', (clone $parents)->count()),
            $this->metric('Tanpa relasi orang tua', (clone $base)->whereDoesntHave('parentInfo')->count(),
                'Belum tentu wajib pada setiap konfigurasi'),
            $this->metric('Wilayah asal terisi', (clone $base)->whereNotNull('registrations.province_code')->count()),
        ], [
            $this->group('Jenis kelamin', $this->distribution($base, 'registrations.gender', [
                'L' => 'Laki-laki', 'P' => 'Perempuan',
                'male' => 'Laki-laki', 'female' => 'Perempuan',
            ])),
            $this->group('Provinsi asal', $this->distribution($base, 'registrations.province', [], 20)),
            $this->group('Pendidikan ayah (agregat)', $this->distribution($parents, 'father_education', [], 15)),
            $this->group('Pendidikan ibu (agregat)', $this->distribution($parents, 'mother_education', [], 15)),
        ], [
            'Profil dan keluarga ditampilkan dalam agregat, bukan NIK, kontak, atau penghasilan individu.',
            'Isian demografi yang tidak diwajibkan oleh konfigurasi unit dapat tidak tersedia.',
        ]);
    }

    private function schoolReport(Builder $base): array
    {
        $total = (clone $base)->count();
        $school = "COALESCE(NULLIF(UPPER(TRIM(registrations.previous_school)), ''), 'Tidak diisi')";
        $schoolCounts = (clone $base)->selectRaw($school.' as bucket, COUNT(*) as aggregate')
            ->groupByRaw($school)->orderByDesc('aggregate')->limit(20)->get();
        $linked = ContinuationRegistrationLink::query()
            ->whereIn('registration_id', $this->ids($base))
            ->distinct()->count('registration_id');

        return $this->pack([
            $this->metric('Pendaftaran', $total),
            $this->metric('Asal sekolah terisi', (clone $base)
                ->whereNotNull('registrations.previous_school')
                ->whereRaw("TRIM(registrations.previous_school) <> ''")->count()),
            $this->metric('Terhubung data terusan', $linked,
                'Peserta unik yang punya hubungan terusan/alumni'),
            $this->metric('Persentase terusan', $this->rate($linked, $total)),
        ], [
            $this->group('Asal sekolah terbanyak', $this->rows($schoolCounts, $total),
                'Pengelompokan nama menggunakan uppercase dan trim; variasi ejaan mungkin masih berbeda.'),
            $this->group('Wilayah kabupaten/kota', $this->distribution($base, 'registrations.city', [], 20)),
            $this->group('Jalur pendaftaran', $this->byPathway($base)),
        ], [
            'Penghitungan terusan memakai hubungan data terusan yang tercatat, bukan tebakan dari kesamaan nama.',
            'Sekolah asal yang tidak diisi tetap ditampilkan sebagai kategori tersendiri.',
        ]);
    }

    private function financeReport(Builder $base, User $viewer, array $filters): array
    {
        $payments = $this->payments($base);
        $verified = (clone $payments)->where('status', 'verified');
        $totalTransactions = (clone $payments)->count();

        $groups = [
            $this->group('Status transaksi pembayaran',
                $this->distribution($payments, 'status', [
                    'pending' => 'Menunggu', 'paid' => 'Bukti diunggah',
                    'verified' => 'Terverifikasi', 'rejected' => 'Ditolak',
                ])),
        ];
        // Available VA numbers are not bound to an opening or pathway. Show
        // unit-level stock only when these more granular filters are absent.
        $showPool = blank($filters['registration_opening_id'] ?? null)
            && blank($filters['registration_pathway_id'] ?? null)
            && blank($filters['study_program_id'] ?? null);
        if ($showPool) {
            $pool = $this->virtualAccounts($viewer, $filters);
            $groups[] = $this->group('Pool VA menurut status (cakupan unit)',
                $this->distribution($pool, 'status', VirtualAccount::STATUSES),
                'Pool VA belum dialokasikan pada pembukaan tertentu.');
        }

        return $this->pack([
            $this->metric('Transaksi pembayaran', $totalTransactions, 'Jumlah transaksi, tidak selalu peserta unik'),
            $this->metric('Bukti menunggu verifikasi', (clone $payments)->where('status', 'paid')->count(), '', 'warning'),
            $this->metric('Transaksi terverifikasi', (clone $verified)->count(), '', 'success'),
            $this->metric('Nominal terverifikasi', $this->rupiah((float) (clone $verified)->sum('amount')),
                'Bukti administrasi terverifikasi, bukan rekonsiliasi bank'),
            $this->metric('Transaksi ditolak', (clone $payments)->where('status', 'rejected')->count()),
        ], $groups, [
            'Nominal terverifikasi bukan bukti settlement rekening bank.',
            $showPool ? 'Pool VA mengikuti unit dan tahun ajaran, tidak dikurangi oleh filter lifecycle/pendaftar.'
                : 'Pool VA tidak ditampilkan karena filter pembukaan, prodi, atau jalur tidak berlaku untuk seluruh stok VA.',
        ]);
    }

    private function documentReport(Builder $base): array
    {
        $documents = $this->documents($base);
        $total = (clone $documents)->count();
        $types = "COALESCE(NULLIF(documents.requirement_key, ''), documents.type)";
        $grouped = (clone $documents)->selectRaw($types.' as bucket, COUNT(*) as aggregate')
            ->groupByRaw($types)->orderByDesc('aggregate')->limit(20)->get();

        return $this->pack([
            $this->metric('Berkas terunggah', $total, 'Record terbaru, berkas lama yang diganti dikecualikan'),
            $this->metric('Berkas terverifikasi', (clone $documents)->where('is_verified', true)->count(), '', 'success'),
            $this->metric('Menunggu pemeriksaan', (clone $documents)->where('is_verified', false)
                ->whereNull('rejection_reason')->count(), '', 'warning'),
            $this->metric('Berkas ditolak', (clone $documents)->whereNotNull('rejection_reason')->count(), '', 'danger'),
            $this->metric('Peserta melengkapi berkas', (clone $base)
                ->whereNotNull('registrations.documents_completed_at')->count()),
        ], [
            $this->group('Status pemeriksaan berkas', [
                $this->row('Terverifikasi', (clone $documents)->where('is_verified', true)->count(), $total),
                $this->row('Ditolak', (clone $documents)->whereNotNull('rejection_reason')->count(), $total),
                $this->row('Menunggu', (clone $documents)->where('is_verified', false)
                    ->whereNull('rejection_reason')->count(), $total),
            ]),
            $this->group('Kategori berkas terunggah', $this->rows($grouped, $total)),
        ], [
            'Jumlah berkas tidak sama dengan jumlah pendaftar. Syarat berkas dapat berbeda menurut versi konfigurasi setiap pendaftar.',
            'Berkas yang digantikan dikecualikan agar tidak menggandakan hasil.',
        ]);
    }

    private function testingReport(Builder $base, User $viewer, array $filters): array
    {
        $results = AdmissionTestResult::query()->whereIn('registration_id', $this->ids($base));

        // CBT remains optional and separate from the admission funnel. Its
        // attempts are only counted if BOTH the applicant and exam unit match
        // the accessible scope. A single user can hold several registrations,
        // so attempts are not presented as opening-specific results.
        $attempts = OnlineExamAttempt::query()
            ->whereIn('user_id', (clone $base)->whereNotNull('registrations.user_id')
                ->select('registrations.user_id'))
            ->whereHas('exam', function (Builder $exam) use ($viewer, $filters): void {
                $exam->whereHas('unit', fn (Builder $unit): Builder => $unit->operational());
                if ($viewer->isTU()) {
                    $exam->where('unit_id', $viewer->unit_id);
                } elseif (filled($filters['unit_id'] ?? null)) {
                    $exam->where('unit_id', (int) $filters['unit_id']);
                }
            });

        $total = (clone $results)->count();
        $graded = (clone $attempts)->where('status', 'graded');

        return $this->pack([
            $this->metric('Hasil tes penerimaan', $total, 'Catatan hasil tes yang terkait pendaftaran'),
            $this->metric('Rata-rata nilai tes', (clone $results)->whereNotNull('score')->count()
                ? number_format((float) (clone $results)->whereNotNull('score')->avg('score'), 1, ',', '.') : '—'),
            $this->metric('Percobaan CBT', (clone $attempts)->count(), 'CBT opsional, bisa lebih dari satu percobaan'),
            $this->metric('CBT dinilai', (clone $graded)->count()),
            $this->metric('CBT lulus', (clone $graded)->where('passed', true)->count()),
        ], [
            $this->group('Status hasil tes', $this->distribution($results, 'status')),
            $this->group('Hasil tes', $this->distribution($results, 'result')),
            $this->group('Status percobaan CBT', $this->distribution($attempts, 'status')),
        ], [
            'Unit tanpa tes/CBT tidak dianggap gagal atau tertinggal; tes bersifat opsional sesuai konfigurasi.',
            'Percobaan CBT dihitung berdasarkan peserta dan unit ujian terkait. Penyaringan gelombang/pembukaan tidak dapat menjamin atribusi setiap percobaan CBT.',
        ]);
    }

    private function selectionReport(Builder $base, User $viewer, array $filters): array
    {
        $selections = Selection::query()->whereIn('registration_id', $this->ids($base));
        $quota = AdmissionQuota::query()->where('is_active', true)
            ->whereHas('opening', function (Builder $opening) use ($viewer, $filters): void {
                $opening->operational();
                if ($viewer->isTU()) {
                    $opening->where('unit_id', $viewer->unit_id);
                } elseif (filled($filters['unit_id'] ?? null)) {
                    $opening->where('unit_id', (int) $filters['unit_id']);
                }
                if (filled($filters['academic_year'] ?? null)) {
                    $opening->where('academic_year', $filters['academic_year']);
                }
                if (filled($filters['registration_opening_id'] ?? null)) {
                    $opening->whereKey((int) $filters['registration_opening_id']);
                }
                if (filled($filters['study_program_id'] ?? null)) {
                    $opening->where('study_program_id', (int) $filters['study_program_id']);
                }
            });
        if (filled($filters['registration_pathway_id'] ?? null)) {
            $quota->where('registration_pathway_id', (int) $filters['registration_pathway_id']);
        }

        $accepted = (clone $selections)->where('decision', 'accepted')->count();

        return $this->pack([
            $this->metric('Keputusan seleksi', (clone $selections)->count()),
            $this->metric('Diterima seleksi', $accepted, 'Keputusan diterima, bukan kursi final'),
            $this->metric('Daftar tunggu', (clone $selections)->where('decision', 'waiting_list')->count()),
            $this->metric('Total kuota aktif', (clone $quota)->sum('capacity'), 'Menurut pembukaan dan jalur yang relevan'),
            $this->metric('Rasio diterima vs kuota', $this->rate($accepted, (clone $quota)->sum('capacity')),
                'Indikatif: keputusan diterima / kuota, bukan okupansi kursi'),
        ], [
            $this->group('Distribusi keputusan', $this->distribution($selections, 'decision', [
                'accepted' => 'Diterima', 'rejected' => 'Ditolak',
                'waiting_list' => 'Daftar tunggu', 'pending' => 'Menunggu',
            ])),
            $this->group('Status seleksi pendaftar', $this->distribution($base, 'registrations.status')),
        ], [
            'Kuota mengikuti konfigurasi pembukaan/jalur; angka diterima belum dikurangi penawaran yang dibatalkan atau tidak diambil.',
            'Jalur tanpa kuota aktif tidak otomatis berarti kuota nol untuk seluruh proses.',
        ]);
    }

    private function postAdmissionReport(Builder $base): array
    {
        $offers = AdmissionOffer::query()->whereIn('registration_id', $this->ids($base));
        $items = ReRegistrationItem::query()->whereIn('registration_id', $this->ids($base));
        $total = (clone $offers)->count();

        return $this->pack([
            $this->metric('Penawaran penerimaan', $total),
            $this->metric('Penawaran diterima', (clone $offers)->where('status', 'accepted')->count(), '', 'success'),
            $this->metric('Penawaran ditolak', (clone $offers)->where('status', 'declined')->count()),
            $this->metric('Daftar ulang selesai', (clone $base)
                ->whereNotNull('registrations.re_registration_completed_at')->count()),
            $this->metric('Enrollment', (clone $base)->where('registrations.status', 'enrolled')->count()),
        ], [
            $this->group('Status penawaran', $this->distribution($offers, 'status')),
            $this->group('Status item daftar ulang', $this->distribution($items, 'status')),
        ], [
            'Pasca-penerimaan hanya dinilai pada pembukaan yang mengaktifkan alur tersebut; tidak diwajibkan untuk unit K12.',
            'Item daftar ulang dapat berjumlah lebih dari satu per pendaftar.',
        ]);
    }

    private function operationsReport(Builder $base): array
    {
        $payments = $this->payments($base);
        $docs = $this->documents($base);
        $emails = MailDeliveryAttempt::query()->whereIn('registration_id', $this->ids($base));
        $logs = AuditLog::query()->whereIn('registration_id', $this->ids($base));

        return $this->pack([
            $this->metric('Validasi perlu tindakan', (clone $base)
                ->where('registrations.current_stage', 'data_validation')
                ->whereIn('registrations.data_validation_status', ['pending', 'revision'])->count(),
                '', 'warning'),
            $this->metric('Bukti bayar perlu verifikasi', (clone $payments)->where('status', 'paid')->count(), '', 'warning'),
            $this->metric('Berkas perlu verifikasi', (clone $docs)->where('is_verified', false)
                ->whereNull('rejection_reason')->count(), '', 'warning'),
            $this->metric('Data orang tua belum tercatat', (clone $base)->whereDoesntHave('parentInfo')->count(),
                'Perlu ditinjau terhadap konfigurasi, bukan otomatis kesalahan'),
            $this->metric('Upaya pengiriman email', (clone $emails)->count(), 'Hanya email yang terkait pendaftaran'),
        ], [
            $this->group('Status pengiriman email', $this->distribution($emails, 'status')),
            $this->group('Jejak audit terkait pendaftar', $this->distribution($logs, 'event', [], 15)),
        ], [
            'Audit yang tidak terkait langsung ke suatu pendaftaran tidak dimasukkan; informasi ini bukan audit keamanan lengkap.',
            'Analisis SLA/durasi historis belum dinyatakan akurat sebelum ketersediaan riwayat tiap perpindahan tahap diverifikasi.',
        ]);
    }

    private function explorerReport(Builder $base, array $filters): array
    {
        $dimension = $filters['dimension'] ?? 'unit';
        $rows = match ($dimension) {
            'stage' => $this->distribution($base, 'registrations.current_stage', Registration::STAGES, 100),
            'status' => $this->distribution($base, 'registrations.status', [], 100),
            'gender' => $this->distribution($base, 'registrations.gender', [
                'L' => 'Laki-laki', 'P' => 'Perempuan',
            ], 100),
            'province' => $this->distribution($base, 'registrations.province', [], 100),
            'school' => $this->schoolRows($base, 100),
            'pathway' => $this->byPathway($base, 100),
            'year' => $this->byOpeningField($base, 'academic_year', 100),
            'wave' => $this->byOpeningField($base, 'wave', 100),
            default => $this->byUnit($base, 100),
        };

        return $this->pack([
            $this->metric('Pendaftaran dalam cakupan', (clone $base)->count()),
            $this->metric('Kategori ditampilkan', count($rows)),
            $this->metric('Dimensi aktif', [
                'unit' => 'Unit', 'stage' => 'Tahap', 'status' => 'Status', 'gender' => 'Jenis kelamin',
                'province' => 'Provinsi', 'school' => 'Sekolah asal',
                'pathway' => 'Jalur', 'year' => 'Tahun ajaran', 'wave' => 'Gelombang',
            ][$dimension] ?? 'Unit'),
        ], [
            $this->group('Distribusi menurut dimensi', $rows,
                'Gunakan pilihan dimensi di filter untuk mengganti sudut analisis.'),
        ], [
            'Eksplorasi ini agregat dan tidak membuka data pribadi lintas-unit.',
            'Ekspor detail tetap menggunakan menu Laporan Operasional yang sudah ada.',
        ]);
    }

    private function documents(Builder $base): Builder
    {
        return Document::query()->whereNull('documents.superseded_at')
            ->whereIn('documents.registration_id', $this->ids($base));
    }

    private function payments(Builder $base): Builder
    {
        return Payment::query()->whereIn('registration_id', $this->ids($base));
    }

    private function ids(Builder $base): Builder
    {
        return (clone $base)->select('registrations.id');
    }

    private function virtualAccounts(User $viewer, array $filters): Builder
    {
        $query = VirtualAccount::query()
            ->whereHas('unit', fn (Builder $q): Builder => $q->operational());

        if ($viewer->isTU()) {
            $query->where('unit_id', $viewer->unit_id);
        } elseif (filled($filters['unit_id'] ?? null)) {
            $query->where('unit_id', (int) $filters['unit_id']);
        }

        return $query;
    }

    private function byUnit(Builder $base, int $limit = 20): array
    {
        $total = (clone $base)->count();
        $names = Unit::query()->operational()->pluck('name', 'id');
        $records = (clone $base)->selectRaw('registrations.unit_id as bucket, COUNT(*) as aggregate')
            ->groupBy('registrations.unit_id')->orderByDesc('aggregate')->limit($limit)->get();

        return $this->rows($records, $total, $names->all());
    }

    private function byPathway(Builder $base, int $limit = 20): array
    {
        $total = (clone $base)->count();
        $names = RegistrationPathway::query()
            ->whereIn('id', (clone $base)->whereNotNull('registrations.registration_pathway_id')
                ->select('registrations.registration_pathway_id'))
            ->pluck('name', 'id')->all();
        $records = (clone $base)->selectRaw('registrations.registration_pathway_id as bucket, COUNT(*) as aggregate')
            ->groupBy('registrations.registration_pathway_id')->orderByDesc('aggregate')->limit($limit)->get();

        return $this->rows($records, $total, $names);
    }

    private function byOpeningField(Builder $base, string $column, int $limit = 20): array
    {
        // The column is never interpolated from user input.
        abort_unless(in_array($column, ['academic_year', 'wave'], true), 400);

        $total = (clone $base)->count();
        $records = (clone $base)
            ->join('registration_openings as analytics_openings', 'analytics_openings.id', '=', 'registrations.registration_opening_id')
            ->selectRaw("analytics_openings.{$column} as bucket, COUNT(*) as aggregate")
            ->groupBy("analytics_openings.{$column}")->orderByDesc('aggregate')->limit($limit)->get();

        return $this->rows($records, $total);
    }

    private function schoolRows(Builder $base, int $limit = 20): array
    {
        $total = (clone $base)->count();
        $normal = "COALESCE(NULLIF(UPPER(TRIM(registrations.previous_school)), ''), 'Tidak diisi')";
        $records = (clone $base)->selectRaw($normal.' as bucket, COUNT(*) as aggregate')
            ->groupByRaw($normal)->orderByDesc('aggregate')->limit($limit)->get();

        return $this->rows($records, $total);
    }

    private function distribution(Builder $query, string $column, array $labels = [], int $limit = 15): array
    {
        // Callers provide only fixed, server-defined column expressions.
        $total = (clone $query)->count();
        $rows = (clone $query)->selectRaw("{$column} as bucket, COUNT(*) as aggregate")
            ->groupBy($column)->orderByDesc('aggregate')->limit($limit)->get();

        return $this->rows($rows, $total, $labels);
    }

    private function rows(Collection $records, int $denominator, array $labels = []): array
    {
        return $records->map(function ($item) use ($denominator, $labels): array {
            $raw = $item->bucket;
            $label = filled($raw) ? (string) ($labels[$raw] ?? $raw) : 'Tidak diisi';

            return $this->row($label, (int) $item->aggregate, $denominator);
        })->all();
    }

    private function row(string $label, int $count, int $denominator): array
    {
        return [
            'label' => $label,
            'count' => $count,
            'percent' => $denominator > 0 ? round(100 * $count / $denominator, 1) : 0,
        ];
    }

    private function metric(string $label, int|float|string $value, string $description = '', string $color = 'primary'): array
    {
        return [
            'label' => $label,
            'value' => is_int($value) ? number_format($value, 0, ',', '.') : (string) $value,
            'description' => $description,
            'color' => $color,
        ];
    }

    private function group(string $title, array $rows, string $description = ''): array
    {
        return compact('title', 'rows', 'description');
    }

    private function pack(array $stats, array $groups, array $notes = []): array
    {
        return compact('stats', 'groups', 'notes');
    }

    private function rate(int $numerator, int|float $denominator): string
    {
        return $denominator > 0
            ? number_format(100 * $numerator / $denominator, 1, ',', '.').'%'
            : '—';
    }

    private function rupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
