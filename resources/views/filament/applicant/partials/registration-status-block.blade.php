@switch($blockKey)
    @case('additional_information')
        @if ($registration->custom_answers)
            <x-filament::section heading="Informasi Tambahan">
                @include('registration.custom-answers', ['registration' => $registration])
            </x-filament::section>
        @endif
        @break

    @case('selection_tests')
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
        @break

    @case('announcement')
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
        @break

    @case('post_announcement')
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
        @break

    @case('summary')
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
        @break

    @case('payment')
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
        @break

    @case('required_documents')
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
        @break
@endswitch
