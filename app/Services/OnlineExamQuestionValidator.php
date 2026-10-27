<?php

namespace App\Services;

use App\Models\OnlineExamQuestion;
use Illuminate\Validation\ValidationException;

class OnlineExamQuestionValidator
{
    public function validate(OnlineExamQuestion $question): void
    {
        $type = (string) $question->type;
        $options = $question->options ?? [];
        $key = $question->grading_config ?? [];

        $valid = isset(OnlineExamQuestion::TYPES[$type])
            && trim((string) $question->prompt) !== ''
            && (float) $question->points > 0
            && (float) $question->points <= 10000
            && is_array($options) && is_array($key);

        $choices = $options['choices'] ?? [];
        if (in_array($type, ['single_choice', 'multiple_choice', 'matching'], true)) {
            $valid = $valid && is_array($choices)
                && count($choices) >= 2 && count($choices) <= 30
                && $this->validLabels($choices);
        }

        if ($type === 'single_choice') {
            $valid = $valid && is_scalar($key['correct'] ?? null)
                && array_key_exists((string) $key['correct'], $choices);
        } elseif ($type === 'multiple_choice') {
            $answers = $key['correct_options'] ?? null;
            $valid = $valid && is_array($answers) && $answers !== []
                && count($answers) === count(array_unique(array_map('strval', $answers)))
                && collect($answers)->every(fn ($answer) => is_scalar($answer) && array_key_exists((string) $answer, $choices));
        } elseif ($type === 'true_false') {
            $valid = $valid && in_array((string) ($key['correct'] ?? ''), ['0', '1'], true);
        } elseif ($type === 'matching') {
            $prompts = $options['prompts'] ?? null;
            $pairs = $key['pairs'] ?? null;
            $valid = $valid && is_array($prompts) && count($prompts) >= 2
                && count($prompts) <= 30 && $this->validLabels($prompts)
                && is_array($pairs) && count($pairs) === count($prompts)
                && collect($prompts)->keys()->every(fn ($id) => isset($pairs[$id]) && array_key_exists((string) $pairs[$id], $choices));
        } elseif ($type === 'short_answer') {
            $answers = $key['accepted'] ?? null;
            $valid = $valid && is_array($answers) && $answers !== []
                && count($answers) <= 30
                && collect($answers)->every(fn ($answer) => is_string($answer) && trim($answer) !== '');
        } elseif ($type === 'numerical') {
            $valid = $valid && is_numeric($key['value'] ?? null)
                && is_numeric($key['tolerance'] ?? null)
                && is_finite((float) ($key['value'] ?? 0))
                && is_finite((float) ($key['tolerance'] ?? 0))
                && (float) $key['tolerance'] >= 0
                && (float) $key['tolerance'] <= 1000000;
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'grading_config' => 'Opsi, bobot, atau kunci jawaban soal belum sesuai jenis yang dipilih.',
            ]);
        }
    }

    private function validLabels(array $items): bool
    {
        return collect($items)->every(
            fn ($label, $id): bool => is_scalar($id) && preg_match('/^[A-Za-z0-9_]{1,30}$/', (string) $id) === 1
                && is_string($label) && trim($label) !== '' && mb_strlen($label) <= 800
        );
    }
}
