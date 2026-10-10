<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionReadinessService
{
    /**
     * Manual checks cannot be proved by PHP. Evidence is scoped to deployment
     * rather than source-code commits. Manual approvals remain visible after
     * copy-paste upgrades; operational checks are re-evaluated on every visit.
     */
    public const MANUAL = [
        'backup_restore' => ['Pemulihan backup database & file privat', 'Cadangkan dan pulihkan pada server terisolasi; cocokkan data, dokumen, dan hak akses.', 'Backup'],
        'rpo_rto' => ['RPO, RTO, dan penanggung jawab insiden', 'Catat target pemulihan, retensi backup, operator, dan hasil simulasi kehilangan layanan.', 'Backup'],
        'rollback' => ['Rollback teruji', 'Buktikan rollback kode/konfigurasi yang kompatibel dengan migrasi dan data bisnis.', 'Release'],
        'release_approval' => ['Persetujuan rilis dan SHA', 'Konfirmasikan commit persis yang dideploy, penanggung jawab dan go/no-go.', 'Release'],
        'queue_supervisor' => ['Supervisor dan worker bertahan restart', 'Restart worker di staging; buktikan process manager menjaga emails, notifications, default.', 'Queue'],
        'scheduler_supervision' => ['Cron & scheduler benar-benar berjalan', 'Lakukan observasi schedule:list, cron OS, dan waktu terakhir probe pada server.', 'Queue'],
        'smtp_delivery' => ['Email nyata masuk ke inbox', 'Uji verifikasi, VA, pengumuman, dan reset password dengan inbox eksternal; SPF/DKIM/DMARC.', 'Email'],
        'smtp_failure' => ['Simulasi email gagal / retry', 'Uji transport putus, retry, failed jobs, pembatasan resend, audit, dan pemulihan.', 'Email'],
        'notification_db' => ['Database notification end-to-end', 'Gunakan pendaftar dan Admin Unit nyata: tampil, hanya unit yang sah, mark-read, dan refresh.', 'Notifikasi'],
        'push_devices' => ['Push nyata lintas perangkat', 'Buktikan opt-in/out, foreground/background, dua akun, logout/login, Android dan Safari iOS PWA.', 'PWA/Push'],
        'push_credentials' => ['VAPID dan egress jaringan', 'Konfirmasi VAPID stabil, endpoint provider terpercaya, firewall egress HTTPS, rotasi/disaster recovery.', 'PWA/Push'],
        'pwa_install' => ['Instalasi & offline behavior PWA', 'Periksa manifest, scope SW, install dan update; tidak boleh cache HTML auth, dokumen, atau data pribadi.', 'PWA/Push'],
        'https_headers' => ['TLS dan reverse proxy nyata', 'Periksa sertifikat, redirect HTTP, forwarded headers, HSTS, cookies Secure, CSRF dan WebSocket/Livewire.', 'Keamanan'],
        'webserver_denial' => ['Webroot dan file sensitif', 'Buktikan .env, .git, .sql, file private, backup, log dan storage non-public tak bisa diakses publik.', 'Keamanan'],
        'access_roles' => ['RBAC dan anti-IDOR', 'Uji TU, Admin Unit, Super Admin, pendaftar lintas unit termasuk ekspor, file privat, VA, audit.', 'Keamanan'],
        'captcha_limits' => ['Auth/CAPTCHA dan brute-force', 'Uji login, reset password, register, verifikasi email, throttle dan sesi kadaluwarsa.', 'Keamanan'],
        'file_security' => ['Upload dan pemeriksaan malware', 'Uji ekstensi, MIME, pemindaian, izin akses, ukuran, file berbahaya dan file ekspor.', 'Keamanan'],
        'workflow_uat' => ['SPMB end-to-end seluruh jalur', 'Uji daftar, email, validasi, VA, bukti transfer, verifikasi manual, tes, seleksi, pengumuman, daftar ulang.', 'Bisnis'],
        'failure_recovery' => ['Pemulihan workflow terputus', 'Simulasi email gagal, tab ditutup, upload gagal, job tertunda, perubahan status; tidak boleh ada duplikasi Payment/VA.', 'Bisnis'],
        'load_capacity' => ['Uji beban dan performa', 'Uji jumlah pendaftar puncak, antrean, query lambat, Redis, MySQL, CPU/RAM, upload dan batas file.', 'Operasional'],
        'monitoring_alerts' => ['Alerting, log, disk & kapasitas', 'Buktikan alarm error 5xx, queue failed, disk, DB, Redis, backup dan proses PHP/worker.', 'Operasional'],
        'retention_privacy' => ['Privasi, retensi, dan persetujuan', 'Periksa data pribadi, audit, masa simpan, consent, penghapusan terkontrol, hak akses operator.', 'Kepatuhan'],
        'payment_reconciliation' => ['Rekonsiliasi VA dan pembayaran', 'Periksa pool VA, status transfer manual, kuitansi, duplikasi dan ketidaksesuaian finansial.', 'Bisnis'],
        'staging_isolation' => ['Isolasi staging-production', 'Validasi database, APP_KEY, session, cache prefix, storage, alamat email uji, dan isolasi queue.', 'Release'],
    ];

    public function auditScope(): string
    {
        // Database compatibility: existing tables still use release_sha.
        // The sentinel is intentionally installation-scoped, not a Git SHA.
        // There is no dependency on Git or a manually maintained .env version.
        return 'installation';
    }

    public function deploymentId(): string
    {
        // Only a one-way identifier is persisted; do not expose the configured URL.
        return hash('sha256', rtrim((string) config('app.url'), '/'));
    }

    public function report(): array
    {
        $environment = (string) config('app.env');
        $release = $this->auditScope();
        $checks = [];

        $add = static function (string $id, string $category, bool $ok, string $message, bool $warning = false) use (&$checks): void {
            $checks[] = [
                'id' => $id, 'category' => $category,
                'label' => str_replace('_', ' ', ucfirst($id)),
                'status' => $ok ? 'pass' : ($warning ? 'warning' : 'fail'),
                'detail' => $message, 'manual' => false,
            ];
        };
        $probe = static function (callable $fn): bool {
            try {
                return (bool) $fn();
            } catch (Throwable) {
                return false;
            }
        };

        $add('environment', 'Konfigurasi', $environment === 'production', 'APP_ENV harus production untuk keputusan production.');
        $add('audit_scope', 'Release', true,
            'Audit menggunakan identitas instalasi. Tidak perlu Git, SHA atau hash file kode.');
        $add('app_key', 'Konfigurasi', filled(config('app.key')), 'APP_KEY harus terpasang dan tetap konsisten.');
        $add('debug', 'Konfigurasi', config('app.debug') === false, 'APP_DEBUG wajib false.');
        $add('https', 'Keamanan', str_starts_with((string) config('app.url'), 'https://'), 'APP_URL harus https://.');
        $add('secure_cookie', 'Keamanan', config('session.secure') === true, 'SESSION_SECURE_COOKIE wajib true.');
        $add('http_only_cookie', 'Keamanan', config('session.http_only') === true, 'SESSION_HTTP_ONLY wajib true.');
        $add('same_site_cookie', 'Keamanan', in_array(config('session.same_site'), ['lax', 'strict'], true), 'SESSION_SAME_SITE lax atau strict.');
        $add('session_persistent', 'Konfigurasi', in_array(config('session.driver'), ['redis', 'database', 'file'], true), 'Session harus menggunakan storage persisten.');
        $add('queue_persistent', 'Queue', ! in_array(config('queue.default'), [null, '', 'sync', 'null'], true), 'Queue harus asynchronous dan persisten.');
        $add('mail_transport', 'Email', ! in_array(config('mail.default'), [null, '', 'log', 'array'], true)
            && filled(config('mail.from.address')) && config('mail.from.address') !== 'hello@example.com',
            'Mailer nyata dan MAIL_FROM_ADDRESS non-demo wajib tersedia.');
        $add('dangerous_reset_locked', 'Keamanan', blank(config('spmb.reset.allowed_target')), 'Reset development harus dimatikan di production.');
        $add('upload_scan', 'Keamanan', config('spmb.uploads.require_malware_scan') === true, 'Pemindaian malware pada upload direkomendasikan.', true);

        $private = config('filesystems.disks.applicant-private', []);
        $public = config('filesystems.disks.public', []);
        $privateRoot = rtrim((string) ($private['root'] ?? ''), '/\\');
        $publicRoot = rtrim((string) ($public['root'] ?? ''), '/\\');
        $add('private_uploads', 'Keamanan', $privateRoot !== '' && $publicRoot !== ''
            && $privateRoot !== $publicRoot && ! str_starts_with($privateRoot.'/', $publicRoot.'/')
            && ($private['serve'] ?? false) === false && ($private['visibility'] ?? null) === 'private',
            'File pendaftar harus privat dan tidak berada di bawah public disk.');

        $db = $probe(fn () => DB::connection()->select('SELECT 1') !== []);
        $add('database_connection', 'Database', $db, 'Database harus menjawab SELECT 1 tanpa perubahan data.');

        $tables = ['migrations', 'users', 'notifications', 'push_subscriptions', 'jobs', 'failed_jobs',
            'mail_delivery_attempts', 'pending_applicant_email_changes', 'audit_logs', 'registrations',
            'payments', 'virtual_accounts', 'production_readiness_attestations', 'production_readiness_snapshots'];
        foreach ($tables as $table) {
            $ok = $db && $probe(fn () => Schema::hasTable($table));
            $add('table_'.$table, 'Database', $ok, "Tabel {$table} harus tersedia; jalankan migrasi sebelum production.");
        }

        $pending = null;
        if ($db && $probe(fn () => Schema::hasTable('migrations'))) {
            try {
                $ran = DB::table('migrations')->pluck('migration')->all();
                $available = array_map(static fn (string $p): string => pathinfo($p, PATHINFO_FILENAME),
                    glob(database_path('migrations/*.php')) ?: []);
                $pending = count(array_diff($available, $ran));
            } catch (Throwable) {
                $pending = null;
            }
        }
        $add('migrations_pending', 'Database', $pending === 0,
            $pending === null ? 'Status migration tidak dapat ditentukan.' : "Terdapat {$pending} migration belum dijalankan.");

        $failed = $db && $probe(fn () => Schema::hasTable('failed_jobs'))
            ? $probe(fn () => DB::table('failed_jobs')->count() === 0) : false;
        $add('failed_jobs', 'Queue', $failed, 'Periksa job gagal. Gagal lama harus ditinjau; jangan dihapus tanpa investigasi.');

        $recentFailedEmail = $db && $probe(fn () => Schema::hasTable('mail_delivery_attempts'))
            ? $probe(fn () => DB::table('mail_delivery_attempts')->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())->count() === 0) : false;
        $add('email_failures_24h', 'Email', $recentFailedEmail, 'Tidak boleh ada pengiriman email gagal dalam 24 jam terakhir.');

        $stuck = $db && $probe(fn () => Schema::hasTable('mail_delivery_attempts'))
            ? $probe(fn () => DB::table('mail_delivery_attempts')->where('status', 'queued')
                ->where('created_at', '<', now()->subMinutes(15))->count() === 0) : false;
        $add('email_stuck', 'Email', $stuck, 'Tidak boleh ada antrean email yang belum ditangani > 15 menit.');

        $add('vapid_keys', 'PWA/Push',
            filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key')),
            'VAPID public/private key harus tersedia dan dijaga stabil.');
        $assets = ['sw.js', 'js/pwa.js', 'images/pwa/icon-192.png', 'images/pwa/icon-512.png', 'images/pwa/icon-maskable-512.png'];
        foreach ($assets as $asset) {
            $add('asset_'.str_replace(['/', '.'], '_', $asset), 'PWA/Push', is_file(public_path($asset)),
                "Asset {$asset} harus terpasang di public dan dapat diakses melalui HTTPS.");
        }
        $add('manifest_route', 'PWA/Push', \Illuminate\Support\Facades\Route::has('pwa.manifest'),
            'Rute manifest.webmanifest harus terdaftar.');
        $add('push_api', 'PWA/Push', \Illuminate\Support\Facades\Route::has('push.subscriptions.store')
            && \Illuminate\Support\Facades\Route::has('push.subscriptions.destroy'),
            'Rute push subscribe/unsubscribe harus terdaftar.');

        $probesEnabled = config('spmb.readiness.probes_enabled', false) === true;
        $add('probes_enabled', 'Monitoring', $probesEnabled,
            'Aktifkan SPMB_READINESS_PROBES_ENABLED=true untuk menguji scheduler dan kedua worker.');
        foreach (['scheduler', 'worker:mail', 'worker:notifications'] as $name) {
            $key = $name === 'scheduler' ? 'spmb:readiness:scheduler:last' : 'spmb:readiness:'.$name;
            $recent = $probesEnabled && $probe(function () use ($key): bool {
                $timestamp = Cache::get($key);
                return is_numeric($timestamp) && (int) $timestamp <= now()->timestamp
                    && (int) $timestamp > now()->subMinutes(12)->timestamp;
            });
            $add('probe_'.str_replace(':', '_', $name), 'Monitoring', $recent,
                "Heartbeat {$name} harus pernah dieksekusi dalam 12 menit terakhir.");
        }

        $attestations = [];
        if ($db && $probe(fn () => Schema::hasTable('production_readiness_attestations'))) {
            try {
                $attestations = DB::table('production_readiness_attestations')
                    ->where('deployment_id', $this->deploymentId())->where('release_sha', $release)
                    ->get()->keyBy('check_id')->all();
            } catch (Throwable) {
                // No manual approval can be assumed.
            }
        }

        foreach (self::MANUAL as $id => [$title, $description, $category]) {
            $record = $attestations[$id] ?? null;
            $status = $record?->status === 'pass' ? 'pass' : ($record?->status === 'fail' ? 'fail' : 'pending');
            $checks[] = [
                'id' => $id, 'category' => $category, 'label' => $title,
                'status' => $status, 'detail' => $description, 'manual' => true,
                'evidence' => $record?->evidence, 'reviewed_at' => $record?->reviewed_at,
            ];
        }

        $counts = array_count_values(array_column($checks, 'status'));
        $fail = $counts['fail'] ?? 0;
        $pendingCount = $counts['pending'] ?? 0;
        $warning = $counts['warning'] ?? 0;
        $ready = $fail === 0 && $pendingCount === 0 && $warning === 0 && $environment === 'production';

        return [
            'status' => $ready ? 'ready_for_review' : 'not_ready',
            'environment' => $environment,
            'release_sha' => $release,
            'audit_scope' => 'installation',
            'checks' => $checks, 'failures' => $fail, 'pending' => $pendingCount,
            'warnings' => $warning, 'passes' => $counts['pass'] ?? 0,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function attest(User $actor, string $checkId, string $status, string $evidence): void
    {
        abort_unless($actor->is_active && $actor->isAdmin(), 403);
        abort_unless(array_key_exists($checkId, self::MANUAL), 422);

        if (! in_array($status, ['pass', 'fail', 'pending'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Status audit tidak sah.']);
        }
        $evidence = trim($evidence);
        if ($status === 'pass' && mb_strlen($evidence) < 20 || mb_strlen($evidence) > 2000) {
            throw \Illuminate\Validation\ValidationException::withMessages(['evidence' => 'Untuk lulus, isi bukti minimal 20 dan maksimal 2000 karakter.']);
        }
        $release = $this->release();
         DB::table('production_readiness_attestations')->updateOrInsert(
            ['deployment_id' => $this->deploymentId(), 'release_sha' => $release, 'check_id' => $checkId],
            ['status' => $status, 'evidence' => $evidence, 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );

        app(AuditTrail::class)->record('release.readiness_attestation', $actor, actor: $actor,
            metadata: ['release_sha' => $release, 'check_id' => $checkId, 'status' => $status],
            description: 'Pemeriksaan readiness manual diperbarui');
    }

    public function snapshot(User $actor): array
    {
        abort_unless($actor->is_active && $actor->isAdmin(), 403);
        $report = $this->report();

        DB::table('production_readiness_snapshots')->insert([
            'deployment_id' => $this->deploymentId(), 'release_sha' => $report['release_sha'],
            'environment' => $report['environment'], 'status' => $report['status'],
            'failed_count' => $report['failures'], 'warning_count' => $report['warnings'],
            'pending_count' => $report['pending'],
            'summary' => json_encode($report, JSON_THROW_ON_ERROR),
            'created_by' => $actor->id, 'created_at' => now(),
        ]);
        app(AuditTrail::class)->record('release.readiness_snapshot', $actor, actor: $actor,
            metadata: ['release_sha' => $report['release_sha'], 'status' => $report['status'],
                'failures' => $report['failures'], 'pending' => $report['pending']],
            description: 'Super Admin menyimpan snapshot pemeriksaan production');
        return $report;
    }
}
