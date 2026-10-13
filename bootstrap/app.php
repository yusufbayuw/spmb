<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\ValidateApplicantPasswordResetSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(AssignRequestId::class);
        // Filament's reset route uses the 'signed' alias. Preserve Laravel's
        // strict validation everywhere else while supporting proxied applicant links.
        $middleware->alias(['signed' => ValidateApplicantPasswordResetSignature::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response): Response {
            if ($requestId = request()->attributes->get('request_id')) {
                $response->headers->set('X-Request-ID', $requestId);
            }

            return $response;
        });
    })->create();
