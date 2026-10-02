<?php

namespace App\Services\PracticalValidators;

use App\Models\PracticalAssertion;
use App\Models\PracticalRun;
use App\Models\PracticalRunAssertion;

interface PracticalValidator
{
    public function validate(
        PracticalRun $run,
        PracticalAssertion|PracticalRunAssertion $assertion,
    ): PracticalValidationResult;
}
