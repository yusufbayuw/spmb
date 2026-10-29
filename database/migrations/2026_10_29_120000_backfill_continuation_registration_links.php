<?php
use App\Services\ContinuationCandidateMatcher;
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {
        app(ContinuationCandidateMatcher::class)->reconcileExistingRegistrations();
    }
    public function down(): void {
        // Preserve historical links.
    }
};
