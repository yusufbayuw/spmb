<x-filament-panels::page>
    @php
        $registration = $this->registrationRecord;
        $stages = array_keys($registration->progressStages());
        $stageLabels = $registration->progressStages();
        $operationalStages = array_keys($registration->enabledStages());
        $currentOperationalIndex = array_search($registration->current_stage, $operationalStages, true);
        $reachedVisibleStages = collect($stages)->filter(function (string $stage) use ($operationalStages, $currentOperationalIndex, $registration): bool {
            if ($registration->current_stage === 'completed') {
                return true;
            }

            $stageOperationalIndex = array_search($stage, $operationalStages, true);

            return $stageOperationalIndex !== false
                && $currentOperationalIndex !== false
                && $stageOperationalIndex <= $currentOperationalIndex;
        })->count();
        $progress = count($stages) > 0
            ? (int) round(($reachedVisibleStages / count($stages)) * 100)
            : 0;
        $requiredDocuments = \App\Services\RegistrationWorkflowService::requiredDocuments($registration);
        $isHigherEducation = $registration->unit?->isHigherEducation() ?? false;
        $participantLabel = $isHigherEducation ? 'calon mahasiswa' : 'calon siswa';
        $identityPhoto = app(\App\Services\RegistrationCardService::class)->identityPhoto($registration);
        $portalConfiguration = app(\App\Services\UnitConfigurationService::class)->current((int) $registration->unit_id)
            ?? $registration->configuration;
        $portalBlocks = $portalConfiguration
            ? $portalConfiguration->applicantPortalBlocks()
            : \App\Models\UnitConfiguration::defaultApplicantPortalBlocks();
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
                        @if ($registration->current_stage === 'completed')
                            Proses pendaftaran telah mencapai 100%.
                        @else
                            Proses pendaftaran telah mencapai {{ $progress }}%. Ikuti aksi yang tersedia agar proses dapat berlanjut.
                        @endif
                    </p>
                    @if ($registration->currentStageDescription())
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $registration->currentStageDescription() }}</p>
                    @endif
                </div>

                <x-filament::button tag="a" href="{{ \App\Filament\Applicant\Pages\Dashboard::getUrl() }}" color="gray" outlined icon="heroicon-m-arrow-left">
                    Semua Pendaftaran
                </x-filament::button>
            </div>

            <div class="mt-5 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $progress }}%"></div>
            </div>
        </x-filament::section>

        @if ($registration->current_stage === 'completed')
            <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
                <x-slot name="heading">{{ $registration->completionTitle() }}</x-slot>
                <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $registration->completionMessage() }}</p>
            </x-filament::section>
        @endif

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

<x-filament::section>
                    <x-slot name="heading">Aksi Selanjutnya</x-slot>
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

                        @if ($registration->isOperational() && $registration->current_stage !== 'completed' && filled($registration->registration_number) && ! $identityPhoto)
                            <x-filament::button
                                tag="a"
                                href="{{ \App\Filament\Applicant\Pages\IdentityPhotoUpload::getUrl(['registration' => $registration->uuid]) }}"
                                color="warning"
                                icon="heroicon-m-photo"
                            >
                                Upload Foto Identitas
                            </x-filament::button>
                        @elseif ($registration->isOperational() && $registration->current_stage !== 'completed' && $identityPhoto && ! $identityPhoto->is_verified)
                            <x-filament::button
                                tag="a"
                                href="{{ \App\Filament\Applicant\Pages\IdentityPhotoUpload::getUrl(['registration' => $registration->uuid]) }}"
                                color="gray"
                                outlined
                                icon="heroicon-m-photo"
                            >
                                Lihat / Ganti Foto
                            </x-filament::button>
                        @endif

                        @if ($registration->isOperational() && $registration->applicant_card_number && $identityPhoto)
                            <x-filament::button tag="a" href="{{ route('registration.card', $registration) }}" target="_blank" color="gray" outlined icon="heroicon-m-identification">
                                Kartu Pendaftaran
                            </x-filament::button>
                        @elseif ($registration->isOperational() && $registration->applicant_card_number && ! $identityPhoto)
                            <x-filament::badge color="warning" icon="heroicon-m-photo">
                                Upload foto identitas untuk membuka kartu pendaftaran
                            </x-filament::badge>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Tahapan Pendaftaran</x-slot>
                    <div class="space-y-1">
                        @foreach ($stages as $index => $stage)
                            @php
                                $stageOperationalIndex = array_search($stage, $operationalStages, true);
                                $isDone = $registration->current_stage === 'completed'
                                    || ($stageOperationalIndex !== false
                                        && $currentOperationalIndex !== false
                                        && $stageOperationalIndex < $currentOperationalIndex);
                                $isCurrent = $stage === $registration->current_stage;
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

        @if (collect($portalBlocks)->contains(fn (array $block): bool => (bool) ($block['active'] ?? true)))
            <div class="grid gap-6 xl:grid-cols-2">
                @foreach ($portalBlocks as $portalBlock)
                    @php
                        $portalBlockKey = $portalBlock['key'] ?? null;
                        $portalBlockActive = (bool) ($portalBlock['active'] ?? true);
                        $portalBlockHasContent = match ($portalBlockKey) {
                            'additional_information' => filled($registration->custom_answers),
                            'selection_tests' => $registration->testResults->isNotEmpty(),
                            'announcement' => $registration->announcement?->status === 'published',
                            'post_announcement' => ($registration->current_stage === 'admission_offer' && $registration->admissionOffer)
                                || ($registration->current_stage === 'waiting_list' && $registration->selection)
                                || $registration->current_stage === 're_registration',
                            'summary', 'payment' => true,
                            'required_documents' => count($requiredDocuments) > 0,
                            default => false,
                        };
                        $portalBlockWide = in_array($portalBlockKey, [
                            'additional_information',
                            'selection_tests',
                            'announcement',
                            'post_announcement',
                        ], true);
                    @endphp

                    @continue(! $portalBlockActive || ! $portalBlockHasContent)

                    <div @class(['xl:col-span-2' => $portalBlockWide])>
                        @include('filament.applicant.partials.registration-status-block', ['blockKey' => $portalBlockKey])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
