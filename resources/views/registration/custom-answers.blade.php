@if($registration?->configuration)
    <dl class="grid gap-4 md:grid-cols-2">
        @foreach(app(\App\Services\ConfiguredRegistrationForm::class)->presentationFields($registration->configuration) as $field)
            @if($field['active'] && array_key_exists($field['key'], $registration->custom_answers ?? []))
                @php
$answer = $registration->custom_answers[$field['key']];
@endphp
                <div>
                    <dt class="text-sm text-gray-500">{{ $field['label'] }}</dt>
                    @if(filled($field['help'] ?? null))
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $field['help'] }}</p>
                    @endif
                    <dd class="mt-1">
                        @if(($field['type'] ?? null) === 'file' && is_string($answer) && filled($answer))
                            <a
                                href="{{ route('files.applicant.registration-custom-field', ['registration' => $registration, 'key' => $field['key'], 'download' => 1]) }}"
                                class="text-primary-600 hover:underline"
                            >
                                Unduh dokumen
                            </a>
                        @else
                            {{ is_array($answer) ? implode(', ', $answer) : ($field['type'] === 'boolean' ? ($answer ? 'Ya' : 'Tidak') : $answer) }}
                        @endif
                    </dd>
                </div>
            @endif
        @endforeach
    </dl>
@endif
