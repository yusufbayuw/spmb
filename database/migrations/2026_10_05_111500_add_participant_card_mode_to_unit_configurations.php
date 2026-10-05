<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->string('participant_card_mode', 24)
                ->default('both')
                ->after('registration_number_digits');
        });
    }

    public function down(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn('participant_card_mode');
        });
    }
};
