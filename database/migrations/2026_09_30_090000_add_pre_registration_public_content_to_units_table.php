<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            if (! Schema::hasColumn('units', 'pre_registration_heading')) {
                $table->string('pre_registration_heading', 180)->nullable()->after('public_body');
            }

            if (! Schema::hasColumn('units', 'pre_registration_body')) {
                $table->longText('pre_registration_body')->nullable()->after('pre_registration_heading');
            }

            if (! Schema::hasColumn('units', 'pre_registration_items')) {
                $table->json('pre_registration_items')->nullable()->after('pre_registration_body');
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            foreach (['pre_registration_items', 'pre_registration_body', 'pre_registration_heading'] as $column) {
                if (Schema::hasColumn('units', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
