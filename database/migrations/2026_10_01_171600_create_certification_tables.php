<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_programs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('target_role', 50);
            $table->string('version', 30)->default('1.0');
            $table->unsignedTinyInteger('passing_score')->default(80);
            $table->unsignedSmallInteger('valid_months')->default(24);
            $table->foreignId('training_program_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['target_role', 'is_active']);
        });

        Schema::create('certification_questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certification_program_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('single_choice');
            $table->text('question');
            $table->json('options')->nullable();
            $table->string('correct_answer', 255);
            $table->text('explanation')->nullable();
            $table->decimal('weight', 8, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('certification_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certification_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('status', 30)->default('in_progress');
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['certification_program_id', 'user_id', 'attempt_no'], 'cert_attempt_program_user_no_unique');
            $table->index(['user_id', 'status']);
        });

        Schema::create('certification_answers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certification_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_question_id')->constrained()->cascadeOnDelete();
            $table->string('answer', 255)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['certification_attempt_id', 'certification_question_id'], 'cert_answer_attempt_question_unique');
        });

        Schema::create('user_certifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_attempt_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('certificate_number', 100)->unique();
            $table->uuid('verification_code')->unique();
            $table->decimal('score', 5, 2);
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_certifications');
        Schema::dropIfExists('certification_answers');
        Schema::dropIfExists('certification_attempts');
        Schema::dropIfExists('certification_questions');
        Schema::dropIfExists('certification_programs');
    }
};
