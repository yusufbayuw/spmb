@php
    $configuration = $registration?->configuration;
    $scoreSettings = $configuration?->academic_score_settings ?? [];
    $scores = $registration?->academicScores ?? collect();
    $achievements = $registration?->achievements ?? collect();
@endphp

@if($configuration?->academic_scores_enabled && $scores->isNotEmpty())
    <div class="space-y-3">
        <div class="text-sm font-semibold text-gray-950 dark:text-white">Data Nilai</div>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2 text-left">Kelas</th>
                        <th class="px-3 py-2 text-left">Mata Pelajaran</th>
                        <th class="px-3 py-2 text-left">Komponen</th>
                        <th class="px-3 py-2 text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($scores as $score)
                        @php
                            $grade = collect($scoreSettings['grades'] ?? [])->firstWhere('key', $score->grade_key);
                            $subject = collect($scoreSettings['subjects'] ?? [])->firstWhere('key', $score->subject_key);
                            $assessment = collect($scoreSettings['assessments'] ?? [])->firstWhere('key', $score->assessment_key);
                        @endphp
                        <tr>
                            <td class="px-3 py-2">{{ $grade['label'] ?? $score->grade_key }}</td>
                            <td class="px-3 py-2">{{ $subject['label'] ?? $score->subject_key }}</td>
                            <td class="px-3 py-2">{{ $assessment['label'] ?? $score->assessment_key }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ rtrim(rtrim(number_format((float) $score->score, 2, ',', '.'), '0'), ',') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($configuration?->achievements_enabled && $achievements->isNotEmpty())
    <div class="mt-5 space-y-3">
        <div class="text-sm font-semibold text-gray-950 dark:text-white">Prestasi</div>
        <div class="space-y-3">
            @foreach($achievements as $achievement)
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="font-medium text-gray-950 dark:text-white">{{ $achievement->title }}</div>
                    <div class="mt-1 text-sm text-gray-500">
                        {{ $achievement->level }}
                        @if($achievement->year) · {{ $achievement->year }} @endif
                        @if($achievement->organizer) · {{ $achievement->organizer }} @endif
                    </div>
                    @if($achievement->description)
                        <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $achievement->description }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
