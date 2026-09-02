<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicantEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isUser() && ! $user->hasVerifiedEmail()) {
            $url = Filament::getPanel('pendaftar')->getEmailVerificationPromptUrl();

            return redirect()->guest($url ?: '/pendaftar');
        }

        return $next($request);
    }
}
