<?php

use App\Models\UnitConfiguration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->json('registrant_relationship_options')
                ->nullable()
                ->after('builtin_field_policy');
        });

        DB::table('unit_configurations')
            ->whereNull('registrant_relationship_options')
            ->update([
                'registrant_relationship_options' => json_encode(
                    UnitConfiguration::defaultRegistrantRelationshipOptions(),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn('registrant_relationship_options');
        });
    }
};
