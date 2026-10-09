<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            // Disabled by default. Only Super Admin may change this setting.
            $table->boolean('allow_admin_unit_registration_purge')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('allow_admin_unit_registration_purge');
        });
    }
};
