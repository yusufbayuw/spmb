<?php

namespace App\Filament\Applicant\Pages;

use App\Models\OnlineExamAttempt;
use App\Services\OnlineExamEngine;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class OnlineExamTake extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Kerjakan Tes Online';
    protected static ?string $slug = 'ujian-online/{attempt}';
    protected static string $view = 'filament.applicant.pages.online-exam-take';

    public string $attemptUuid;

    /** @var array<string, mixed> */
    public array $answers = [];

    public function mount(string $attempt): void
    {
        abort_unless(Str::isUuid($attempt), 404);
        $this->attemptUuid = $attempt;

        $record = $this->record();
        $record = app(OnlineExamEngine::class)->refreshTimeout($record, auth()->user());

        foreach ($record->items as $item) {
            if ($item->answer !== null) {
                $this->answers[(string) $item->id] = $item->answer['value'] ?? null;
            }
        }
    }

    public function updatedAnswers(mixed $value, string $key): void
    {
        $itemId = (int) explode('.', $key)[0];
        $this->saveItem($itemId);
    }

    public function saveItem(int $itemId): void
    {
        if ($itemId <= 0 || ! array_key_exists((string) $itemId, $this->answers)) {
            return;
        }

        try {
            app(OnlineExamEngine::class)->save(
                $this->record(), auth()->user(), $itemId, $this->answers[(string) $itemId],
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Jawaban tidak tersimpan')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Jawaban tidak valid.')
                ->danger()->send();
        }
    }

    public function submitExam(): void
    {
        $engine = app(OnlineExamEngine::class);
        $record = $this->record();
        if ($record->status === 'in_progress' && $record->expires_at->gt(now())) {
            foreach (array_keys($this->answers) as $id) {
                if (ctype_digit((string) $id)) {
                    $engine->save($record, auth()->user(), (int) $id, $this->answers[$id]);
                }
            }
        }

        $engine->submit($record, auth()->user());

        Notification::make()->title('Jawaban ujian telah dikumpulkan')->success()->send();
    }

    protected function getViewData(): array
    {
        $attempt = app(OnlineExamEngine::class)->refreshTimeout($this->record(), auth()->user());
        $attempt->load(['exam', 'items']);

        return [
            'examAttempt' => $attempt,
            'rows' => $attempt->items->map(fn ($item): array => [
                'id' => $item->id,
                'type' => $item->type,
                'prompt' => $item->display_snapshot['prompt'] ?? '',
                'choices' => $item->display_snapshot['choices'] ?? [],
                'prompts' => $item->display_snapshot['prompts'] ?? [],
                'points' => $item->points,
            ])->all(),
        ];
    }

    private function record(): OnlineExamAttempt
    {
        return OnlineExamAttempt::query()
            ->with(['assignment.exam.unit', 'items'])
            ->where('uuid', $this->attemptUuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }
}
