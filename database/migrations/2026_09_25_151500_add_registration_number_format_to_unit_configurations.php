<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->string('registration_number_prefix', 30)->nullable()->after('completion_message');
            $table->unsignedTinyInteger('registration_number_digits')->default(4)->after('registration_number_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn([
                'registration_number_prefix',
                'registration_number_digits',
            ]);
        });
    }
};
