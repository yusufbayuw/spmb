<?php

namespace App\Services\PracticalValidators;

use App\Models\PracticalAssertion;
use App\Models\PracticalRun;

class StateUnchangedValidator implements PracticalValidator
{
    public function validate(PracticalRun $run, PracticalAssertion $assertion): PracticalValidationResult
    {
        $config = $assertion->config;
        $record = $run->sandboxRecords()
            ->where('entity_type', $config['entity_type'] ?? '')
            ->where('entity_key', $config['entity_key'] ?? '')
            ->first();

        if (! $record) {
            return new PracticalValidationResult(false, [], [], 'Record sandbox yang diperlukan tidak ditemukan.');
        }

        $path = (string) ($config['path'] ?? '');
        $expected = $path === '' ? $record->original_state : data_get($record->original_state, $path);
        $actual = $path === '' ? $record->state : data_get($record->state, $path);

        return new PracticalValidationResult(
            PracticalValue::equivalent($expected, $actual),
            ['value' => $expected],
            ['value' => $actual],
            PracticalValue::equivalent($expected, $actual) ? null : 'Data yang seharusnya dipertahankan telah berubah.',
        );
    }
}
