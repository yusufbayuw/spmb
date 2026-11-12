<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_openings', function (Blueprint $table): void {
            // Opt-in: existing openings keep their current applicant UI.
            $table->boolean('show_total_applicants')->default(false);
            $table->boolean('show_verified_applicants')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('registration_openings', function (Blueprint $table): void {
            $table->dropColumn(['show_total_applicants', 'show_verified_applicants']);
        });
    }
};
