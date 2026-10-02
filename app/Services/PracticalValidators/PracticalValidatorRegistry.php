<?php

namespace App\Services\PracticalValidators;

use InvalidArgumentException;

class PracticalValidatorRegistry
{
    public const OPTIONS = [
        StateEqualsValidator::class => 'State sama dengan nilai yang diharapkan',
        StateUnchangedValidator::class => 'State tidak berubah dari kondisi awal',
        EventExistsValidator::class => 'Event wajib terjadi',
        EventNotExistsValidator::class => 'Event tidak boleh terjadi',
    ];

    public function resolve(string $class): PracticalValidator
    {
        if (! array_key_exists($class, self::OPTIONS)) {
            throw new InvalidArgumentException('Validator practical tidak diizinkan: '.$class);
        }

        $validator = app($class);

        if (! $validator instanceof PracticalValidator) {
            throw new InvalidArgumentException('Validator practical tidak valid: '.$class);
        }

        return $validator;
    }
}
