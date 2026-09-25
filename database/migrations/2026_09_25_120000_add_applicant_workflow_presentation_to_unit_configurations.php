<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->json('applicant_visible_stages')->nullable()->after('workflow_stage_labels');
            $table->string('completion_after_stage', 60)->nullable()->after('applicant_visible_stages');
            $table->string('completion_title', 180)->nullable()->after('completion_after_stage');
            $table->text('completion_message')->nullable()->after('completion_title');
        });
    }

    public function down(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn([
                'applicant_visible_stages',
                'completion_after_stage',
                'completion_title',
                'completion_message',
            ]);
        });
    }
};
