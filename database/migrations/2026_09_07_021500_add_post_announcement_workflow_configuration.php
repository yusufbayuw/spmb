<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->boolean('post_announcement_enabled')->default(false)->after('tests_enabled');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->string('status', 40)->default('draft')->change();
        });

        $registrations = DB::table('registrations')
            ->whereIn('current_stage', ['waiting_list', 'admission_offer', 're_registration', 'enrollment'])
            ->get(['id', 'status']);

        foreach ($registrations as $registration) {
            $decision = DB::table('selections')
                ->where('registration_id', $registration->id)
                ->value('decision');

            $status = in_array($decision, ['accepted', 'rejected', 'waiting_list'], true)
                ? $decision
                : match ($registration->status) {
                    'confirmed', 'enrolled' => 'accepted',
                    'withdrawn', 'expired' => 'rejected',
                    default => $registration->status,
                };

            DB::table('registrations')
                ->where('id', $registration->id)
                ->update([
                    'current_stage' => 'completed',
                    'status' => $status,
                    'updated_at' => now(),
                ]);

            DB::table('admission_offers')
                ->where('registration_id', $registration->id)
                ->where('status', 'offered')
                ->update([
                    'status' => 'expired',
                    'expired_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn('post_announcement_enabled');
        });

        // The registration status column intentionally remains VARCHAR because narrowing it
        // back to the legacy ENUM could discard statuses already used by the admission flow.
    }
};
