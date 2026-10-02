<x-filament-panels::page>
    @php($run = $this->activeRun())

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Sandbox Terisolasi</x-slot>
            <x-slot name="description">
                Semua record pada ujian praktik berada di tabel sandbox terpisah. Aksi di halaman ini tidak mengubah pendaftar, pembayaran, dokumen, unit, atau pembukaan pendaftaran production.
            </x-slot>
        </x-filament::section>

        @if ($lastResult)
            <x-filament::section>
                <x-slot name="heading">{{ $lastResult['passed'] ? 'Practical Lulus' : 'Practical Belum Lulus' }}</x-slot>
                <x-slot name="description">{{ $lastResult['scenario'] }} · {{ $lastResult['program'] }}</x-slot>

                <div class="space-y-4">
                    <div class="text-3xl font-semibold text-gray-950 dark:text-white">
                        {{ number_format($lastResult['score'], 2, ',', '.') }}
                    </div>

                    <div class="space-y-2">
                        @foreach ($lastResult['assertions'] as $assertion)
                            <div class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                <div>
                                    <div class="font-medium">{{ $assertion['name'] }}</div>
                                    @if ($assertion['feedback'])
                                        <div class="mt-1 text-sm text-gray-500">{{ $assertion['feedback'] }}</div>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    @if ($assertion['critical'])
                                        <x-filament::badge color="danger">Critical</x-filament::badge>
                                    @endif
                                    <x-filament::badge :color="$assertion['passed'] ? 'success' : 'danger'">
                                        {{ $assertion['passed'] ? 'PASS' : 'FAIL' }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($lastResult['certificate_uuid'])
                        <x-filament::button
                            tag="a"
                            href="{{ route('certificates.verify', ['certificate' => $lastResult['certificate_uuid']]) }}"
                            target="_blank"
                            icon="heroicon-m-document-check"
                            color="success"
                        >
                            Lihat Sertifikat {{ $lastResult['certificate_number'] }}
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($run)
            <x-filament::section>
                <x-slot name="heading">{{ $run->scenario->name }}</x-slot>
                <x-slot name="description">
                    {{ $run->scenario->program->name }} · Percobaan ke-{{ $run->attempt_no }}
                    @if ($run->scenario->time_limit_minutes)
                        · Batas {{ $run->scenario->time_limit_minutes }} menit
                    @endif
                </x-slot>

                <div class="space-y-5">
                    <div class="rounded-xl bg-gray-50 p-4 text-sm dark:bg-white/5">
                        {{ $run->scenario->instructions }}
                    </div>

                    @if ($run->isExpired())
                        <div class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-500/20 dark:bg-danger-500/10 dark:text-danger-300">
                            Waktu scenario telah berakhir. Aksi baru dikunci; kirim kondisi terakhir untuk dinilai.
                        </div>
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach ($run->sandboxRecords as $record)
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                <div class="text-xs uppercase tracking-wide text-gray-500">{{ $record->entity_type }}</div>
                                <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $record->label }}</div>

                                <dl class="mt-4 space-y-2 text-sm">
                                    @foreach ($record->state as $key => $value)
                                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-2 last:border-0 dark:border-white/5">
                                            <dt class="text-gray-500">{{ str($key)->headline() }}</dt>
                                            <dd class="text-right font-medium">
                                                @if (is_bool($value))
                                                    {{ $value ? 'Ya' : 'Tidak' }}
                                                @elseif (is_array($value))
                                                    {{ json_encode($value, JSON_UNESCAPED_UNICODE) }}
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div>
                        <div class="mb-3 font-semibold text-gray-950 dark:text-white">Tindakan</div>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($run->scenario->actions as $action)
                                <x-filament::button
                                    wire:click="performAction('{{ $action->uuid }}')"
                                    :color="$this->safeColor($action->button_color)"
                                    :disabled="$run->isExpired()"
                                    @if ($action->requires_confirmation)
                                        wire:confirm="Tindakan ini dapat menyebabkan practical gagal. Tetap jalankan?"
                                    @endif
                                >
                                    {{ $action->label }}
                                </x-filament::button>
                            @endforeach
                        </div>
                    </div>

                    @if ($run->events->isNotEmpty())
                        <div>
                            <div class="mb-3 font-semibold text-gray-950 dark:text-white">Event Log Sandbox</div>
                            <div class="space-y-2">
                                @foreach ($run->events->take(10) as $event)
                                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                                        <span class="font-medium">{{ $event->metadata['label'] ?? $event->action_code }}</span>
                                        <span class="text-gray-500">· {{ $event->target_type }} / {{ $event->target_key }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <x-filament::button wire:click="submitRun" color="success" icon="heroicon-m-check-badge">
                            Kirim Practical untuk Dinilai
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @else
            @php($scenarios = $this->scenarios())

            @forelse ($scenarios as $scenario)
                @php($passed = $this->passedRunFor($scenario))
                @php($canStart = $this->canStart($scenario))

                <x-filament::section>
                    <x-slot name="heading">{{ $scenario->name }}</x-slot>
                    <x-slot name="description">{{ $scenario->program->code }} · {{ $scenario->code }}</x-slot>

                    <div class="space-y-4">
                        @if ($scenario->description)
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $scenario->description }}</p>
                        @endif

                        <div class="flex flex-wrap gap-2">
                            @if ($passed)
                                <x-filament::badge color="success">Practical lulus · {{ number_format((float) $passed->score, 2, ',', '.') }}</x-filament::badge>
                            @elseif ($canStart)
                                <x-filament::badge color="warning">Siap dikerjakan</x-filament::badge>
                            @else
                                <x-filament::badge color="gray">Lulus teori terlebih dahulu</x-filament::badge>
                            @endif

                            @if ($scenario->time_limit_minutes)
                                <x-filament::badge color="gray">{{ $scenario->time_limit_minutes }} menit</x-filament::badge>
                            @endif
                        </div>

                        @if (! $passed && $canStart)
                            <x-filament::button wire:click="startScenario('{{ $scenario->uuid }}')" icon="heroicon-m-play">
                                Mulai Practical Sandbox
                            </x-filament::button>
                        @elseif ($passed)
                            <p class="text-sm text-gray-500">Scenario ini sudah lulus untuk attempt teori terkait.</p>
                        @endif
                    </div>
                </x-filament::section>
            @empty
                <x-filament::section>
                    <div class="py-8 text-center text-gray-500">Belum ada practical scenario aktif untuk sertifikasi Anda.</div>
                </x-filament::section>
            @endforelse
        @endif
    </div>
</x-filament-panels::page>
