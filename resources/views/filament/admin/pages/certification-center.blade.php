<x-filament-panels::page>
    @php($attempt = $this->activeAttempt())

    <div class="space-y-6">
        @if ($lastResult)
            <x-filament::section>
                <x-slot name="heading">{{ $lastResult['status'] === 'passed' ? 'Ujian Teori Lulus' : 'Ujian Teori Belum Lulus' }}</x-slot>
                <div class="space-y-3">
                    <div class="text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($lastResult['score'], 2, ',', '.') }}</div>
                    <p class="text-sm text-gray-500">Nilai minimum teori: {{ $lastResult['passing_score'] }}</p>

                    @if ($lastResult['requires_practical'])
                        <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm dark:border-warning-500/20 dark:bg-warning-500/10">
                            Teori sudah lulus. Sertifikat belum diterbitkan sampai seluruh practical scenario wajib lulus.
                        </div>
                        <x-filament::button tag="a" href="{{ \App\Filament\Admin\Pages\PracticalSandbox::getUrl() }}" icon="heroicon-m-beaker">
                            Lanjut ke Ujian Praktik
                        </x-filament::button>
                    @elseif ($lastResult['certificate_uuid'])
                        <x-filament::button
                            tag="a"
                            href="{{ route('certificates.verify', ['certificate' => $lastResult['certificate_uuid']]) }}"
                            target="_blank"
                            icon="heroicon-m-document-check"
                        >
                            Lihat Bukti Kelulusan
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($attempt)
            <form wire:submit="submitExam" class="space-y-6">
                <x-filament::section>
                    <x-slot name="heading">{{ $attempt->program_name_snapshot ?: $attempt->program->name }}</x-slot>
                    <x-slot name="description">
                        {{ $attempt->program_code_snapshot ?: $attempt->program->code }}
                        · v{{ $attempt->program_version_snapshot ?: $attempt->program->version }}
                        · Percobaan ke-{{ $attempt->attempt_no }}
                        · Nilai minimum {{ $attempt->passing_score_snapshot ?? $attempt->program->passing_score }}
                    </x-slot>

                    <div class="space-y-5">
                        <div class="flex flex-wrap gap-2">
                            <x-filament::badge color="gray">{{ $attempt->attemptQuestions->count() }} soal snapshot</x-filament::badge>
                            @if ($attempt->time_limit_minutes_snapshot)
                                <x-filament::badge color="warning">{{ $attempt->time_limit_minutes_snapshot }} menit</x-filament::badge>
                            @endif
                            @if ($attempt->expires_at)
                                <x-filament::badge :color="$attempt->isExpired() ? 'danger' : 'gray'">
                                    Batas {{ $attempt->expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                </x-filament::badge>
                            @endif
                        </div>

                        <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
                            Paket soal, opsi, kunci, bobot, passing score, dan practical requirement telah dibekukan saat ujian dimulai.
                        </div>

                        @foreach ($attempt->attemptQuestions as $question)
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                <div class="font-medium text-gray-950 dark:text-white">{{ $loop->iteration }}. {{ $question->question }}</div>
                                <div class="mt-4 space-y-2">
                                    @foreach ($question->resolvedOptions() as $key => $label)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-lg p-2 hover:bg-gray-50 dark:hover:bg-white/5">
                                            <input type="radio" value="{{ $key }}" wire:model="answers.{{ $question->id }}" class="mt-1">
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
                        Kirim Jawaban Teori
                    </x-filament::button>
                </div>
            </form>
        @else
            @php($programs = $this->programs())

            <x-filament::section>
                <x-slot name="heading">Sertifikasi Kompetensi SPMB</x-slot>
                <x-slot name="description">
                    Training memberi eligibility. Setiap attempt memakai snapshot immutable sehingga perubahan bank soal atau konfigurasi tidak mengubah attempt yang sudah dimulai.
                </x-slot>
            </x-filament::section>

            @forelse ($programs as $program)
                @php($eligible = $this->eligible($program))
                @php($certificate = $program->certifications->first(fn ($item) => $item->isValid()))
                @php($theory = $certificate?->attempt ?? $this->latestPassedTheory($program))
                @php($bankCount = $program->questions->count())
                @php($attemptQuestionCount = $program->question_count ? min($program->question_count, $bankCount) : $bankCount)
                @php($practicalRequired = $theory ? collect($theory->requiredPracticalScenarioIds())->isNotEmpty() : $program->practicalScenarios->isNotEmpty())
                @php($practicalComplete = $certificate ? true : ($theory ? $this->practicalComplete($program) : false))

                <x-filament::section>
                    <x-slot name="heading">{{ $program->name }}</x-slot>
                    <x-slot name="description">{{ $program->code }} · v{{ $program->version }} · berlaku {{ $program->valid_months }} bulan</x-slot>

                    <div class="space-y-4">
                        @if ($program->description)
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $program->description }}</p>
                        @endif

                        <div class="flex flex-wrap gap-2">
                            <x-filament::badge :color="$eligible ? 'success' : 'warning'">
                                {{ $eligible ? 'Training selesai' : 'Training belum selesai' }}
                            </x-filament::badge>
                            <x-filament::badge :color="$theory ? 'success' : 'gray'">
                                {{ $theory ? 'Teori lulus · '.number_format((float) $theory->score, 2, ',', '.') : 'Teori belum lulus' }}
                            </x-filament::badge>
                            @if ($practicalRequired)
                                <x-filament::badge :color="$practicalComplete ? 'success' : 'warning'">
                                    {{ $practicalComplete ? 'Practical lulus' : 'Practical belum lengkap' }}
                                </x-filament::badge>
                            @endif
                            <x-filament::badge color="gray">{{ $attemptQuestionCount }} dari {{ $bankCount }} soal</x-filament::badge>
                            @if ($program->time_limit_minutes)
                                <x-filament::badge color="gray">{{ $program->time_limit_minutes }} menit</x-filament::badge>
                            @endif
                        </div>

                        <p class="text-xs text-gray-500">
                            Teori ≥ {{ $program->passing_score }}
                            · Practical ≥ {{ $program->practical_passing_score }}
                            · Bobot {{ $program->theory_weight }}% / {{ $program->practical_weight }}%
                            @if ($program->max_attempts)
                                · Maks. {{ $program->max_attempts }} attempt/siklus
                            @endif
                            @if ($program->cooldown_hours)
                                · Retry {{ $program->cooldown_hours }} jam
                            @endif
                        </p>

                        @if ($certificate)
                            <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-500/20 dark:bg-success-500/10">
                                <div class="font-semibold text-success-700 dark:text-success-300">Sertifikat aktif</div>
                                <div class="mt-1 font-mono text-xs">{{ $certificate->certificate_number }}</div>
                                <div class="mt-3">
                                    <x-filament::button tag="a" href="{{ route('certificates.verify', $certificate) }}" target="_blank" size="sm" color="success">
                                        Lihat Sertifikat
                                    </x-filament::button>
                                </div>
                            </div>
                        @elseif (! $eligible)
                            <p class="text-sm text-gray-500">Selesaikan {{ $program->trainingProgram?->name ?? 'training terkait' }} terlebih dahulu.</p>
                        @elseif (! $theory && $bankCount === 0)
                            <p class="text-sm text-gray-500">Soal teori belum dipublikasikan oleh Admin Pusat.</p>
                        @elseif (! $theory)
                            <x-filament::button wire:click="startExam('{{ $program->uuid }}')" icon="heroicon-m-pencil-square">
                                Mulai Ujian Teori
                            </x-filament::button>
                        @elseif ($practicalRequired && ! $practicalComplete)
                            <x-filament::button tag="a" href="{{ \App\Filament\Admin\Pages\PracticalSandbox::getUrl() }}" icon="heroicon-m-beaker">
                                Lanjut Ujian Praktik
                            </x-filament::button>
                        @endif
                    </div>
                </x-filament::section>
            @empty
                <x-filament::section>
                    <div class="py-8 text-center text-gray-500">Belum ada program sertifikasi aktif untuk role Anda.</div>
                </x-filament::section>
            @endforelse
        @endif
    </div>
</x-filament-panels::page>
