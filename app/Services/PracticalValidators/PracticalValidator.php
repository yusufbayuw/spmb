<?php

namespace App\Services\PracticalValidators;

use App\Models\PracticalAssertion;
use App\Models\PracticalRun;

interface PracticalValidator
{
    public function validate(PracticalRun $run, PracticalAssertion $assertion): PracticalValidationResult;
}
