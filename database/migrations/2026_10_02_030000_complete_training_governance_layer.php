<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_module_assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('training_module_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('seed_key', 160)->nullable()->unique();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('passing_score')->default(80);
            $table->unsignedSmallInteger('question_count')->nullable();
            $table->unsignedSmallInteger('max_attempts')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('training_module_questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('training_module_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('seed_key', 180)->nullable();
            $table->string('type', 30)->default('single_choice');
            $table->text('question');
            $table->json('options')->nullable();
            $table->string('correct_answer', 255);
            $table->text('explanation')->nullable();
            $table->decimal('weight', 8, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['training_module_assessment_id', 'seed_key'],
                'training_module_question_seed_unique'
            );
        });

        Schema::create('training_module_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('training_module_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('status', 30)->default('in_progress');
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedTinyInteger('passing_score_snapshot')->nullable();
            $table->unsignedSmallInteger('question_count_snapshot')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['training_module_assessment_id', 'user_id', 'attempt_no'],
                'training_module_attempt_user_no_unique'
            );
            $table->index(['user_id', 'status']);
        });

        Schema::create('training_module_attempt_questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('training_module_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_module_question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->text('question');
            $table->json('options')->nullable();
            $table->string('correct_answer', 255);
            $table->text('explanation')->nullable();
            $table->decimal('weight', 8, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['training_module_attempt_id', 'sort_order'],
                'training_module_attempt_question_sort_unique'
            );
        });

        Schema::create('training_module_answers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('training_module_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_module_attempt_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_module_question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('answer', 255)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['training_module_attempt_id', 'training_module_attempt_question_id'],
                'training_module_answer_snapshot_unique'
            );
        });

        Schema::table('app_settings', function (Blueprint $table): void {
            $table->boolean('training_enforce_sequence')->default(true)->after('theme_color');
            $table->boolean('training_require_module_mastery')->default(true)->after('training_enforce_sequence');
            $table->string('certification_enforcement_mode', 30)->default('off')->after('training_require_module_mastery');
            $table->json('certification_expiry_reminder_days')->nullable()->after('certification_enforcement_mode');
            $table->boolean('certificate_artifact_enabled')->default(true)->after('certification_expiry_reminder_days');
        });

        Schema::table('user_certifications', function (Blueprint $table): void {
            $table->json('expiry_reminders_sent')->nullable()->after('revocation_metadata');
            $table->string('artifact_path')->nullable()->after('expiry_reminders_sent');
            $table->string('artifact_sha256', 64)->nullable()->after('artifact_path');
            $table->string('artifact_signature', 64)->nullable()->after('artifact_sha256');
            $table->string('artifact_signature_algorithm', 40)->nullable()->after('artifact_signature');
            $table->timestamp('artifact_generated_at')->nullable()->after('artifact_signature_algorithm');
        });
    }

    public function down(): void
    {
        Schema::table('user_certifications', function (Blueprint $table): void {
            $table->dropColumn([
                'expiry_reminders_sent',
                'artifact_path',
                'artifact_sha256',
                'artifact_signature',
                'artifact_signature_algorithm',
                'artifact_generated_at',
            ]);
        });

        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'training_enforce_sequence',
                'training_require_module_mastery',
                'certification_enforcement_mode',
                'certification_expiry_reminder_days',
                'certificate_artifact_enabled',
            ]);
        });

        Schema::dropIfExists('training_module_answers');
        Schema::dropIfExists('training_module_attempt_questions');
        Schema::dropIfExists('training_module_attempts');
        Schema::dropIfExists('training_module_questions');
        Schema::dropIfExists('training_module_assessments');
    }
};
