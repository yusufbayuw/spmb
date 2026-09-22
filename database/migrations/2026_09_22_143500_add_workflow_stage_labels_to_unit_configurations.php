<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('unit_configurations', 'workflow_stage_labels')) {
            Schema::table('unit_configurations', function (Blueprint $table): void {
                $table->json('workflow_stage_labels')->nullable()->after('post_announcement_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('unit_configurations', 'workflow_stage_labels')) {
            Schema::table('unit_configurations', function (Blueprint $table): void {
                $table->dropColumn('workflow_stage_labels');
            });
        }
    }
};
