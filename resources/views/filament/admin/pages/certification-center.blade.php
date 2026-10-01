<x-filament-panels::page>
    @php($attempt = $this->activeAttempt())

    <div class="space-y-6">
        @if ($lastResult)
            <x-filament::section>
                <x-slot name="heading">{{ $lastResult['status'] === 'passed' ? 'Ujian Lulus' : 'Ujian Belum Lulus' }}</x-slot>
                <div class="space-y-3">
                    <div class="text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($lastResult['score'], 2, ',', '.') }}</div>
                    <p class="text-sm text-gray-500">Nilai minimum: {{ $lastResult['passing_score'] }}</p>
                    @if ($lastResult['certificate_uuid'])
                        <x-filament::button tag="a" href="{{ route('certificates.verify', ['certificate' => $lastResult['certificate_uuid']]) }}" target="_blank" icon="heroicon-m-document-check">
                            Lihat Bukti Kelulusan
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($attempt)
            <form wire:submit="submitExam" class="space-y-6">
                <x-filament::section>
                    <x-slot name="heading">{{ $attempt->program->name }}</x-slot>
                    <x-slot name="description">Percobaan ke-{{ $attempt->attempt_no }} · Nilai minimum {{ $attempt->program->passing_score }}</x-slot>

                    <div class="space-y-6">
                        @foreach ($attempt->program->questions as $question)
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
                <div class="flex justify-end"><x-filament::button type="submit" color="success" icon="heroicon-m-check-badge">Kirim Jawaban</x-filament::button></div>
            </form>
        @else
            @php($programs = $this->programs())
            <x-filament::section>
                <x-slot name="heading">Sertifikasi Kompetensi SPMB</x-slot>
                <x-slot name="description">Training dan ujian dipisahkan. Sertifikat hanya diterbitkan setelah syarat training terpenuhi dan nilai ujian mencapai batas kelulusan.</x-slot>
            </x-filament::section>

            @forelse ($programs as $program)
                @php($eligible = $this->eligible($program))
                @php($certificate = $program->certifications->first(fn ($item) => $item->isValid()))
                @php($questionCount = $program->questions->count())

                <x-filament::section>
                    <x-slot name="heading">{{ $program->name }}</x-slot>
                    <x-slot name="description">{{ $program->code }} · v{{ $program->version }} · berlaku {{ $program->valid_months }} bulan</x-slot>
                    <div class="space-y-4">
                        @if ($program->description)<p class="text-sm text-gray-600 dark:text-gray-300">{{ $program->description }}</p>@endif
                        <div class="flex flex-wrap gap-2">
                            <x-filament::badge :color="$eligible ? 'success' : 'warning'">{{ $eligible ? 'Syarat training terpenuhi' : 'Training belum selesai' }}</x-filament::badge>
                            <x-filament::badge color="gray">{{ $questionCount }} soal aktif</x-filament::badge>
                            <x-filament::badge color="gray">Lulus ≥ {{ $program->passing_score }}</x-filament::badge>
                        </div>

                        @if ($certificate)
                            <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-500/20 dark:bg-success-500/10">
                                <div class="font-semibold text-success-700 dark:text-success-300">Sertifikat aktif</div>
                                <div class="mt-1 font-mono text-xs">{{ $certificate->certificate_number }}</div>
                                <div class="mt-3"><x-filament::button tag="a" href="{{ route('certificates.verify', $certificate) }}" target="_blank" size="sm" color="success">Lihat Sertifikat</x-filament::button></div>
                            </div>
                        @elseif (! $eligible)
                            <p class="text-sm text-gray-500">Selesaikan program {{ $program->trainingProgram?->name ?? 'training terkait' }} terlebih dahulu.</p>
                        @elseif ($questionCount === 0)
                            <p class="text-sm text-gray-500">Soal ujian belum dipublikasikan oleh Admin Pusat.</p>
                        @else
                            <x-filament::button wire:click="startExam('{{ $program->uuid }}')" icon="heroicon-m-pencil-square">Mulai Ujian Sertifikasi</x-filament::button>
                        @endif
                    </div>
                </x-filament::section>
            @empty
                <x-filament::section><div class="py-8 text-center text-gray-500">Belum ada program sertifikasi aktif untuk role Anda.</div></x-filament::section>
            @endforelse
        @endif
    </div>
</x-filament-panels::page>
