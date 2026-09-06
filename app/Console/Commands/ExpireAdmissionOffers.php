<?php

namespace App\Console\Commands;

use App\Services\AdmissionDecisionService;
use Illuminate\Console\Command;

class ExpireAdmissionOffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spmb:expire-admission-offers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mengakhiri penawaran penerimaan yang melewati batas konfirmasi';

    /**
     * Execute the console command.
     */
    public function handle(AdmissionDecisionService $admission): int
    {
        $count = $admission->expireDueOffers();
        $reminders = $admission->sendOfferReminders();
        $this->components->info("{$count} penawaran berakhir dan {$reminders} pengingat diproses.");

        return self::SUCCESS;
    }
}
