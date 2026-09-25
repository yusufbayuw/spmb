@if($registration?->configuration)
    <dl class="grid gap-4 md:grid-cols-2">
        @foreach(app(\App\Services\ConfiguredRegistrationForm::class)->presentationFields($registration->configuration) as $field)
            @if($field['active'] && array_key_exists($field['key'], $registration->custom_answers ?? []))
                @php
$answer = $registration->custom_answers[$field['key']];
$detailSettings = ($field['type'] ?? null) === 'boolean'
    ? app(\App\Services\ConfiguredRegistrationForm::class)->booleanDetailSettings($field, $answer)
    : null;
$detailAnswer = data_get($registration->custom_answers, '_details.'.$field['key']);
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
                    @if($detailSettings && filled($detailAnswer))
                        <div class="mt-3 border-l-2 border-gray-200 pl-3 dark:border-gray-700">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $detailSettings['label'] }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-gray-950 dark:text-white">{{ $detailAnswer }}</dd>
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    </dl>
@endif
