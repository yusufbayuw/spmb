<?php

namespace App\Services\PracticalValidators;

class PracticalValidationResult
{
    public function __construct(
        public bool $passed,
        public array $expected = [],
        public array $actual = [],
        public ?string $feedback = null,
    ) {}
}
