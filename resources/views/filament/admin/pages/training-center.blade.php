<x-filament-panels::page>
    @php($attempt = $this->activeAssessmentAttempt())

    <div class="space-y-6">
        @if ($this->governanceWarning())
            <x-filament::section>
                <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-200">
                    {{ $this->governanceWarning() }}
                </div>
            </x-filament::section>
        @endif

        @if ($lastAssessmentResult)
            <x-filament::section>
                <x-slot name="heading">{{ $lastAssessmentResult['status'] === 'passed' ? 'Checkpoint Lulus' : 'Checkpoint Belum Lulus' }}</x-slot>
                <x-slot name="description">{{ $lastAssessmentResult['module'] }}</x-slot>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="text-3xl font-semibold">{{ number_format($lastAssessmentResult['score'], 2, ',', '.') }}</div>
                    <x-filament::badge :color="$lastAssessmentResult['status'] === 'passed' ? 'success' : 'danger'">
                        Minimum {{ $lastAssessmentResult['passing_score'] }}
                    </x-filament::badge>
                </div>
            </x-filament::section>
        @endif

        @if ($attempt)
            <form wire:submit="submitAssessment" class="space-y-6">
                <x-filament::section>
                    <x-slot name="heading">{{ $attempt->assessment->title }}</x-slot>
                    <x-slot name="description">
                        {{ $attempt->assessment->module->program->name }} · {{ $attempt->assessment->module->title }} · Attempt {{ $attempt->attempt_no }}
                    </x-slot>

                    <div class="space-y-5">
                        <div class="rounded-xl bg-gray-50 p-4 text-sm dark:bg-white/5">
                            Soal checkpoint telah dibekukan untuk attempt ini. Nilai minimum {{ $attempt->passing_score_snapshot }}.
                        </div>

                        @foreach ($attempt->attemptQuestions as $question)
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                <div class="font-medium">{{ $loop->iteration }}. {{ $question->question }}</div>
                                <div class="mt-4 space-y-2">
                                    @foreach ($question->resolvedOptions() as $key => $label)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-lg p-2 hover:bg-gray-50 dark:hover:bg-white/5">
                                            <input type="radio" value="{{ $key }}" wire:model="assessmentAnswers.{{ $question->id }}" class="mt-1">
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>

                <div class="flex justify-end">
                    <x-filament::button type="submit" color="success" icon="heroicon-m-check-badge">
                        Kirim Checkpoint
                    </x-filament::button>
                </div>
            </form>
        @else
            @php($programs = $this->programs())

            <x-filament::section>
                <x-slot name="heading">Training Berbasis Role</x-slot>
                <x-slot name="description">
                    Lesson, checkpoint modul, dan urutan belajar membentuk satu jalur kompetensi. Progress tersimpan pada akun masing-masing.
                </x-slot>
            </x-filament::section>

            @forelse ($programs as $program)
                @php($enrollment = $program->enrollments->first())
                @php($progress = $this->progressFor($program))
                @php($completedLessonIds = $enrollment?->progress?->where('status', 'completed')->pluck('training_lesson_id')->all() ?? [])

                <x-filament::section>
                    <x-slot name="heading">{{ $program->name }}</x-slot>
                    <x-slot name="description">{{ $program->code }} · v{{ $program->version }} · {{ $program->roleLabel() }}</x-slot>

                    <div class="space-y-5">
                        @if ($program->description)
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $program->description }}</p>
                        @endif

                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span>Progress keseluruhan</span><span class="font-semibold">{{ $progress }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>

                        @if (! $enrollment)
                            <x-filament::button wire:click="startTraining('{{ $program->uuid }}')" icon="heroicon-m-play">
                                Mulai Training
                            </x-filament::button>
                        @elseif ($enrollment->status === 'completed')
                            <x-filament::badge color="success" icon="heroicon-m-check-circle">Training selesai</x-filament::badge>
                        @endif

                        <div class="space-y-3">
                            @foreach ($program->modules as $module)
                                @php($moduleUnlocked = $enrollment && $this->moduleUnlocked($module, $enrollment))
                                @php($assessment = $module->assessment)
                                @php($assessmentPassed = $assessment ? $this->assessmentPassed($assessment, $enrollment) : false)
                                <details class="rounded-xl border border-gray-200 p-4 dark:border-white/10" @if($loop->first) open @endif>
                                    <summary class="cursor-pointer font-semibold text-gray-950 dark:text-white">
                                        {{ $loop->iteration }}. {{ $module->title }}
                                        @if ($assessmentPassed)
                                            <span class="ml-2 text-xs font-normal text-success-600">Lulus</span>
                                        @elseif (! $moduleUnlocked)
                                            <span class="ml-2 text-xs font-normal text-gray-500">Terkunci</span>
                                        @endif
                                    </summary>

                                    @if ($module->description)
                                        <p class="mt-2 text-sm text-gray-500">{{ $module->description }}</p>
                                    @endif

                                    <div class="mt-4 space-y-3">
                                        @foreach ($module->lessons as $lesson)
                                            @php($lessonCompleted = in_array($lesson->id, $completedLessonIds, true))
                                            @php($lessonUnlocked = $this->lessonUnlocked($lesson, $enrollment))
                                            <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <div class="font-medium">{{ $lesson->title }}</div>
                                                        <div class="mt-1 text-xs text-gray-500">
                                                            {{ $lesson->typeLabel() }}
                                                            @if ($lesson->duration_minutes) · ± {{ $lesson->duration_minutes }} menit @endif
                                                        </div>
                                                    </div>
                                                    @if ($lessonCompleted)
                                                        <x-filament::badge color="success">Selesai</x-filament::badge>
                                                    @elseif (! $lessonUnlocked)
                                                        <x-filament::badge color="gray">Terkunci</x-filament::badge>
                                                    @endif
                                                </div>

                                                @if ($lessonUnlocked || $lessonCompleted)
                                                    @if ($lesson->content)
                                                        <div class="prose prose-sm mt-4 max-w-none dark:prose-invert">
                                                            {!! strip_tags($lesson->content, '<p><br><strong><b><em><i><ul><ol><li><h2><h3><blockquote><code>') !!}
                                                        </div>
                                                    @endif

                                                    @if ($lesson->video_url)
                                                        <div class="mt-3">
                                                            <x-filament::button tag="a" href="{{ $lesson->video_url }}" target="_blank" color="gray" outlined size="sm">Buka Video</x-filament::button>
                                                        </div>
                                                    @endif
                                                @endif

                                                @if (! $lessonCompleted && $lessonUnlocked)
                                                    <div class="mt-4">
                                                        <x-filament::button wire:click="completeLesson('{{ $lesson->uuid }}')" size="sm" icon="heroicon-m-check">
                                                            Tandai Selesai
                                                        </x-filament::button>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach

                                        @if ($assessment && $assessment->is_active)
                                            <div class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-500/20 dark:bg-primary-500/10">
                                                <div class="flex flex-wrap items-center justify-between gap-3">
                                                    <div>
                                                        <div class="font-semibold">{{ $assessment->title }}</div>
                                                        <div class="mt-1 text-xs text-gray-500">
                                                            Nilai minimum {{ $assessment->passing_score }}
                                                            @if ($assessment->max_attempts) · maks. {{ $assessment->max_attempts }} attempt @endif
                                                        </div>
                                                    </div>

                                                    @if ($assessmentPassed)
                                                        <x-filament::badge color="success">Checkpoint lulus</x-filament::badge>
                                                    @elseif ($this->assessmentReady($assessment, $enrollment))
                                                        <x-filament::button wire:click="startAssessment('{{ $assessment->uuid }}')" size="sm" icon="heroicon-m-pencil-square">
                                                            Mulai Checkpoint
                                                        </x-filament::button>
                                                    @else
                                                        <x-filament::badge color="gray">Selesaikan lesson dahulu</x-filament::badge>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </div>
                </x-filament::section>
            @empty
                <x-filament::section>
                    <div class="py-8 text-center text-gray-500">Belum ada program training aktif untuk role Anda.</div>
                </x-filament::section>
            @endforelse
        @endif
    </div>
</x-filament-panels::page>
