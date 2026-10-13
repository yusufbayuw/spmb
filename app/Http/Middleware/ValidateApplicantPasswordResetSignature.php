<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ValidateSignature;

/**
 * Filament's applicant reset route validates the signature before showing
 * the form. Support a relative signature for new links and the canonical
 * absolute signature used by previously-issued links behind a reverse proxy.
 *
 * All other signed routes continue through Laravel's original middleware.
 */
class ValidateApplicantPasswordResetSignature extends ValidateSignature
{
    public function handle($request, Closure $next, ...$args)
    {
        if ($request->routeIs('filament.pendaftar.auth.password-reset.reset')) {
            return app(ValidateApplicantEmailVerificationSignature::class)
                ->handle($request, $next);
        }

        return parent::handle($request, $next, ...$args);
    }
}
