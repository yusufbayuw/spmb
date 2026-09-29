<?php

namespace App\Filament\Legal\Pages;

use App\Services\AccountConsentService;
use App\Services\RegistrationConsentService;
use Filament\Pages\SimplePage;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;

abstract class LegalPolicyPage extends SimplePage
{
    protected static string $view = 'filament.legal.policy';

    protected ?string $maxWidth = MaxWidth::FourExtraLarge->value;

    public string $policyContent = '';

    public int $policyVersion = 1;

    public ?string $publishedAtLabel = null;

    abstract protected function policyKind(): string;

    public function mount(): void
    {
        $policy = app(AccountConsentService::class)->current();
        $sanitizer = app(RegistrationConsentService::class);
        $kind = $this->policyKind();

        $this->heading = $kind === 'terms'
            ? (string) $policy->terms_title
            : (string) $policy->privacy_title;

        $rawContent = $kind === 'terms'
            ? (string) $policy->terms_content
            : (string) $policy->privacy_content;

        $this->policyContent = $sanitizer->sanitizeHtml($rawContent);
        $this->policyVersion = (int) $policy->version;
        $this->publishedAtLabel = $policy->published_at
            ? $policy->published_at
                ->timezone(config('app.timezone'))
                ->translatedFormat('d F Y H:i').' WIB'
            : null;
    }

    public function getTitle(): string | Htmlable
    {
        return (string) $this->heading;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Versi '.$this->policyVersion
            .($this->publishedAtLabel ? ' · Berlaku sejak '.$this->publishedAtLabel : '');
    }
}
