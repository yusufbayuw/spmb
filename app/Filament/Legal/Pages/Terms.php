<?php

namespace App\Filament\Legal\Pages;

class Terms extends LegalPolicyPage
{
    protected function policyKind(): string
    {
        return 'terms';
    }
}
