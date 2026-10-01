<x-filament-panels::page>
    @php($programs = $this->programs())

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Training Berbasis Role</x-slot>
            <x-slot name="description">
                Selesaikan training sesuai role Anda sebelum mengikuti ujian sertifikasi. Progress tersimpan pada akun masing-masing.
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
                            <span>Progress</span><span class="font-semibold">{{ $progress }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                            <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>

                    @if (! $enrollment)
                        <x-filament::button wire:click="startTraining('{{ $program->uuid }}')" icon="heroicon-m-play">Mulai Training</x-filament::button>
                    @elseif ($enrollment->status === 'completed')
                        <x-filament::badge color="success" icon="heroicon-m-check-circle">Training selesai</x-filament::badge>
                    @endif

                    <div class="space-y-3">
                        @foreach ($program->modules as $module)
                            <details class="rounded-xl border border-gray-200 p-4 dark:border-white/10" @if($loop->first) open @endif>
                                <summary class="cursor-pointer font-semibold text-gray-950 dark:text-white">
                                    {{ $loop->iteration }}. {{ $module->title }}
                                    @if ($module->is_required)<span class="ml-2 text-xs font-normal text-gray-500">Wajib</span>@endif
                                </summary>
                                @if ($module->description)<p class="mt-2 text-sm text-gray-500">{{ $module->description }}</p>@endif

                                <div class="mt-4 space-y-3">
                                    @foreach ($module->lessons as $lesson)
                                        @php($lessonCompleted = in_array($lesson->id, $completedLessonIds, true))
                                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <div class="font-medium text-gray-950 dark:text-white">{{ $lesson->title }}</div>
                                                    <div class="mt-1 text-xs text-gray-500">
                                                        {{ $lesson->typeLabel() }} @if ($lesson->duration_minutes) · ± {{ $lesson->duration_minutes }} menit @endif
                                                    </div>
                                                </div>
                                                @if ($lessonCompleted)<x-filament::badge color="success">Selesai</x-filament::badge>@endif
                                            </div>

                                            @if ($lesson->content)
                                                <div class="prose prose-sm mt-4 max-w-none dark:prose-invert">
                                                    {!! strip_tags($lesson->content, '<p><br><strong><b><em><i><ul><ol><li><h2><h3><blockquote><code>') !!}
                                                </div>
                                            @endif

                                            @if ($lesson->video_url)
                                                <div class="mt-3"><x-filament::button tag="a" href="{{ $lesson->video_url }}" target="_blank" color="gray" outlined size="sm">Buka Video</x-filament::button></div>
                                            @endif

                                            @if (! $lessonCompleted)
                                                <div class="mt-4">
                                                    <x-filament::button wire:click="completeLesson('{{ $lesson->uuid }}')" size="sm" icon="heroicon-m-check">Tandai Selesai</x-filament::button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>
            </x-filament::section>
        @empty
            <x-filament::section><div class="py-8 text-center text-gray-500">Belum ada program training aktif untuk role Anda.</div></x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
