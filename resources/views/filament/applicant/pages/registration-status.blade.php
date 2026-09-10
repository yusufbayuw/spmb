<x-filament-panels::page>
    @php
        $registration = $this->registrationRecord;
        $stages = array_keys($registration->enabledStages());
        $stageLabels = $registration->enabledStages();
        $stageIndex = $this->stageIndex();
        $progress = (int) round((($stageIndex + 1) / count($stages)) * 100);
        $requiredDocuments = \App\Services\RegistrationWorkflowService::requiredDocuments($registration);
        $isHigherEducation = $registration->unit?->isHigherEducation() ?? false;
        $participantLabel = $isHigherEducation ? 'calon mahasiswa' : 'calon siswa';
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge color="primary">{{ $registration->stageLabel() }}</x-filament::badge>
                        <x-filament::badge color="gray">
                            {{ $registration->registrant_type === 'parent' ? 'Orang tua / wali' : ($isHigherEducation ? 'Calon mahasiswa' : 'Daftar mandiri') }}
                        </x-filament::badge>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Proses pendaftaran telah mencapai {{ $progress }}%. Ikuti aksi yang tersedia agar proses dapat berlanjut.
                    </p>
                </div>

                <x-filament::button tag="a" href="{{ \App\Filament\Applicant\Pages\Dashboard::getUrl() }}" color="gray" outlined icon="heroicon-m-arrow-left">
                    Semua Pendaftaran
                </x-filament::button>
            </div>

            <div class="mt-5 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $progress }}%"></div>
            </div>
        </x-filament::section>

        @if (! $registration->isOperational())
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
                <x-slot name="heading">Pendaftaran {{ $registration->lifecycleLabel() }}</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $registration->lifecycle_reason }}</p>
            </x-filament::section>
        @endif

        @if ($registration->isOperational() && $registration->data_validation_status === 'revision')
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
                <x-slot name="heading">Data perlu diperbaiki</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $registration->data_validation_notes ?: 'Petugas meminta perbaikan data pendaftaran. Silakan gunakan menu Perbaiki Data pada Pendaftaran Saya.' }}
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

        @if($registration->custom_answers)
            <x-filament::section heading="Informasi Tambahan">@include('registration.custom-answers', ['registration' => $registration])</x-filament::section>
        @endif

        @if($registration->academicScores->isNotEmpty() || $registration->achievements->isNotEmpty())
            <x-filament::section heading="Nilai & Prestasi">
                @include('registration.academic-profile', ['registration' => $registration])
            </x-filament::section>
        @endif
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-filament::section>
                    <x-slot name="heading">Aksi Berikutnya</x-slot>
                    <x-slot name="description">Aksi yang tersedia menyesuaikan tahap pendaftaran saat ini.</x-slot>

                    <div class="flex flex-wrap gap-3">
                        @if (! $registration->isOperational())
                            <x-filament::badge color="warning">Pendaftaran tidak aktif. Hubungi petugas untuk tindak lanjut.</x-filament::badge>
                        @elseif ($registration->current_stage === 'payment')
                            <x-filament::button tag="a" href="{{ \App\Filament\Applicant\Pages\PaymentUpload::getUrl(['registration' => $registration->uuid]) }}" icon="heroicon-m-banknotes">
                                Upload Bukti Pembayaran
                            </x-filament::button>
                        @elseif ($registration->current_stage === 'payment_verification')
                            <x-filament::badge color="warning" icon="heroicon-m-clock">Menunggu verifikasi pembayaran petugas</x-filament::badge>
                        @elseif (in_array($registration->current_stage, ['documents', 'document_verification'], true))
                            <x-filament::button tag="a" href="{{ \App\Filament\Applicant\Pages\DocumentsUpload::getUrl(['registration' => $registration->uuid]) }}" icon="heroicon-m-document-arrow-up">
                                {{ $registration->current_stage === 'documents' ? 'Lengkapi Dokumen' : 'Lihat Dokumen' }}
                            </x-filament::button>
                        @elseif ($registration->current_stage === 'data_validation')
                            <x-filament::badge color="warning" icon="heroicon-m-clock">Menunggu validasi data oleh petugas</x-filament::badge>
                        @elseif ($registration->current_stage === 'virtual_account')
                            <x-filament::badge color="warning" icon="heroicon-m-envelope">Menunggu Virtual Account dari petugas</x-filament::badge>
                        @elseif ($registration->current_stage === 'applicant_card')
                            <x-filament::badge color="warning" icon="heroicon-m-identification">Menunggu penerbitan kartu pendaftar</x-filament::badge>
                        @elseif ($registration->current_stage === 'tests')
                            <x-filament::button tag="a" :href="\App\Filament\Applicant\Pages\TestSchedule::getUrl(['registration' => $registration->uuid])">Pilih / Ubah Jadwal Tes</x-filament::button>
                            <x-filament::badge color="info" icon="heroicon-m-academic-cap">Ikuti rangkaian tes sesuai jadwal</x-filament::badge>
                        @elseif ($registration->current_stage === 'selection')
                            <x-filament::badge color="warning" icon="heroicon-m-clock">Menunggu keputusan seleksi</x-filament::badge>
                        @elseif ($registration->current_stage === 'announcement')
                            <x-filament::badge color="warning" icon="heroicon-m-megaphone">Menunggu pengumuman dipublikasikan</x-filament::badge>
                        @elseif ($registration->current_stage === 'admission_offer')
                            <x-filament::badge color="success" icon="heroicon-m-academic-cap">Konfirmasikan kursi penerimaan</x-filament::badge>
                        @elseif ($registration->current_stage === 'waiting_list')
                            <x-filament::badge color="warning" icon="heroicon-m-clock">Anda berada dalam daftar tunggu</x-filament::badge>
                        @elseif ($registration->current_stage === 're_registration')
                            <x-filament::button tag="a" :href="\App\Filament\Applicant\Pages\ReRegistration::getUrl(['registration' => $registration->uuid])" icon="heroicon-m-document-check">
                                Lanjutkan Daftar Ulang
                            </x-filament::button>
                        @elseif ($registration->current_stage === 'enrollment')
                            <x-filament::badge color="info" icon="heroicon-m-user-plus">Menunggu proses enrollment petugas</x-filament::badge>
                        @else
                            <x-filament::badge color="success" icon="heroicon-m-check-circle">Proses pendaftaran selesai</x-filament::badge>
                        @endif

                        @if ($registration->isOperational() && $registration->applicant_card_number)
                            <x-filament::button tag="a" href="{{ route('registration.card', $registration) }}" target="_blank" color="gray" outlined icon="heroicon-m-printer">
                                Cetak Kartu Pendaftar
                            </x-filament::button>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Tahapan Pendaftaran</x-slot>
                    <div class="space-y-1">
                        @foreach ($stages as $index => $stage)
                            @php
                                $isDone = $index < $stageIndex || $registration->current_stage === 'completed';
                                $isCurrent = $index === $stageIndex && $registration->current_stage !== 'completed';
                                $stageLabel = $stage === 'selection'
                                    ? ($isHigherEducation ? 'Seleksi Calon Mahasiswa' : 'Seleksi Calon Siswa')
                                    : ($stageLabels[$stage] ?? str($stage)->replace('_', ' ')->title());
                            @endphp
                            <div class="flex items-start gap-3 rounded-xl px-3 py-3 {{ $isCurrent ? 'bg-primary-50 dark:bg-primary-500/10' : '' }}">
                                <div
                                    class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $isCurrent ? 'bg-primary-600 text-white' : ($isDone ? 'text-white' : 'bg-gray-100 text-gray-500 dark:bg-white/10') }}"
                                    @if ($isDone)
                                        style="background-color: rgb(var(--success-600)); color: rgb(255 255 255);"
                                    @endif
                                >
                                    @if ($isDone)
                                        <x-heroicon-m-check class="h-4 w-4" />
                                    @else
                                        <span class="text-xs font-semibold">{{ $index + 1 }}</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="text-sm font-semibold {{ $isCurrent ? 'text-primary-700 dark:text-primary-300' : 'text-gray-950 dark:text-white' }}">
                                        {{ $stageLabel }}
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
                                        <div class="font-medium text-gray-950 dark:text-white">{{ collect($registration->configuredTests())->firstWhere('id', $result->admission_test_id)['name'] ?? $result->admissionTest?->name ?? 'Tes' }}</div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            @if ($result->admissionTest?->studyProgram)
                                                {{ $result->admissionTest->studyProgram->label() }} ·
                                            @else
                                                Tes umum ·
                                            @endif
                                            @php
$bookedSession = $registration->testBookings->firstWhere('admission_test_id', $result->admission_test_id)?->session;
@endphp
                                            @if($bookedSession)
                                                {{ $bookedSession->label() }}
                                            @elseif($registration->configuration && ! $registration->configuration->legacy)
                                                Jadwal belum dipilih
                                            @elseif ($result->admissionTest?->scheduled_at)
                                                {{ $result->admissionTest->scheduled_at->format('d/m/Y H:i') }}
                                            @else
                                                Jadwal akan diinformasikan
                                            @endif
                                            @if (! $bookedSession && (! $registration->configuration || $registration->configuration->legacy) && $result->admissionTest?->location)
                                                · {{ $result->admissionTest->location }}
                                            @endif
                                        </div>
                                    </div>
                                    <x-filament::badge :color="in_array($result->status, ['completed', 'passed'], true) ? 'success' : ($result->status === 'failed' ? 'danger' : 'gray')">
                                        {{ match($result->status) {
                                            'unbooked' => 'Belum Terjadwal',
                                            'scheduled' => 'Terjadwal',
                                            'completed' => 'Selesai',
                                            'absent' => 'Tidak Hadir',
                                            'exempted' => 'Dibebaskan',
                                            default => str($result->status)->replace('_', ' ')->title(),
                                        } }}
                                    </x-filament::badge>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                @if ($registration->announcement?->status === 'published')
                    <x-filament::section icon="heroicon-o-megaphone" icon-color="success">
                        <x-slot name="heading">{{ $registration->announcement->title ?: 'Pengumuman Hasil Penerimaan' }}</x-slot>
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

                @if ($registration->current_stage === 'admission_offer' && $registration->admissionOffer)
                    <x-filament::section icon="heroicon-o-academic-cap" icon-color="success">
                        <x-slot name="heading">Selamat, Anda Diterima</x-slot>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Konfirmasikan penerimaan sebelum {{ $registration->admissionOffer->expires_at->format('d/m/Y H:i') }}.
                        </p>
                        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                            <form method="POST" action="{{ route('admission-offers.accept', $registration->admissionOffer) }}">
                                @csrf
                                <x-filament::button type="submit" color="success" icon="heroicon-m-check-circle">
                                    Terima Kursi
                                </x-filament::button>
                            </form>
                            <form method="POST" action="{{ route('admission-offers.decline', $registration->admissionOffer) }}" class="flex flex-1 gap-2">
                                @csrf
                                <x-filament::input.wrapper class="flex-1">
                                    <x-filament::input name="reason" required placeholder="Alasan menolak penawaran" />
                                </x-filament::input.wrapper>
                                <x-filament::button type="submit" color="danger" outlined>
                                    Tolak
                                </x-filament::button>
                            </form>
                        </div>
                    </x-filament::section>
                @elseif ($registration->current_stage === 'waiting_list' && $registration->selection)
                    <x-filament::section icon="heroicon-o-clock" icon-color="warning">
                        <x-slot name="heading">Status: Daftar Tunggu</x-slot>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            @if ($registration->selection->waitlist_rank)
                                Posisi daftar tunggu: {{ $registration->selection->waitlist_rank }}.
                            @endif
                            Pantau portal ini untuk perubahan status.
                        </p>
                    </x-filament::section>
                @elseif ($registration->current_stage === 're_registration')
                    <x-filament::section icon="heroicon-o-document-check" icon-color="info">
                        <x-slot name="heading">Tahap berikutnya: Daftar Ulang</x-slot>
                        @php
                            $requiredItems = $registration->reRegistrationItems->where('is_required', true);
                            $verifiedItems = $requiredItems->where('status', 'verified')->count();
                        @endphp
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $verifiedItems }} dari {{ $requiredItems->count() }} persyaratan wajib telah diverifikasi.</p>
                        <div class="mt-4">
                            <x-filament::button tag="a" :href="\App\Filament\Applicant\Pages\ReRegistration::getUrl(['registration' => $registration->uuid])">
                                Lanjutkan Daftar Ulang
                            </x-filament::button>
                        </div>
                    </x-filament::section>
                @endif
            </div>

            <div class="space-y-6">
                <x-filament::section>
                    <x-slot name="heading">Ringkasan</x-slot>
                    <dl class="space-y-4 text-sm">
                        <div><dt class="text-gray-500">Nomor pendaftaran</dt><dd class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $registration->registration_number }}</dd></div>
                        <div><dt class="text-gray-500">Unit / institusi tujuan</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $registration->unit?->name ?? '-' }}</dd></div>
                        @if ($registration->opening?->studyProgram)
                            <div><dt class="text-gray-500">Program studi</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $registration->opening->studyProgram->label() }}</dd></div>
                        @endif
                        <div><dt class="text-gray-500">NIK {{ $participantLabel }}</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $registration->nik }}</dd></div>
                        @if($registration->province || $registration->city || $registration->district || $registration->village)
                            <div>
                                <dt class="text-gray-500">Wilayah domisili</dt>
                                <dd class="mt-1 font-medium text-gray-950 dark:text-white">
                                    {{ collect([$registration->village, $registration->district, $registration->city, $registration->province])->filter()->implode(', ') }}
                                </dd>
                            </div>
                        @endif
                        <div><dt class="text-gray-500">Tanggal daftar</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $registration->submitted_at?->format('d/m/Y H:i') ?? $registration->created_at->format('d/m/Y H:i') }}</dd></div>
                    </dl>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Pembayaran</x-slot>
                    @foreach($registration->receipts as $receipt)
                        <x-filament::button tag="a" :href="route('registration.receipt', [$registration, $receipt])" target="_blank">Cetak Kuitansi</x-filament::button>
                    @endforeach
                    @if ($registration->latestPayment)
                        <dl class="space-y-3 text-sm">
                            @if ($registration->latestPayment->virtualAccount?->bank)
                                <div><dt class="text-gray-500">Bank</dt><dd class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $registration->latestPayment->virtualAccount->bank }}</dd></div>
                            @endif
                            <div><dt class="text-gray-500">Virtual Account</dt><dd class="mt-1 font-mono font-semibold text-gray-950 dark:text-white">{{ $registration->latestPayment->va_number ?? 'Belum tersedia' }}</dd></div>
                            @if (! is_null($registration->latestPayment->amount))
                                <div><dt class="text-gray-500">Nominal</dt><dd class="mt-1 font-semibold text-gray-950 dark:text-white">Rp {{ number_format((float) $registration->latestPayment->amount, 0, ',', '.') }}</dd></div>
                            @endif
                            <div><dt class="text-gray-500">Status</dt><dd class="mt-1"><x-filament::badge>{{ str($registration->latestPayment->status)->replace('_', ' ')->title() }}</x-filament::badge></dd></div>
                        </dl>
                    @else
                        <p class="text-sm text-gray-500">Belum ada Virtual Account yang diberikan.</p>
                    @endif
                </x-filament::section>

                @if(count($requiredDocuments))
                <x-filament::section>
                    <x-slot name="heading">Dokumen Wajib</x-slot>
                    <div class="space-y-3">
                        @foreach ($requiredDocuments as $type)
                            @php
                                $requirement = collect($registration->documentRequirements())->firstWhere('key', $type);
                                $files = $registration->documents->filter(fn ($file) => ($file->requirement_key ?: $file->type) === $type);
                                $document = $files->first();
                                $label = $requirement['label'];
                            @endphp
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                @if ($files->isNotEmpty() && $files->every(fn ($file) => $file->is_verified))
                                    <x-filament::badge color="success">Terverifikasi</x-filament::badge>
                                @elseif ($files->contains(fn ($file) => filled($file->rejection_reason)))
                                    <x-filament::badge color="danger">Ditolak</x-filament::badge>
                                @elseif ($document)
                                    <x-filament::badge color="warning">Diperiksa</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">Belum upload</x-filament::badge>
                                @endif
                            </div>
                            @foreach($files->whereNotNull('rejection_reason') as $rejectedFile)
                                <p class="text-sm text-gray-600 dark:text-gray-300">Alasan penolakan: {{ $rejectedFile->rejection_reason }}</p>
                            @endforeach
                        @endforeach
                    </div>
                </x-filament::section>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
