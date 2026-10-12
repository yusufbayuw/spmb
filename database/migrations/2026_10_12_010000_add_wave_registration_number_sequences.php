<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing values remain unchanged; wave-mode numbers can be longer than 50 characters.
        Schema::table('registrations', function (Blueprint $table): void {
            $table->string('registration_number', 100)->nullable()->change();
        });

        Schema::create('registration_number_wave_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('academic_year', 20);
            $table->string('wave', 100);
            $table->string('wave_code', 12);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['unit_id', 'academic_year', 'wave'], 'reg_wave_seq_group_unique');
            $table->unique(['unit_id', 'academic_year', 'wave_code'], 'reg_wave_seq_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_number_wave_sequences');

        // Do not truncate or rewrite already-issued registration numbers on rollback.
    }
};
