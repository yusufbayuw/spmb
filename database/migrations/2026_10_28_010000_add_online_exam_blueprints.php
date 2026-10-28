<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_exam_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['unit_id', 'code'], 'online_exam_category_unit_code_unique');
        });

        Schema::table('online_exam_questions', function (Blueprint $table): void {
            $table->foreignId('online_exam_category_id')->nullable()
                ->constrained('online_exam_categories')->restrictOnDelete();
            $table->string('difficulty', 15)->default('medium');
            $table->string('competency_tag', 150)->nullable();
            $table->index(['unit_id', 'online_exam_category_id', 'difficulty'], 'online_question_blueprint_lookup');
        });

        Schema::table('online_exams', function (Blueprint $table): void {
            $table->foreignId('study_program_id')->nullable()
                ->constrained('study_programs')->restrictOnDelete();
            // Null/empty JSON retains Phase 1's manually curated fixed package.
            $table->json('blueprint')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('online_exams', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('study_program_id');
            $table->dropColumn('blueprint');
        });

        Schema::table('online_exam_questions', function (Blueprint $table): void {
            $table->dropIndex('online_question_blueprint_lookup');
            $table->dropConstrainedForeignId('online_exam_category_id');
            $table->dropColumn(['difficulty', 'competency_tag']);
        });

        Schema::dropIfExists('online_exam_categories');
    }
};
