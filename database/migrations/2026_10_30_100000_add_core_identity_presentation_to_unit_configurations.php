<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('unit_configurations', 'core_identity_fields')) {
            Schema::table('unit_configurations', function (Blueprint $table): void {
                $table->json('core_identity_fields')->nullable()->after('builtin_field_policy');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('unit_configurations', 'core_identity_fields')) {
            Schema::table('unit_configurations', function (Blueprint $table): void {
                $table->dropColumn('core_identity_fields');
            });
        }
    }
};
