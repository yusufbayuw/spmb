<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasIndex('training_programs', 'training_programs_code_unique')) {
            Schema::table('training_programs', function (Blueprint $table): void {
                $table->dropUnique('training_programs_code_unique');
            });
        }

        if (! $this->hasIndex('training_programs', 'training_program_code_version_unique')) {
            Schema::table('training_programs', function (Blueprint $table): void {
                $table->unique(['code', 'version'], 'training_program_code_version_unique');
            });
        }

        if ($this->hasIndex('certification_programs', 'certification_programs_code_unique')) {
            Schema::table('certification_programs', function (Blueprint $table): void {
                $table->dropUnique('certification_programs_code_unique');
            });
        }

        if (! $this->hasIndex('certification_programs', 'cert_program_code_version_unique')) {
            Schema::table('certification_programs', function (Blueprint $table): void {
                $table->unique(['code', 'version'], 'cert_program_code_version_unique');
            });
        }

        if (! Schema::hasColumn('certification_programs', 'question_count')) {
            Schema::table('certification_programs', function (Blueprint $table): void {
                $table->unsignedSmallInteger('question_count')->nullable()->after('passing_score');
                $table->unsignedSmallInteger('time_limit_minutes')->nullable()->after('question_count');
                $table->unsignedSmallInteger('max_attempts')->nullable()->after('time_limit_minutes');
                $table->unsignedSmallInteger('cooldown_hours')->default(0)->after('max_attempts');
                $table->boolean('shuffle_questions')->default(true)->after('cooldown_hours');
                $table->boolean('shuffle_options')->default(true)->after('shuffle_questions');
            });
        }

        if ($this->hasIndex('practical_scenarios', 'practical_scenarios_code_unique')) {
            Schema::table('practical_scenarios', function (Blueprint $table): void {
                $table->dropUnique('practical_scenarios_code_unique');
            });
        }

        if (! $this->hasIndex('practical_scenarios', 'practical_scenario_program_code_unique')) {
            Schema::table('practical_scenarios', function (Blueprint $table): void {
                $table->unique(['certification_program_id', 'code'], 'practical_scenario_program_code_unique');
            });
        }

        if (! Schema::hasColumn('certification_attempts', 'program_code_snapshot')) {
            Schema::table('certification_attempts', function (Blueprint $table): void {
                $table->string('program_code_snapshot', 50)->nullable()->after('user_id');
                $table->string('program_name_snapshot', 180)->nullable()->after('program_code_snapshot');
                $table->string('program_version_snapshot', 30)->nullable()->after('program_name_snapshot');
                $table->unsignedTinyInteger('passing_score_snapshot')->nullable()->after('program_version_snapshot');
                $table->unsignedTinyInteger('theory_weight_snapshot')->nullable()->after('passing_score_snapshot');
                $table->unsignedTinyInteger('practical_weight_snapshot')->nullable()->after('theory_weight_snapshot');
                $table->unsignedTinyInteger('practical_passing_score_snapshot')->nullable()->after('practical_weight_snapshot');
                $table->unsignedSmallInteger('valid_months_snapshot')->nullable()->after('practical_passing_score_snapshot');
                $table->unsignedSmallInteger('question_count_snapshot')->nullable()->after('practical_passing_score_snapshot');
                $table->unsignedSmallInteger('time_limit_minutes_snapshot')->nullable()->after('question_count_snapshot');
                $table->json('required_practical_scenario_ids_snapshot')->nullable()->after('time_limit_minutes_snapshot');
                $table->timestamp('expires_at')->nullable()->after('started_at');
            });
        }

        Schema::create('certification_attempt_questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certification_attempt_id');
            $table->foreignId('certification_question_id')->nullable();
            $table->foreign('certification_attempt_id', 'cert_attempt_q_attempt_fk')
                ->references('id')->on('certification_attempts')->cascadeOnDelete();
            $table->foreign('certification_question_id', 'cert_attempt_q_source_fk')
                ->references('id')->on('certification_questions')->nullOnDelete();
            $table->string('type', 30);
            $table->text('question');
            $table->json('options')->nullable();
            $table->string('correct_answer', 255);
            $table->text('explanation')->nullable();
            $table->decimal('weight', 8, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['certification_attempt_id', 'sort_order'],
                'cert_attempt_question_sort_unique'
            );
        });

        Schema::table('certification_answers', function (Blueprint $table): void {
            $table->dropForeign(['certification_question_id']);
            $table->unsignedBigInteger('certification_question_id')->nullable()->change();
            $table->foreign('certification_question_id', 'cert_answer_source_q_fk')
                ->references('id')->on('certification_questions')->nullOnDelete();

            $table->foreignId('certification_attempt_question_id')
                ->nullable()
                ->after('certification_question_id');
            $table->foreign('certification_attempt_question_id', 'cert_answer_snapshot_q_fk')
                ->references('id')->on('certification_attempt_questions')->nullOnDelete();

            $table->unique(
                ['certification_attempt_id', 'certification_attempt_question_id'],
                'cert_answer_attempt_snapshot_unique'
            );
        });

        Schema::table('practical_runs', function (Blueprint $table): void {
            $table->string('scenario_code_snapshot', 80)->nullable()->after('user_id');
            $table->string('scenario_name_snapshot', 180)->nullable()->after('scenario_code_snapshot');
            $table->longText('instructions_snapshot')->nullable()->after('scenario_name_snapshot');
            $table->unsignedSmallInteger('time_limit_minutes_snapshot')->nullable()->after('instructions_snapshot');
            $table->unsignedTinyInteger('passing_score_snapshot')->nullable()->after('time_limit_minutes_snapshot');
        });

        Schema::create('practical_run_actions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('practical_run_id');
            $table->foreignId('practical_scenario_action_id')->nullable();
            $table->foreign('practical_run_id', 'pr_run_action_run_fk')
                ->references('id')->on('practical_runs')->cascadeOnDelete();
            $table->foreign('practical_scenario_action_id', 'pr_run_action_source_fk')
                ->references('id')->on('practical_scenario_actions')->nullOnDelete();
            $table->string('code', 100);
            $table->string('label', 180);
            $table->string('target_type', 80);
            $table->string('target_key', 100);
            $table->json('mutation');
            $table->json('allowed_when')->nullable();
            $table->string('button_color', 30)->default('gray');
            $table->boolean('requires_confirmation')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['practical_run_id', 'code'], 'practical_run_action_code_unique');
        });

        Schema::create('practical_run_assertions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('practical_run_id');
            $table->foreignId('practical_assertion_id')->nullable();
            $table->foreign('practical_run_id', 'pr_run_assert_run_fk')
                ->references('id')->on('practical_runs')->cascadeOnDelete();
            $table->foreign('practical_assertion_id', 'pr_run_assert_source_fk')
                ->references('id')->on('practical_assertions')->nullOnDelete();
            $table->string('code', 100);
            $table->string('name', 180);
            $table->string('validator_class');
            $table->json('config');
            $table->decimal('points', 8, 2)->default(1);
            $table->boolean('is_critical')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['practical_run_id', 'code'], 'practical_run_assertion_code_unique');
        });

        Schema::table('practical_run_events', function (Blueprint $table): void {
            $table->foreignId('practical_run_action_id')
                ->nullable()
                ->after('practical_scenario_action_id');
            $table->foreign('practical_run_action_id', 'pr_run_event_action_fk')
                ->references('id')->on('practical_run_actions')->nullOnDelete();
        });

        Schema::table('practical_run_results', function (Blueprint $table): void {
            $table->dropForeign(['practical_assertion_id']);
            $table->unsignedBigInteger('practical_assertion_id')->nullable()->change();
            $table->foreign('practical_assertion_id', 'pr_run_result_source_assert_fk')
                ->references('id')->on('practical_assertions')->nullOnDelete();

            $table->foreignId('practical_run_assertion_id')
                ->nullable()
                ->after('practical_assertion_id');
            $table->foreign('practical_run_assertion_id', 'pr_run_result_snapshot_assert_fk')
                ->references('id')->on('practical_run_assertions')->nullOnDelete();

            $table->unique(
                ['practical_run_id', 'practical_run_assertion_id'],
                'practical_result_run_snapshot_unique'
            );
        });

        Schema::table('user_certifications', function (Blueprint $table): void {
            $table->string('recipient_name_snapshot', 180)->nullable()->after('certification_attempt_id');
            $table->string('recipient_unit_snapshot', 180)->nullable()->after('recipient_name_snapshot');
            $table->string('recipient_role_snapshot', 80)->nullable()->after('recipient_unit_snapshot');
            $table->string('program_code_snapshot', 50)->nullable()->after('recipient_role_snapshot');
            $table->string('program_name_snapshot', 180)->nullable()->after('program_code_snapshot');
            $table->string('program_version_snapshot', 30)->nullable()->after('program_name_snapshot');
            $table->decimal('theory_score', 5, 2)->nullable()->after('score');
            $table->decimal('practical_score', 5, 2)->nullable()->after('theory_score');
            $table->timestamp('revoked_at')->nullable()->after('status');
            $table->foreignId('revoked_by_user_id')->nullable()->after('revoked_at');
            $table->foreign('revoked_by_user_id', 'user_cert_revoker_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable()->after('revoked_by_user_id');
            $table->json('revocation_metadata')->nullable()->after('revocation_reason');
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }

    public function down(): void
    {
        Schema::table('user_certifications', function (Blueprint $table): void {
            $table->dropForeign('user_cert_revoker_fk');
            $table->dropColumn([
                'recipient_name_snapshot', 'recipient_unit_snapshot', 'recipient_role_snapshot',
                'program_code_snapshot', 'program_name_snapshot', 'program_version_snapshot',
                'theory_score', 'practical_score', 'revoked_at', 'revoked_by_user_id',
                'revocation_reason', 'revocation_metadata',
            ]);
        });

        Schema::table('practical_run_results', function (Blueprint $table): void {
            $table->dropUnique('practical_result_run_snapshot_unique');
            $table->dropForeign('pr_run_result_snapshot_assert_fk');
            $table->dropColumn('practical_run_assertion_id');

            $table->dropForeign('pr_run_result_source_assert_fk');
            $table->unsignedBigInteger('practical_assertion_id')->nullable(false)->change();
            $table->foreign('practical_assertion_id', 'pr_run_result_source_assert_fk')
                ->references('id')->on('practical_assertions')->cascadeOnDelete();
        });

        Schema::table('practical_run_events', function (Blueprint $table): void {
            $table->dropForeign('pr_run_event_action_fk');
            $table->dropColumn('practical_run_action_id');
        });

        Schema::dropIfExists('practical_run_assertions');
        Schema::dropIfExists('practical_run_actions');

        Schema::table('practical_runs', function (Blueprint $table): void {
            $table->dropColumn([
                'scenario_code_snapshot', 'scenario_name_snapshot', 'instructions_snapshot',
                'time_limit_minutes_snapshot', 'passing_score_snapshot',
            ]);
        });

        Schema::table('certification_answers', function (Blueprint $table): void {
            $table->dropUnique('cert_answer_attempt_snapshot_unique');
            $table->dropForeign('cert_answer_snapshot_q_fk');
            $table->dropColumn('certification_attempt_question_id');

            $table->dropForeign('cert_answer_source_q_fk');
            $table->unsignedBigInteger('certification_question_id')->nullable(false)->change();
            $table->foreign('certification_question_id', 'cert_answer_source_q_fk')
                ->references('id')->on('certification_questions')->cascadeOnDelete();
        });

        Schema::dropIfExists('certification_attempt_questions');

        Schema::table('certification_attempts', function (Blueprint $table): void {
            $table->dropColumn([
                'program_code_snapshot', 'program_name_snapshot', 'program_version_snapshot',
                'passing_score_snapshot', 'theory_weight_snapshot', 'practical_weight_snapshot',
                'practical_passing_score_snapshot', 'valid_months_snapshot', 'question_count_snapshot',
                'time_limit_minutes_snapshot', 'required_practical_scenario_ids_snapshot',
                'expires_at',
            ]);
        });

        Schema::table('practical_scenarios', function (Blueprint $table): void {
            $table->dropUnique('practical_scenario_program_code_unique');
            $table->unique('code');
        });

        Schema::table('certification_programs', function (Blueprint $table): void {
            $table->dropUnique('cert_program_code_version_unique');
            $table->dropColumn([
                'question_count', 'time_limit_minutes', 'max_attempts', 'cooldown_hours',
                'shuffle_questions', 'shuffle_options',
            ]);
            $table->unique('code');
        });

        Schema::table('training_programs', function (Blueprint $table): void {
            $table->dropUnique('training_program_code_version_unique');
            $table->unique('code');
        });
    }
};
