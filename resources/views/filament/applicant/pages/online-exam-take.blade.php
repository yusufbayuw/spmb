<x-filament-panels::page>
    <div class="space-y-5" @if ($examAttempt->status === 'in_progress') wire:poll.15s @endif>
        <x-filament::section :heading="$examAttempt->exam->title">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    Percobaan ke-{{ $examAttempt->attempt_no }} · {{ count($rows) }} soal
                </div>
                @if ($examAttempt->status === 'in_progress')
                    <div
                        x-data="{ seconds: 0 }"
                        x-init="const finish = Date.parse(@js($examAttempt->expires_at->toIso8601String())); const tick = () => { seconds = Math.max(0, Math.ceil((finish - Date.now()) / 1000)); }; tick(); setInterval(tick, 1000)"
                        class="rounded-md border px-3 py-2 text-sm font-semibold dark:border-gray-700"
                        aria-label="Sisa waktu ujian"
                    >
                        Sisa waktu: <span x-text="Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0')"></span>
                    </div>
                @endif
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Jawaban disimpan otomatis. Batas waktu dan kebenaran pengerjaan ditentukan oleh server.
            </p>
        </x-filament::section>

        @if ($examAttempt->status !== 'in_progress')
            <x-filament::section heading="Ujian selesai">
                @if ($examAttempt->status === 'pending_review')
                    <p class="text-sm">Jawaban sudah diterima. Hasil akhir menunggu koreksi esai oleh petugas.</p>
                @else
                    <p class="text-sm">Skor: {{ number_format((float) $examAttempt->percentage, 2, ',', '.') }}%
                        · {{ $examAttempt->passed ? 'Lulus' : 'Belum lulus' }}</p>
                @endif
                <p class="mt-2 text-xs text-gray-500">Hasil ini hanya berlaku di Tes Online dan tidak otomatis menjadi hasil seleksi SPMB.</p>
                <x-filament::button :href="\App\Filament\Applicant\Pages\OnlineExams::getUrl()" tag="a" color="gray">
                    Kembali ke Daftar Tes Online
                </x-filament::button>
            </x-filament::section>
        @else
            @foreach ($rows as $index => $row)
                <x-filament::section :heading="'Soal '.($index + 1)">
                    <div class="space-y-3">
                        <div class="whitespace-pre-wrap text-sm font-medium text-gray-950 dark:text-white">{{ $row['prompt'] }}</div>
                        <div class="text-xs text-gray-500">Bobot: {{ $row['points'] }}</div>
                        @if (in_array($row['type'], ['single_choice', 'true_false'], true))
                            @foreach ($row['choices'] as $key => $label)
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="radio" class="text-primary-600" name="question_{{ $row['id'] }}"
                                        value="{{ $key }}" wire:model.live="answers.{{ $row['id'] }}">
                                    {{ $label }}
                                </label>
                            @endforeach
                        @elseif ($row['type'] === 'multiple_choice')
                            @foreach ($row['choices'] as $key => $label)
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="checkbox" class="rounded text-primary-600" value="{{ $key }}"
                                        wire:model.live="answers.{{ $row['id'] }}">
                                    {{ $label }}
                                </label>
                            @endforeach
                        @elseif ($row['type'] === 'matching')
                            @foreach ($row['prompts'] as $key => $label)
                                <div class="grid gap-2 md:grid-cols-2 sm:items-center">
                                    <label class="text-sm" for="match_{{ $row['id'] }}_{{ $key }}">{{ $label }}</label>
                                    <select id="match_{{ $row['id'] }}_{{ $key }}"
                                        class="rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"
                                        wire:model.live="answers.{{ $row['id'] }}.{{ $key }}">
                                        <option value="">Pilih pasangan</option>
                                        @foreach ($row['choices'] as $choiceKey => $choiceLabel)
                                            <option value="{{ $choiceKey }}">{{ $choiceLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        @elseif ($row['type'] === 'short_answer')
                            <input type="text" maxlength="500"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900"
                                wire:model.live.debounce.1000ms="answers.{{ $row['id'] }}"
                                placeholder="Masukkan jawaban singkat">
                        @elseif ($row['type'] === 'numerical')
                            <input type="number" step="any"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900"
                                wire:model.live.debounce.1000ms="answers.{{ $row['id'] }}"
                                placeholder="Masukkan angka">
                        @elseif ($row['type'] === 'essay')
                            <textarea rows="7" maxlength="15000"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900"
                                wire:model.live.debounce.1000ms="answers.{{ $row['id'] }}"
                                placeholder="Tulis jawaban uraian"></textarea>
                        @endif
                    </div>
                </x-filament::section>
            @endforeach

            <x-filament::section>
                <p class="mb-3 text-xs text-gray-600 dark:text-gray-300">
                    Pastikan semua jawaban sudah tersimpan sebelum mengumpulkan. Setelah dikumpulkan, jawaban tidak dapat diubah.
                </p>
                <x-filament::button wire:click="submitExam" wire:confirm="Kumpulkan jawaban sekarang? Jawaban tidak bisa diedit setelah dikirim."
                    wire:loading.attr="disabled" color="primary">
                    Kumpulkan Jawaban
                </x-filament::button>
                <span wire:loading class="ml-3 text-xs text-gray-500">Sedang menyimpan...</span>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
