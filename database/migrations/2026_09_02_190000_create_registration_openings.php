<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('academic_year', 20);
            $table->string('wave', 100);
            $table->string('pathway', 100);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'open', 'closed', 'archived'])->default('draft');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['unit_id', 'academic_year', 'wave', 'pathway'],
                'registration_openings_unique_period'
            );
            $table->index(['unit_id', 'academic_year', 'status']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('registration_opening_id')
                ->nullable()
                ->after('unit_id')
                ->constrained('registration_openings')
                ->restrictOnDelete();

            $table->dropUnique(['nik']);
            $table->unique(
                ['registration_opening_id', 'nik'],
                'registrations_opening_nik_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique('registrations_opening_nik_unique');
            $table->dropConstrainedForeignId('registration_opening_id');
            $table->unique('nik');
        });

        Schema::dropIfExists('registration_openings');
    }
};
