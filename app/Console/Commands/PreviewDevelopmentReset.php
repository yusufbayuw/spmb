<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\VirtualAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreviewDevelopmentReset extends Command
{
    protected $signature = 'spmb:dev-reset-preview';

    protected $description = 'Pratinjau data operasional sebelum reset development; tidak mengubah database';

    public function handle(): int
    {
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->components->error('Reset development tidak diizinkan pada APP_ENV=production.');

            return self::FAILURE;
        }

        $this->components->warn('PRATINJAU SAJA: tidak ada data yang dihapus.');
        $this->table(['Data', 'Jumlah'], [
            ['Pendaftaran', Registration::query()->count()],
            ['Pembayaran', Payment::query()->count()],
            ['Virtual Account total', VirtualAccount::query()->count()],
            ['VA tersedia tanpa assignment/pembayaran', VirtualAccount::query()
                ->where('status', 'available')
                ->whereNull('registration_id')
                ->whereDoesntHave('payment')
                ->count()],
            ['VA berstatus paid', VirtualAccount::query()->where('status', 'paid')->count()],
        ]);

        $this->components->warn('Backup database dan file privat, verifikasi koneksi database, serta rekonsiliasi VA bank wajib sebelum reset.');
        $this->line('Perintah ini sengaja tidak menghapus data: dependensi pembayaran, bukti pembayaran, audit, dan konfigurasi unit harus dipetakan sebelum destructive reset.');

        return self::SUCCESS;
    }
}
