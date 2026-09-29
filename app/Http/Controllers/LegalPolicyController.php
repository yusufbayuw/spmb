<?php

namespace App\Http\Controllers;

use App\Services\AccountConsentService;
use App\Services\RegistrationConsentService;
use Illuminate\Contracts\View\View;

class LegalPolicyController extends Controller
{
    public function terms(): View
    {
        $policy = app(AccountConsentService::class)->current();

        return view('legal.policy', [
            'title' => $policy->terms_title,
            'content' => app(RegistrationConsentService::class)->sanitizeHtml((string) $policy->terms_content),
            'version' => $policy->version,
            'publishedAt' => $policy->published_at,
        ]);
    }

    public function privacy(): View
    {
        $policy = app(AccountConsentService::class)->current();

        return view('legal.policy', [
            'title' => $policy->privacy_title,
            'content' => app(RegistrationConsentService::class)->sanitizeHtml((string) $policy->privacy_content),
            'version' => $policy->version,
            'publishedAt' => $policy->published_at,
        ]);
    }
}
