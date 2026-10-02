<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuation_candidates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('academic_year', 20);
            $table->string('source_school_name', 150);
            $table->string('source_file')->nullable();
            $table->uuid('import_batch_uuid')->nullable()->index();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('source_key', 64)->unique();
            $table->string('nik', 16)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('full_name', 150)->nullable();
            $table->string('nisn', 20)->nullable();
            $table->string('nipd', 50)->nullable();
            $table->json('prefill_data')->nullable();
            $table->json('raw_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'academic_year', 'nik', 'birth_date', 'is_active'], 'continuation_candidates_match_idx');
            $table->index(['unit_id', 'academic_year', 'is_active'], 'continuation_candidates_scope_idx');
        });

        Schema::create('continuation_registration_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('continuation_candidate_id')->constrained('continuation_candidates')->restrictOnDelete();
            $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('matched_by', 50)->default('nik_birth_date');
            $table->json('source_snapshot')->nullable();
            $table->json('prefilled_fields')->nullable();
            $table->timestamp('matched_at');
            $table->timestamps();
            $table->index(['continuation_candidate_id', 'matched_at'], 'continuation_links_candidate_matched_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuation_registration_links');
        Schema::dropIfExists('continuation_candidates');
    }
};
