<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('unit_configurations', 'builtin_field_policy')) {
            Schema::table('unit_configurations', function (Blueprint $table): void {
                $table->string('builtin_field_policy', 30)
                    ->default('system_default')
                    ->after('post_announcement_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('unit_configurations', 'builtin_field_policy')) {
            Schema::table('unit_configurations', function (Blueprint $table): void {
                $table->dropColumn('builtin_field_policy');
            });
        }
    }
};
