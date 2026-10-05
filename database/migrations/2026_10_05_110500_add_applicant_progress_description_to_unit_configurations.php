<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->text('applicant_progress_description')->nullable()->after('applicant_portal_blocks');
        });
    }

    public function down(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn('applicant_progress_description');
        });
    }
};
