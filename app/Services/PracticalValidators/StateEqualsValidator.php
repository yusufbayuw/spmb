<?php

namespace App\Services\PracticalValidators;

use App\Models\PracticalAssertion;
use App\Models\PracticalRun;
use App\Models\PracticalRunAssertion;

class StateEqualsValidator implements PracticalValidator
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
                ['record' => [$config['entity_type'] ?? null, $config['entity_key'] ?? null]],
                ['record' => null],
                'Record sandbox yang diperlukan tidak ditemukan.',
            );
        }

        $path = (string) ($config['path'] ?? '');
        $actual = $path === '' ? $record->state : data_get($record->state, $path);
        $expected = $config['expected'] ?? null;
        $passed = PracticalValue::equivalent($expected, $actual);

        return new PracticalValidationResult(
            $passed,
            ['value' => $expected],
            ['value' => $actual],
            $passed ? null : 'State akhir belum sesuai kondisi yang diharapkan.',
        );
    }
}
