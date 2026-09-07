<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admission_test_results')
            ->where('status', 'unbooked')
            ->where('result', 'pending')
            ->whereNull('assessed_at')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('registrations')
                    ->whereColumn('registrations.id', 'admission_test_results.registration_id')
                    ->whereIn('registrations.current_stage', [
                        'data_validation',
                        'virtual_account',
                        'payment',
                        'payment_verification',
                        'applicant_card',
                        'documents',
                        'document_verification',
                    ]);
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('test_bookings')
                    ->whereColumn('test_bookings.registration_id', 'admission_test_results.registration_id')
                    ->whereColumn('test_bookings.admission_test_id', 'admission_test_results.admission_test_id')
                    ->whereNotNull('test_bookings.test_session_id');
            })
            ->delete();
    }

    public function down(): void
    {
        // Data-only cleanup is intentionally irreversible.
    }
};
