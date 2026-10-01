<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('target_role', 50);
            $table->string('version', 30)->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['target_role', 'is_active']);
        });

        Schema::create('training_modules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['training_program_id', 'title']);
        });

        Schema::create('training_lessons', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('training_module_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('type', 30)->default('content');
            $table->longText('content')->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['training_module_id', 'title']);
        });

        Schema::create('training_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('enrolled');
            $table->timestamp('enrolled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['training_program_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('training_progress', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('training_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_lesson_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['training_enrollment_id', 'training_lesson_id'], 'training_progress_enrollment_lesson_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_progress');
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_lessons');
        Schema::dropIfExists('training_modules');
        Schema::dropIfExists('training_programs');
    }
};
