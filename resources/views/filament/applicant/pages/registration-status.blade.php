<x-filament-panels::page>
    @php
        $registration = $this->registrationRecord;
        $stageLabels = \App\Models\Registration::STAGES;
        $stages = array_keys($stageLabels);
        $stageIndex = $this->stageIndex();
        $progress = (int) round((($stageIndex + 1) / count($stages)) * 100);
        $requiredDocuments = \App\Services\RegistrationWorkflowService::REQUIRED_DOCUMENTS;
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge color="primary">{{ $registration->stageLabel() }}</x-filament::badge>
                        <x-filament::badge color="gray">
                            {{ $registration->registrant_type === 'parent' ? 'Orang tua / wali' : 'Daftar mandiri' }}
                        </x-filament::badge>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Proses pendaftaran telah mencapai {{ $progress }}%. Ikuti aksi yang tersedia agar proses dapat berlanjut.
                    </p>
                </div>

                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Applicant\Pages\Dashboard::getUrl() }}"
                    color="gray"
                    outlined
                    icon="heroicon-m-arrow-left"
                >
                    Semua Pendaftaran
                </x-filament::button>
            </div>

            <div class="mt-5 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $progress }}%"></div>
            </div>
        </x-filament::section>

        @if ($registration->data_validation_status === 'revision')
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
                <x-slot name="heading">Data perlu diperbaiki</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $registration->data_validation_notes ?: 'Tata Usaha meminta perbaikan data pendaftaran. Silakan perbaiki data lalu kirim kembali.' }}
                </p>
                <div class="mt-4">
                    <x-filament::button
                        tag="a"
                        href="{{ \App\Filament\Applicant\Resources\RegistrationResource::getUrl('edit', ['record' => $registration]) }}"
                        color="warning"
                        icon="heroicon-m-pencil-square"
                    >
                        Perbaiki Data
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-filament::section>
                    <x-slot name="heading">Aksi Berikutnya</x-slot>
                    <x-slot name="description">Aksi yang tersedia menyesuaikan tahap pendaftaran saat ini.</x-slot>

                    <div class="flex flex-wrap gap-3">
                        @if ($registration->current_stage === 'payment')
                            <x-filament::button
                                tag="a"
                                href="{{ \App\Filament\Applicant\Pages\PaymentUpload::getUrl(['registration' => $registration->id]) }}"
                                icon="heroicon-m-banknotes"
                            >
                                Upload Bukti Pembayaran
                            </x-filament::button>
                        @elseif ($registration->current_stage === 'payment_verification')
                            <x-filament::badge color="warning" icon="heroicon-m-clock">Menunggu verifikasi pembayaran TU</x-filament::badge>
                        @elseif (in_array($registration->current_stage, ['documents', 'document_verification'], true))
                            <x-filament::button
                                tag="a"
                                href="{{ \App\Filament\Applicant\Pages\DocumentsUpload::getUrl(['registration' => $registration->id]) }}"
                                icon="heroicon-m-document-arrow-up"
                            >
                                {{ $registration->current_stage === 'documents' ? 'Lengkapi Dokumen' : 'Lihat Dokumen' }}
                            </x-filament::button>
                        @elseif ($registration->current_stage === 'data_validation')
                            <x-filament::badge color="warning" icon="heroicon-m-clock">Menunggu validasi data oleh TU</x-filament::badge>
                        @elseif ($registration->current_stage === 'virtual_account')
                            <x-filament::badge color="warning" icon="heroicon-m-envelope">Menunggu Virtual Account</x-filament::badge>
                        @elseif ($registration->current_stage === 'applicant_card')
                            <x-filament::badge color="warning" icon="heroicon-m-identification">Menunggu penerbitan kartu pendaftar</x-filament::badge>
                        @elseif ($registration->current_stage === 'tests')
                            <x-filament::badge color="info" icon="heroicon-m-academic-cap">Ikuti rangkaian tes sesuai jadwal</x-filament::badge>
                        @elseif ($registration->current_stage === 'selection')
                            <x-filament::badge color="warning" icon="heroicon-m-clock">Menunggu keputusan seleksi</x-filament::badge>
                        @elseif ($registration->current_stage === 'announcement')
                            <x-filament::badge color="warning" icon="heroicon-m-megaphone">Menunggu pengumuman dipublikasikan</x-filament::badge>
                        @else
                            <x-filament::badge color="success" icon="heroicon-m-check-circle">Proses pendaftaran selesai</x-filament::badge>
                        @endif

                        @if ($registration->applicant_card_number)
                            <x-filament::button
                                tag="a"
                                href="{{ route('registration.card', $registration) }}"
                                target="_blank"
                                color="gray"
                                outlined
                                icon="heroicon-m-printer"
                            >
                                Cetak Kartu Pendaftar
                            </x-filament::button>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Tahapan SPMB</x-slot>
                    <div class="space-y-1">
                        @foreach ($stages as $index => $stage)
                            @php
                                $isDone = $index < $stageIndex || $registration->current_stage === 'completed';
                                $isCurrent = $index === $stageIndex && $registration->current_stage !== 'completed';
                            @endphp
                            <div class="flex items-start gap-3 rounded-xl px-3 py-3 {{ $isCurrent ? 'bg-primary-50 dark:bg-primary-500/10' : '' }}">
                                <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $isDone ? 'bg-success-500 text-white' : ($isCurrent ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-500 dark:bg-white/10') }}">
                                    @if ($isDone)
                                        <x-heroicon-m-check class="h-4 w-4" />
                                    @else
                                        <span class="text-xs font-semibold">{{ $index + 1 }}</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="text-sm font-semibold {{ $isCurrent ? 'text-primary-700 dark:text-primary-300' : 'text-gray-950 dark:text-white' }}">
                                        {{ $stageLabels[$stage] ?? str($stage)->replace('_', ' ')->title() }}
                                    </div>
                                    @if ($isCurrent)
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tahap aktif saat ini</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>

                @if ($registration->testResults->isNotEmpty())
                    <x-filament::section>
                        <x-slot name="heading">Tes Seleksi</x-slot>
                        <div class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($registration->testResults->sortBy(fn ($result) => $result->admissionTest?->sort_order ?? 999) as $result)
                                <div class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $result->admissionTest?->name ?? 'Tes' }}</div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $result->admissionTest?->scheduled_at?->format('d M Y H:i') ?? 'Jadwal akan diinformasikan' }}
                                            @if ($result->admissionTest?->location)
                                                · {{ $result->admissionTest->location }}
                                            @endif
                                        </div>
                                    </div>
                                    <x-filament::badge :color="in_array($result->status, ['completed', 'passed'], true) ? 'success' : ($result->status === 'failed' ? 'danger' : 'gray')">
                                        {{ str($result->status)->replace('_', ' ')->title() }}
                                    </x-filament::badge>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                @if ($registration->announcement?->status === 'published')
                    <x-filament::section icon="heroicon-o-megaphone" icon-color="success">
                        <x-slot name="heading">{{ $registration->announcement->title ?: 'Pengumuman SPMB' }}</x-slot>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $registration->announcement->message }}</p>
                        @if ($registration->selection)
                            <div class="mt-4">
                                <x-filament::badge :color="$registration->selection->decision === 'accepted' ? 'success' : ($registration->selection->decision === 'waiting_list' ? 'warning' : 'danger')" size="lg">
                                    {{ match($registration->selection->decision) { 'accepted' => 'DITERIMA', 'waiting_list' => 'DAFTAR TUNGGU', 'rejected' => 'BELUM DITERIMA', default => strtoupper($registration->selection->decision ?? '-') } }}
                                </x-filament::badge>
                            </div>
                        @endif
                    </x-filament::section>
                @endif
            </div>

            <div class="space-y-6">
                <x-filament::section>
                    <x-slot name="heading">Ringkasan</x-slot>
                    <dl class="space-y-4 text-sm">
                        <div><dt class="text-gray-500">Nomor pendaftaran</dt><dd class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $registration->registration_number }}</dd></div>
                        <div><dt class="text-gray-500">Unit tujuan</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $registration->unit?->name ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">NIK calon siswa</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $registration->nik }}</dd></div>
                        <div><dt class="text-gray-500">Tanggal daftar</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $registration->submitted_at?->format('d M Y H:i') ?? $registration->created_at->format('d M Y H:i') }}</dd></div>
                    </dl>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Pembayaran</x-slot>
                    @if ($registration->latestPayment)
                        <dl class="space-y-3 text-sm">
                            <div><dt class="text-gray-500">Virtual Account</dt><dd class="mt-1 font-mono font-semibold text-gray-950 dark:text-white">{{ $registration->latestPayment->va_number ?? 'Belum tersedia' }}</dd></div>
                            <div><dt class="text-gray-500">Nominal</dt><dd class="mt-1 font-semibold text-gray-950 dark:text-white">Rp {{ number_format((float) $registration->latestPayment->amount, 0, ',', '.') }}</dd></div>
                            <div><dt class="text-gray-500">Status</dt><dd class="mt-1"><x-filament::badge>{{ str($registration->latestPayment->status)->replace('_', ' ')->title() }}</x-filament::badge></dd></div>
                        </dl>
                    @else
                        <p class="text-sm text-gray-500">Belum ada tagihan pembayaran.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Dokumen Wajib</x-slot>
                    <div class="space-y-3">
                        @foreach ($requiredDocuments as $type)
                            @php
                                $document = $registration->documents->firstWhere('type', $type);
                                $label = match($type) {
                                    'report_card' => 'Rapor',
                                    'family_card' => 'Kartu Keluarga',
                                    'birth_certificate' => 'Akta Kelahiran',
                                    'photo' => 'Pas Foto',
                                    default => str($type)->replace('_', ' ')->title(),
                                };
                            @endphp
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                @if ($document?->is_verified)
                                    <x-filament::badge color="success">Terverifikasi</x-filament::badge>
                                @elseif ($document)
                                    <x-filament::badge color="warning">Diperiksa</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">Belum upload</x-filament::badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
