@if($registration?->configuration)
    <dl class="grid gap-4 md:grid-cols-2">
        @foreach($registration->configuration->fields as $field)
            @if($field['active'] && array_key_exists($field['key'], $registration->custom_answers ?? []))
                @php
$answer = $registration->custom_answers[$field['key']];
@endphp
                <div><dt class="text-sm text-gray-500">{{ $field['label'] }}</dt><dd>{{ is_array($answer) ? implode(', ', $answer) : ($field['type'] === 'boolean' ? ($answer ? 'Ya' : 'Tidak') : $answer) }}</dd></div>
            @endif
        @endforeach
    </dl>
@endif
