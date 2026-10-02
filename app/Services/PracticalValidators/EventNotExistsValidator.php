<?php

namespace App\Services\PracticalValidators;

use App\Models\PracticalAssertion;
use App\Models\PracticalRun;
use App\Models\PracticalRunAssertion;

class EventNotExistsValidator implements PracticalValidator
{
    public function validate(
        PracticalRun $run,
        PracticalAssertion|PracticalRunAssertion $assertion,
    ): PracticalValidationResult {
        $config = $assertion->config;
        $query = $run->events()->where('action_code', $config['action_code'] ?? '');

        if (filled($config['target_type'] ?? null)) {
            $query->where('target_type', $config['target_type']);
        }

        if (filled($config['target_key'] ?? null)) {
            $query->where('target_key', $config['target_key']);
        }

        $exists = $query->exists();

        return new PracticalValidationResult(
            ! $exists,
            ['event_exists' => false, 'action_code' => $config['action_code'] ?? null],
            ['event_exists' => $exists],
            $exists ? 'Tindakan terlarang dilakukan pada sandbox.' : null,
        );
    }
}
