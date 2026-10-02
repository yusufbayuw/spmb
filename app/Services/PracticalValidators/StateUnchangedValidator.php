<?php

namespace App\Services\PracticalValidators;

use App\Models\PracticalAssertion;
use App\Models\PracticalRun;
use App\Models\PracticalRunAssertion;

class StateUnchangedValidator implements PracticalValidator
{
    public function validate(
        PracticalRun $run,
        PracticalAssertion|PracticalRunAssertion $assertion,
    ): PracticalValidationResult {
        $config = $assertion->config;
        $record = $run->sandboxRecords()
            ->where('entity_type', $config['entity_type'] ?? '')
            ->where('entity_key', $config['entity_key'] ?? '')
            ->first();

        if (! $record) {
            return new PracticalValidationResult(
                false,
                [],
                [],
                'Record sandbox yang diperlukan tidak ditemukan.',
            );
        }

        $path = (string) ($config['path'] ?? '');
        $expected = $path === ''
            ? $record->original_state
            : data_get($record->original_state, $path);
        $actual = $path === ''
            ? $record->state
            : data_get($record->state, $path);
        $passed = PracticalValue::equivalent($expected, $actual);

        return new PracticalValidationResult(
            $passed,
            ['value' => $expected],
            ['value' => $actual],
            $passed ? null : 'Data yang seharusnya dipertahankan telah berubah.',
        );
    }
}
