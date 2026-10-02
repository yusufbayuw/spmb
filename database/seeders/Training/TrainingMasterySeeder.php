<?php

namespace Database\Seeders\Training;

use App\Models\TrainingModule;
use App\Models\TrainingModuleAssessment;
use App\Models\TrainingModuleQuestion;
use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;

class TrainingMasterySeeder extends Seeder
{
    public function run(): void
    {
        TrainingProgram::query()
            ->with('modules.lessons')
            ->orderBy('id')
            ->get()
            ->each(fn (TrainingProgram $program) => $this->seedProgram($program));
    }

    private function seedProgram(TrainingProgram $program): void
    {
        $modules = $program->modules->values();

        foreach ($modules as $moduleIndex => $module) {
            if (! $module->seed_key || $module->lessons->isEmpty()) {
                continue;
            }

            $assessment = TrainingModuleAssessment::query()->firstOrCreate(
                ['training_module_id' => $module->id],
                [
                    'seed_key' => $module->seed_key.'.mastery',
                    'title' => 'Checkpoint: '.$module->title,
                    'description' => 'Checkpoint ringkas untuk memastikan peserta memahami fokus utama modul sebelum melanjutkan.',
                    'passing_score' => 80,
                    'question_count' => 3,
                    'max_attempts' => 5,
                    'is_required' => true,
                    'is_active' => true,
                ],
            );

            if (! $assessment->seed_key) {
                $assessment->update(['seed_key' => $module->seed_key.'.mastery']);
            }

            $otherModules = $modules
                ->reject(fn (TrainingModule $item) => $item->id === $module->id)
                ->values();

            $focusOptions = collect([$module->description])
                ->merge($otherModules->pluck('description'))
                ->filter()
                ->unique()
                ->take(4)
                ->values();

            while ($focusOptions->count() < 4) {
                $focusOptions->push('Fokus operasional lain di luar cakupan modul ini.');
            }

            $correctTopic = (string) $module->lessons->first()->title;
            $otherTopics = $otherModules
                ->flatMap(fn (TrainingModule $item) => $item->lessons->pluck('title'))
                ->filter()
                ->unique()
                ->take(3)
                ->values();

            while ($otherTopics->count() < 3) {
                $otherTopics->push('Topik dari modul lain');
            }

            $moduleTopics = $module->lessons->pluck('title')->values();
            $notBelong = (string) ($otherTopics->first() ?: 'Topik dari modul lain');

            $questions = [
                [
                    'seed_key' => $module->seed_key.'.mastery.focus',
                    'question' => 'Pernyataan mana yang paling tepat menggambarkan fokus modul “'.$module->title.'”?',
                    'options' => [
                        'A' => (string) $focusOptions[0],
                        'B' => (string) $focusOptions[1],
                        'C' => (string) $focusOptions[2],
                        'D' => (string) $focusOptions[3],
                    ],
                    'correct_answer' => 'A',
                    'explanation' => (string) $module->description,
                ],
                [
                    'seed_key' => $module->seed_key.'.mastery.topic',
                    'question' => 'Topik mana yang memang dipelajari pada modul “'.$module->title.'”?',
                    'options' => [
                        'A' => $correctTopic,
                        'B' => (string) $otherTopics[0],
                        'C' => (string) $otherTopics[1],
                        'D' => (string) $otherTopics[2],
                    ],
                    'correct_answer' => 'A',
                    'explanation' => 'Topik tersebut merupakan salah satu materi wajib pada modul ini.',
                ],
                [
                    'seed_key' => $module->seed_key.'.mastery.boundary',
                    'question' => 'Manakah topik yang berada di luar cakupan modul “'.$module->title.'”?',
                    'options' => [
                        'A' => (string) ($moduleTopics[0] ?? $module->title),
                        'B' => (string) ($moduleTopics[1] ?? $module->title),
                        'C' => (string) ($moduleTopics[2] ?? $module->title),
                        'D' => $notBelong,
                    ],
                    'correct_answer' => 'D',
                    'explanation' => 'Pilihan D berasal dari modul lain, sedangkan pilihan lainnya merupakan materi pada modul ini.',
                ],
            ];

            foreach ($questions as $index => $question) {
                TrainingModuleQuestion::query()->firstOrCreate(
                    [
                        'training_module_assessment_id' => $assessment->id,
                        'seed_key' => $question['seed_key'],
                    ],
                    [
                        'type' => 'single_choice',
                        'question' => $question['question'],
                        'options' => $question['options'],
                        'correct_answer' => $question['correct_answer'],
                        'explanation' => $question['explanation'],
                        'weight' => 1,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
