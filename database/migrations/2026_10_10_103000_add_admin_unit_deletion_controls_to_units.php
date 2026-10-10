<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->boolean('allow_admin_unit_registration_deletion')->default(false);
            $table->boolean('allow_admin_unit_va_deletion')->default(false);
            $table->boolean('allow_admin_unit_opening_deletion')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn([
                'allow_admin_unit_registration_deletion',
                'allow_admin_unit_va_deletion',
                'allow_admin_unit_opening_deletion',
            ]);
        });
    }
};
