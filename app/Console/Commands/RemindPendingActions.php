<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Services\RegistrationEmailDeliveryService;
use Illuminate\Console\Command;

class RemindPendingActions extends Command
{
    protected $signature = 'spmb:remind-pending-actions {--execute : Kirim pengingat yang masih relevan}';
    protected $description = 'Tampilkan estimasi pengingat, atau antrekan secara aman ketika --execute diberikan';

    public function handle(RegistrationEmailDeliveryService $service): int
    {
        if (! config('spmb.mail.automatic_reminders_enabled', false)) {
            $this->warn('Pengingat otomatis nonaktif. Aktifkan lewat konfigurasi.');
            return self::SUCCESS;
        }
        $types = ['revision_reminder', 'payment_reminder', 'test_reminder', 'offer_reminder'];
        $count = 0;
        Registration::query()->operational()->with(['user','latestPayment','admissionOffer'])
            ->orderBy('id')->chunkById(100, function ($registrations) use ($service, $types, &$count): void {
                foreach ($registrations as $registration) {
                    foreach (array_intersect($types, array_keys($service->availableTypes($registration))) as $type) {
                        if ($this->option('execute')) {
                            if ($service->scheduleReminder($registration, $type)) {
                                $count++;
                            }
                        } else {
                            $count++;
                        }
                    }
                }
            });
        $this->info($this->option('execute') ? "{$count} pengingat diantrekan." : "{$count} pengingat potensial (dry run).");
        return self::SUCCESS;
    }
}
