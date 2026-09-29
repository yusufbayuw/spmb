<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->json('pre_form_consent')->nullable()->after('registration_number_digits');
        });
    }

    public function down(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn('pre_form_consent');
        });
    }
};
