<x-filament-panels::page>
    @foreach($this->registrationRecord->documentRequirements() as $requirement)
        <x-filament::section :heading="$requirement['label']">
            <p class="text-sm">{{ $requirement['required'] ? 'Wajib' : 'Opsional' }} · Maksimum {{ $requirement['max_files'] }} lampiran</p>
            <p class="text-sm">{{ $requirement['instructions'] ?? '' }}</p>
            @if(!empty($requirement['template_path']))<x-filament::button tag="a" :href="route('registration.template', [$registrationRecord, $requirement['key']])" color="gray" class="mt-3">Unduh Template</x-filament::button>@endif
            <div class="mt-3 grid gap-3 md:grid-cols-2">
            @forelse($registrationRecord->documents->filter(fn ($document) => ($document->requirement_key ?: $document->type) === $requirement['key']) as $document)
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p>{{ $document->original_name }}</p>
                    @if($document->is_verified)<x-filament::badge color="success">Terverifikasi</x-filament::badge>
                    @elseif($document->rejection_reason)<x-filament::badge color="danger">Ditolak</x-filament::badge><p class="mt-2 text-sm">Alasan penolakan: {{ $document->rejection_reason }}</p>
                    @else<x-filament::badge color="warning">Menunggu verifikasi</x-filament::badge>@endif
                    <a href="{{ route('files.applicant.documents.show', $document) }}" class="mt-3 inline-block text-primary-600" @if($document->file_type !== 'docx') data-file-preview data-file-name="{{ $document->original_name }}" @endif>Lihat file</a>
                </div>
            @empty<p class="text-sm text-gray-500">Belum diunggah</p>@endforelse
            </div>
        </x-filament::section>
    @endforeach
    <form wire:submit="submit" class="space-y-6">{{ $this->form }}<div class="flex gap-3"><x-filament::button type="submit">Simpan Dokumen</x-filament::button><x-filament::button tag="a" :href="\App\Filament\Applicant\Pages\RegistrationStatus::getUrl(['registration' => $registrationRecord->uuid])" color="gray">Kembali</x-filament::button></div></form>
</x-filament-panels::page>
