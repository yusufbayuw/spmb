<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApplicantEmailVerificationSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! $request->hasValidRelativeSignature()
            && ! $request->hasValidSignature()
            && ! $this->hasValidCanonicalAbsoluteSignature($request)
        ) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * Keep verification links issued before relative signatures were enabled
     * working when the public host differs from the host seen by PHP.
     */
    private function hasValidCanonicalAbsoluteSignature(Request $request): bool
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        if ($baseUrl === '') {
            return false;
        }

        $canonicalUrl = $baseUrl.'/'.ltrim($request->path(), '/');

        if ($query = $request->getQueryString()) {
            $canonicalUrl .= '?'.$query;
        }

        $canonicalRequest = Request::create($canonicalUrl, $request->method());

        return $canonicalRequest->hasValidSignature();
    }
}
