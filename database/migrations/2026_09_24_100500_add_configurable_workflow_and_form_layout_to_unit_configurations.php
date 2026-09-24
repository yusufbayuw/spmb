<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->json('workflow_blocks')->nullable()->after('workflow_stage_labels');
            $table->json('form_groups')->nullable()->after('fields');
            $table->json('form_layout')->nullable()->after('form_groups');
        });
    }

    public function down(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn(['workflow_blocks', 'form_groups', 'form_layout']);
        });
    }
};
