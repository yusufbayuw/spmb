<?php

use App\Models\AdmissionTestResult;
use App\Models\Registration;
use App\Models\Selection;
use App\Services\RegistrationNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Registration::query()
            ->with(['configuration', 'opening', 'unit'])
            ->where('current_stage', 'applicant_card')
            ->where('lifecycle_status', 'active')
            ->orderBy('id')
            ->chunkById(100, function ($registrations): void {
                foreach ($registrations as $registration) {
                    $paymentRequired = $registration->configuration?->payment_enabled ?? true;

                    if ($paymentRequired && ! $registration->payment_verified_at) {
                        continue;
                    }

                    if (! $paymentRequired && $registration->data_validation_status !== 'valid') {
                        continue;
                    }

                    DB::transaction(function () use ($registration): void {
                        $locked = Registration::query()
                            ->with(['configuration', 'opening', 'unit'])
                            ->lockForUpdate()
                            ->findOrFail($registration->id);

                        app(RegistrationNumberService::class)->assign($locked);

                        $target = $locked->configuration && ! $locked->configuration->documents_enabled
                            ? (collect($locked->configuredTests())->contains('is_required', true) ? 'tests' : 'selection')
                            : 'documents';

                        $issuerId = DB::table('payments')
                            ->where('registration_id', $locked->id)
                            ->where('status', 'verified')
                            ->orderByDesc('verified_at')
                            ->value('verified_by')
                            ?: $locked->data_validated_by;

                        $locked->forceFill([
                            'applicant_card_number' => $locked->applicant_card_number ?: $locked->generateApplicantCardNumber(),
                            'applicant_card_issued_by' => $issuerId,
                            'applicant_card_issued_at' => $locked->applicant_card_issued_at ?: now(),
                            'current_stage' => $target,
                        ])->saveQuietly();

                        if (in_array($target, ['tests', 'selection'], true)) {
                            $tests = $locked->configuredTests();

                            foreach ($tests as $test) {
                                AdmissionTestResult::firstOrCreate(
                                    [
                                        'registration_id' => $locked->id,
                                        'admission_test_id' => $test['id'],
                                    ],
                                    [
                                        'status' => 'unbooked',
                                        'result' => 'pending',
                                    ],
                                );
                            }

                            if (! collect($tests)->contains('is_required', true)) {
                                Selection::firstOrCreate(
                                    ['registration_id' => $locked->id],
                                    ['decision' => 'pending'],
                                );
                            }
                        }
                    });
                }
            });
    }

    public function down(): void
    {
        // Backfilled cards and stage progression are intentionally not reverted.
    }
};
