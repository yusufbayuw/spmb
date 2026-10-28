<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_exams', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->decimal('passing_percentage', 5, 2)->default(60);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->boolean('is_published')->default(false);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'is_published']);
        });

        Schema::create('online_exam_questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->json('grading_config')->nullable();
            $table->decimal('points', 8, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['unit_id', 'type', 'is_active']);
        });

        Schema::create('online_exam_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('online_exam_id')->constrained('online_exams')->restrictOnDelete();
            $table->foreignId('online_exam_question_id')->constrained('online_exam_questions')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['online_exam_id', 'online_exam_question_id'], 'online_item_exam_question_unique');
        });

        Schema::create('online_exam_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('online_exam_id')->constrained('online_exams')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['online_exam_id', 'user_id'], 'online_assignment_exam_user_unique');
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('online_exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('online_exam_assignment_id')->constrained('online_exam_assignments')->restrictOnDelete();
            $table->foreignId('online_exam_id')->constrained('online_exams')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('status', 25)->default('in_progress');
            $table->unsignedSmallInteger('duration_minutes_snapshot');
            $table->decimal('passing_percentage_snapshot', 5, 2);
            $table->decimal('earned_points', 10, 2)->nullable();
            $table->decimal('maximum_points', 10, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['online_exam_assignment_id', 'attempt_no'], 'online_attempt_assignment_number_unique');
            $table->index(['user_id', 'status']);
        });

        Schema::create('online_exam_attempt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('online_exam_attempt_id')->constrained('online_exam_attempts')->restrictOnDelete();
            $table->foreignId('online_exam_question_id')->constrained('online_exam_questions')->restrictOnDelete();
            $table->string('type', 30);
            $table->json('display_snapshot');
            // Never serialize grading_snapshot to a Livewire/browser payload.
            $table->json('grading_snapshot')->nullable();
            $table->json('answer')->nullable();
            $table->decimal('points', 8, 2);
            $table->decimal('earned_points', 8, 2)->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['online_exam_attempt_id', 'online_exam_question_id'], 'online_snapshot_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_exam_attempt_items');
        Schema::dropIfExists('online_exam_attempts');
        Schema::dropIfExists('online_exam_assignments');
        Schema::dropIfExists('online_exam_items');
        Schema::dropIfExists('online_exam_questions');
        Schema::dropIfExists('online_exams');
    }
};
