<?php

use App\Models\Registration;
use App\Services\RegistrationWorkflowService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Registration::query()
            ->where('lifecycle_status', 'active')
            ->where('current_stage', 'applicant_card')
            ->orderBy('id')
            ->chunkById(100, function ($registrations): void {
                foreach ($registrations as $registration) {
                    app(RegistrationWorkflowService::class)->autoIssueApplicantCard($registration);
                }
            });
    }

    public function down(): void
    {
        // Workflow progression and issued card metadata are intentionally not reverted.
    }
};
