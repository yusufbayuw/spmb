<?php

use App\Models\Unit;
use App\Services\RegistrationConsentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->json('pre_form_consent')->nullable()->after('registration_number_digits');
        });

        Unit::query()
            ->select(['id', 'name', 'code', 'institution_type'])
            ->orderBy('id')
            ->chunkById(100, function ($units): void {
                $consentService = app(RegistrationConsentService::class);

                foreach ($units as $unit) {
                    DB::table('unit_configurations')
                        ->where('unit_id', $unit->id)
                        ->whereNull('pre_form_consent')
                        ->update([
                            'pre_form_consent' => json_encode(
                                $consentService->defaultConfiguration($unit),
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                            ),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn('pre_form_consent');
        });
    }
};
