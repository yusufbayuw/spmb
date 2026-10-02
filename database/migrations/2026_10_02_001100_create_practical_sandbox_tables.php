<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practical_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certification_program_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->longText('instructions');
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['certification_program_id', 'is_active'], 'prac_scenario_program_active_idx');
        });

        Schema::create('practical_scenario_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('practical_scenario_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 80);
            $table->string('entity_key', 100);
            $table->string('label', 180);
            $table->json('initial_state');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['practical_scenario_id', 'entity_type', 'entity_key'], 'prac_record_scenario_entity_unique');
        });

        Schema::create('practical_scenario_actions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('practical_scenario_id')->constrained()->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('label', 180);
            $table->string('target_type', 80);
            $table->string('target_key', 100);
            $table->json('mutation');
            $table->json('allowed_when')->nullable();
            $table->string('button_color', 30)->default('gray');
            $table->boolean('requires_confirmation')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['practical_scenario_id', 'code'], 'prac_action_scenario_code_unique');
        });

        Schema::create('practical_assertions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('practical_scenario_id')->constrained()->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name', 180);
            $table->string('validator_class');
            $table->json('config');
            $table->decimal('points', 8, 2)->default(1);
            $table->boolean('is_critical')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['practical_scenario_id', 'code'], 'prac_assert_scenario_code_unique');
        });

        Schema::create('practical_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('practical_scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('status', 30)->default('in_progress');
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['practical_scenario_id', 'certification_attempt_id', 'user_id', 'attempt_no'],
                'prac_run_attempt_unique'
            );
            $table->index(['user_id', 'status'], 'prac_run_user_status_idx');
        });

        Schema::create('practical_sandbox_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('practical_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practical_scenario_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type', 80);
            $table->string('entity_key', 100);
            $table->string('label', 180);
            $table->json('original_state');
            $table->json('state');
            $table->timestamps();

            $table->unique(['practical_run_id', 'entity_type', 'entity_key'], 'prac_sandbox_run_entity_unique');
        });

        Schema::create('practical_run_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('practical_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practical_scenario_action_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action_code', 100);
            $table->string('target_type', 80);
            $table->string('target_key', 100);
            $table->json('before_state');
            $table->json('after_state');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['practical_run_id', 'action_code'], 'prac_event_run_action_idx');
        });

        Schema::create('practical_run_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('practical_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practical_assertion_id')->constrained()->cascadeOnDelete();
            $table->boolean('passed');
            $table->decimal('score', 8, 2)->default(0);
            $table->json('expected')->nullable();
            $table->json('actual')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->unique(['practical_run_id', 'practical_assertion_id'], 'prac_result_run_assert_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practical_run_results');
        Schema::dropIfExists('practical_run_events');
        Schema::dropIfExists('practical_sandbox_records');
        Schema::dropIfExists('practical_runs');
        Schema::dropIfExists('practical_assertions');
        Schema::dropIfExists('practical_scenario_actions');
        Schema::dropIfExists('practical_scenario_records');
        Schema::dropIfExists('practical_scenarios');
    }
};
