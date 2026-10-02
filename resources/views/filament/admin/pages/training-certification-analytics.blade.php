<x-filament-panels::page>
    @php($data = $this->data())
    @php($overview = $data['overview'])

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach ([
                'Staff Aktif' => $overview['staff'],
                'Enrollment' => $overview['enrollments'],
                'Training Selesai' => $overview['training_completed'],
                'Sertifikat Aktif' => $overview['active_certifications'],
                'Expired ≤30 Hari' => $overview['expiring_30_days'],
                'Dicabut' => $overview['revoked'],
            ] as $label => $value)
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <div class="text-xs text-gray-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <x-filament::section>
            <x-slot name="heading">Per Program</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="p-2">Program</th><th class="p-2">Enrollment</th><th class="p-2">Selesai</th>
                            <th class="p-2">Completion</th><th class="p-2">Sertifikat Aktif</th><th class="p-2">Theory Pass</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['programs'] as $row)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="p-2">{{ $row['code'] }} v{{ $row['version'] }} · {{ $row['program'] }}</td>
                                <td class="p-2">{{ $row['enrolled'] }}</td>
                                <td class="p-2">{{ $row['completed'] }}</td>
                                <td class="p-2">{{ $row['completion_rate'] }}%</td>
                                <td class="p-2">{{ $row['active_certifications'] }}</td>
                                <td class="p-2">{{ $row['theory_pass_rate'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Coverage Sertifikasi per Unit</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr><th class="p-2">Unit</th><th class="p-2">Role</th><th class="p-2">Wajib</th><th class="p-2">Staff</th><th class="p-2">Certified</th><th class="p-2">Coverage</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($data['coverage'] as $row)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="p-2">{{ $row['unit'] }}</td><td class="p-2">{{ $row['role'] }}</td>
                                <td class="p-2">{{ $row['required'] }}</td><td class="p-2">{{ $row['staff'] }}</td>
                                <td class="p-2">{{ $row['certified'] }}</td><td class="p-2">{{ $row['coverage'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-4 text-center text-gray-500">Belum ada staff unit pada scope ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Modul dengan Fail Rate Tertinggi</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr><th class="p-2">Program</th><th class="p-2">Modul</th><th class="p-2">Attempt</th><th class="p-2">Rata-rata</th><th class="p-2">Fail Rate</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($data['weak_modules'] as $row)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="p-2">{{ $row['program'] }}</td><td class="p-2">{{ $row['module'] }}</td>
                                <td class="p-2">{{ $row['attempts'] }}</td><td class="p-2">{{ $row['average_score'] }}</td>
                                <td class="p-2">{{ $row['fail_rate'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-4 text-center text-gray-500">Belum ada data checkpoint.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
