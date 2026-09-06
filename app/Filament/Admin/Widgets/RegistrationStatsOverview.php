<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AdmissionQuota;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Registration;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class RegistrationStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $registrations = $this->registrationQuery();
        $documents = $this->documentQuery();
        $payments = $this->paymentQuery();
        $quotas = $this->quotaQuery();

        return [
            Stat::make('Total Pendaftar', number_format((clone $registrations)->count(), 0, ',', '.'))
                ->description('Seluruh pendaftar yang dapat Anda akses')
                ->icon('heroicon-o-users'),
            Stat::make('Menunggu Verifikasi', number_format((clone $registrations)->where('status', 'submitted')->count(), 0, ',', '.'))
                ->description('Pendaftaran baru perlu diperiksa')
                ->color('warning')
                ->icon('heroicon-o-clock'),
            Stat::make('Dokumen Belum Diverifikasi', number_format((clone $documents)->where('is_verified', false)->count(), 0, ',', '.'))
                ->description('Dokumen perlu validasi')
                ->color('warning')
                ->icon('heroicon-o-document-magnifying-glass'),
            Stat::make('Pembayaran Perlu Tindak Lanjut', number_format((clone $payments)->whereIn('status', ['pending', 'paid'])->count(), 0, ',', '.'))
                ->description('Menunggu pembayaran atau verifikasi')
                ->color('info')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Diterima', number_format((clone $registrations)->where('status', 'accepted')->count(), 0, ',', '.'))
                ->description('Calon siswa berstatus diterima')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
            Stat::make('Terkonfirmasi', number_format((clone $registrations)->where('status', 'confirmed')->count(), 0, ',', '.'))
                ->description('Pendaftar telah menerima kursi')
                ->color('success')
                ->icon('heroicon-o-hand-thumb-up'),
            Stat::make('Terdaftar Resmi', number_format((clone $registrations)->where('status', 'enrolled')->count(), 0, ',', '.'))
                ->description('Enrollment telah diselesaikan')
                ->color('success')
                ->icon('heroicon-o-user-plus'),
            Stat::make('Daftar Tunggu', number_format((clone $registrations)->where('current_stage', 'waiting_list')->count(), 0, ',', '.'))
                ->description('Menunggu kursi penerimaan')
                ->color('warning')
                ->icon('heroicon-o-clock'),
            Stat::make('Daya Tampung', number_format((clone $quotas)->sum('capacity'), 0, ',', '.'))
                ->description('Total kapasitas penerimaan aktif')
                ->color('info')
                ->icon('heroicon-o-users'),
            Stat::make('Sisa Daya Tampung', number_format(max(0, (clone $quotas)->sum('capacity') - (clone $registrations)->whereIn('status', ['accepted', 'confirmed', 'enrolled'])->count()), 0, ',', '.'))
                ->description('Kapasitas penerimaan dikurangi kursi aktif')
                ->color('info')
                ->icon('heroicon-o-chart-bar-square'),
        ];
    }

    private function registrationQuery(): Builder
    {
        $query = Registration::query();
        $user = auth()->user();

        if ($user?->isTU() && $user->unit_id) {
            $query->where('unit_id', $user->unit_id);
        }

        return $query;
    }

    private function documentQuery(): Builder
    {
        $query = Document::query();
        $user = auth()->user();

        if ($user?->isTU() && $user->unit_id) {
            $query->whereHas('registration', fn (Builder $query) => $query->where('unit_id', $user->unit_id));
        }

        return $query;
    }

    private function paymentQuery(): Builder
    {
        $query = Payment::query();
        $user = auth()->user();

        if ($user?->isTU() && $user->unit_id) {
            $query->whereHas('registration', fn (Builder $query) => $query->where('unit_id', $user->unit_id));
        }

        return $query;
    }

    private function quotaQuery(): Builder
    {
        $query = AdmissionQuota::query()->where('is_active', true);
        $user = auth()->user();

        if ($user?->isTU() && $user->unit_id) {
            $query->whereHas('opening', fn (Builder $opening) => $opening->where('unit_id', $user->unit_id));
        }

        return $query;
    }
}
