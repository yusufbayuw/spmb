<?php

namespace Database\Seeders\Training;

use App\Models\CertificationProgram;
use App\Models\CertificationQuestion;
use App\Models\PracticalAssertion;
use App\Models\PracticalScenario;
use App\Models\PracticalScenarioAction;
use App\Models\PracticalScenarioRecord;
use App\Models\TrainingLesson;
use App\Models\TrainingModule;
use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;

abstract class CurriculumSeeder extends Seeder
{
    protected function ensureTrainingProgram(array $attributes): TrainingProgram
    {
        return TrainingProgram::query()->firstOrCreate(
            ['code' => $attributes['code']],
            $attributes + ['is_active' => true],
        );
    }

    protected function ensureCertificationProgram(
        TrainingProgram $training,
        array $attributes,
    ): CertificationProgram {
        return CertificationProgram::query()->firstOrCreate(
            ['code' => $attributes['code']],
            [
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'target_role' => $training->target_role,
                'version' => $attributes['version'] ?? $training->version,
                'passing_score' => $attributes['passing_score'],
                'theory_weight' => $attributes['theory_weight'] ?? 40,
                'practical_weight' => $attributes['practical_weight'] ?? 60,
                'practical_passing_score' => $attributes['practical_passing_score'] ?? 80,
                'valid_months' => $attributes['valid_months'] ?? 24,
                'training_program_id' => $training->id,
                'is_active' => true,
                'sort_order' => $attributes['sort_order'] ?? $training->sort_order,
            ],
        );
    }

    protected function seedModule(
        TrainingProgram $program,
        string $seedKey,
        string $title,
        string $description,
        int $sortOrder,
        array $lessons,
    ): TrainingModule {
        $module = TrainingModule::query()
            ->where('training_program_id', $program->id)
            ->where('seed_key', $seedKey)
            ->first();

        if (! $module) {
            $module = TrainingModule::query()
                ->where('training_program_id', $program->id)
                ->whereNull('seed_key')
                ->where('title', $title)
                ->first();

            if ($module) {
                $module->update(['seed_key' => $seedKey]);
            }
        }

        $module ??= TrainingModule::query()->create([
            'training_program_id' => $program->id,
            'seed_key' => $seedKey,
            'title' => $title,
            'description' => $description,
            'sort_order' => $sortOrder,
            'is_required' => true,
        ]);

        foreach ($lessons as $index => $lesson) {
            $this->seedLesson(
                $module,
                $seedKey.'.'.($lesson['key'] ?? str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)),
                $lesson,
                $index + 1,
            );
        }

        return $module;
    }

    protected function seedLesson(
        TrainingModule $module,
        string $seedKey,
        array $lesson,
        int $sortOrder,
    ): TrainingLesson {
        $record = TrainingLesson::query()
            ->where('training_module_id', $module->id)
            ->where('seed_key', $seedKey)
            ->first();

        if (! $record) {
            $record = TrainingLesson::query()
                ->where('training_module_id', $module->id)
                ->whereNull('seed_key')
                ->where('title', $lesson['title'])
                ->first();

            if ($record) {
                $legacyPlaceholder = '<p>'.($module->description ?? '').'</p>';
                $updates = ['seed_key' => $seedKey];

                if (($record->content === null || trim((string) $record->content) === trim($legacyPlaceholder))
                    && filled($lesson['content'] ?? null)) {
                    $updates['content'] = $lesson['content'];
                    $updates['type'] = $lesson['type'] ?? 'guide';
                    $updates['duration_minutes'] = $lesson['duration'] ?? null;
                }

                $record->update($updates);
            }
        }

        return $record ?? TrainingLesson::query()->create([
            'training_module_id' => $module->id,
            'seed_key' => $seedKey,
            'title' => $lesson['title'],
            'type' => $lesson['type'] ?? 'guide',
            'content' => $lesson['content'] ?? null,
            'video_url' => $lesson['video_url'] ?? null,
            'duration_minutes' => $lesson['duration'] ?? null,
            'sort_order' => $sortOrder,
            'is_required' => $lesson['required'] ?? true,
        ]);
    }

    protected function lesson(
        string $title,
        string $objective,
        array $concepts,
        array $steps,
        array $mistakes,
        string $summary,
        int $duration = 20,
        string $type = 'guide',
        ?string $key = null,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'type' => $type,
            'duration' => $duration,
            'content' => $this->renderLesson(
                $title,
                $objective,
                $concepts,
                $steps,
                $mistakes,
                $summary,
            ),
        ];
    }

    protected function renderLesson(
        string $title,
        string $objective,
        array $concepts,
        array $steps,
        array $mistakes,
        string $summary,
    ): string {
        $list = fn (array $items): string => '<ul>'.collect($items)
            ->map(fn (string $item): string => '<li>'.e($item).'</li>')
            ->implode('').'</ul>';

        return '<h2>'.e($title).'</h2>'
            .'<h3>Tujuan Pembelajaran</h3><p>'.e($objective).'</p>'
            .'<h3>Mengapa Ini Penting</h3><p>Kesalahan pada bagian ini dapat berdampak langsung pada kelancaran penerimaan, kualitas data, kewenangan petugas, atau pengalaman pendaftar. Karena itu keputusan harus mengikuti workflow dan batas akses SPMB.</p>'
            .'<h3>Konsep Utama</h3>'.$list($concepts)
            .'<h3>Langkah Operasional</h3><ol>'.collect($steps)
                ->map(fn (string $item): string => '<li>'.e($item).'</li>')
                ->implode('').'</ol>'
            .'<h3>Kesalahan yang Sering Terjadi</h3>'.$list($mistakes)
            .'<h3>Ringkasan</h3><p>'.e($summary).'</p>';
    }

    protected function seedChoiceQuestion(
        CertificationProgram $program,
        string $seedKey,
        string $question,
        array $options,
        string $correct,
        string $explanation,
        int $sortOrder,
        float $weight = 1,
    ): CertificationQuestion {
        return $this->seedQuestion($program, [
            'seed_key' => $seedKey,
            'type' => 'single_choice',
            'question' => $question,
            'options' => $options,
            'correct_answer' => $correct,
            'explanation' => $explanation,
            'weight' => $weight,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    protected function seedTrueFalseQuestion(
        CertificationProgram $program,
        string $seedKey,
        string $question,
        bool $correct,
        string $explanation,
        int $sortOrder,
        float $weight = 1,
    ): CertificationQuestion {
        return $this->seedQuestion($program, [
            'seed_key' => $seedKey,
            'type' => 'true_false',
            'question' => $question,
            'options' => null,
            'correct_answer' => $correct ? '1' : '0',
            'explanation' => $explanation,
            'weight' => $weight,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    protected function seedQuestion(CertificationProgram $program, array $attributes): CertificationQuestion
    {
        $record = CertificationQuestion::query()
            ->where('certification_program_id', $program->id)
            ->where('seed_key', $attributes['seed_key'])
            ->first();

        if (! $record) {
            $record = CertificationQuestion::query()
                ->where('certification_program_id', $program->id)
                ->whereNull('seed_key')
                ->where('question', $attributes['question'])
                ->first();

            if ($record) {
                $record->update(['seed_key' => $attributes['seed_key']]);
            }
        }

        return $record ?? CertificationQuestion::query()->create(
            ['certification_program_id' => $program->id] + $attributes,
        );
    }

    protected function seedScenario(
        CertificationProgram $program,
        array $scenario,
    ): PracticalScenario {
        return PracticalScenario::query()->firstOrCreate(
            ['code' => $scenario['code']],
            [
                'certification_program_id' => $program->id,
                'name' => $scenario['name'],
                'description' => $scenario['description'],
                'instructions' => $scenario['instructions'],
                'time_limit_minutes' => $scenario['time_limit_minutes'] ?? 15,
                'sort_order' => $scenario['sort_order'] ?? 0,
                'is_active' => true,
            ],
        );
    }

    protected function seedScenarioRecord(
        PracticalScenario $scenario,
        string $type,
        string $key,
        string $label,
        array $state,
        int $sortOrder,
    ): PracticalScenarioRecord {
        return PracticalScenarioRecord::query()->firstOrCreate(
            [
                'practical_scenario_id' => $scenario->id,
                'entity_type' => $type,
                'entity_key' => $key,
            ],
            [
                'label' => $label,
                'initial_state' => $state,
                'sort_order' => $sortOrder,
            ],
        );
    }

    protected function seedScenarioAction(
        PracticalScenario $scenario,
        string $code,
        string $label,
        string $targetType,
        string $targetKey,
        array $mutation,
        array $allowedWhen,
        string $color,
        int $sortOrder,
    ): PracticalScenarioAction {
        return PracticalScenarioAction::query()->firstOrCreate(
            ['practical_scenario_id' => $scenario->id, 'code' => $code],
            [
                'label' => $label,
                'target_type' => $targetType,
                'target_key' => $targetKey,
                'mutation' => $mutation,
                'allowed_when' => $allowedWhen,
                'button_color' => $color,
                'requires_confirmation' => $color === 'danger',
                'sort_order' => $sortOrder,
                'is_active' => true,
            ],
        );
    }

    protected function seedScenarioAssertion(
        PracticalScenario $scenario,
        string $code,
        string $name,
        string $validator,
        array $config,
        float $points,
        bool $critical,
        int $sortOrder,
    ): PracticalAssertion {
        return PracticalAssertion::query()->firstOrCreate(
            ['practical_scenario_id' => $scenario->id, 'code' => $code],
            [
                'name' => $name,
                'validator_class' => $validator,
                'config' => $config,
                'points' => $points,
                'is_critical' => $critical,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ],
        );
    }
}
