<?php

namespace App\Services;

use App\Models\OnlineExam;
use App\Models\OnlineExamCategory;
use App\Models\OnlineExamQuestion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OnlineExamBlueprint
{
    public const DIFFICULTIES = [
        'easy' => 'Mudah',
        'medium' => 'Sedang',
        'hard' => 'Sulit',
    ];

    public function isEnabled(OnlineExam $exam): bool
    {
        return ! empty($exam->blueprint);
    }

    /**
     * Verify that the proposed blueprint is valid AND can be fulfilled.
     * Called on publish, on edit, and again before drawing questions.
     */
    public function validate(OnlineExam $exam): void
    {
        $rows = $exam->blueprint ?? [];

        if ($rows === []) {
            return;
        }

        if (! is_array($rows) || count($rows) > 20 || ! array_is_list($rows)) {
            $this->invalid();
        }

        if ($exam->exists && $exam->items()->exists()) {
            throw ValidationException::withMessages([
                'blueprint' => 'Pilih salah satu: blueprint acak ATAU daftar soal manual. Kosongkan Isi Paket Tes terlebih dahulu.',
            ]);
        }

        $seen = [];
        $total = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['category_id'], $row['count'])
                || filter_var($row['category_id'], FILTER_VALIDATE_INT) === false
                || filter_var($row['count'], FILTER_VALIDATE_INT) === false) {
                $this->invalid();
            }

            $categoryId = (int) $row['category_id'];
            $count = (int) $row['count'];
            $difficulty = (string) ($row['difficulty'] ?? 'any');

            if ($categoryId < 1 || isset($seen[$categoryId]) || $count < 1 || $count > 200
                || ! in_array($difficulty, ['any', ...array_keys(self::DIFFICULTIES)], true)) {
                $this->invalid();
            }

            $seen[$categoryId] = true;
            $total += $count;

            $category = OnlineExamCategory::query()->whereKey($categoryId)
                ->where('unit_id', $exam->unit_id)->where('is_active', true)->first();

            if (! $category) {
                $this->invalid();
            }

            if ($this->eligible($exam, $categoryId, $difficulty)->count() < $count) {
                throw ValidationException::withMessages([
                    'blueprint' => 'Bank soal kategori '.$category->name.' tidak mencukupi jumlah yang diminta.',
                ]);
            }
        }

        if ($total < 1 || $total > 300) {
            $this->invalid();
        }
    }

    /** @return Collection<int, OnlineExamQuestion> */
    public function draw(OnlineExam $exam): Collection
    {
        // Must recheck the live pool when a participant actually starts.
        $this->validate($exam);

        return collect($exam->blueprint)->flatMap(function (array $row) use ($exam) {
            return $this->eligible(
                $exam,
                (int) $row['category_id'],
                (string) ($row['difficulty'] ?? 'any')
            )->inRandomOrder()->limit((int) $row['count'])->get();
        })->values();
    }

    private function eligible(OnlineExam $exam, int $categoryId, string $difficulty): \Illuminate\Database\Eloquent\Builder
    {
        return OnlineExamQuestion::query()
            ->where('unit_id', $exam->unit_id)
            ->where('online_exam_category_id', $categoryId)
            ->where('is_active', true)
            ->when($difficulty !== 'any', fn ($q) => $q->where('difficulty', $difficulty));
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages([
            'blueprint' => 'Blueprint harus berisi kategori unit yang unik, jumlah 1–200 per kategori, dan kesulitan yang valid (total maksimal 300 soal).',
        ]);
    }
}
