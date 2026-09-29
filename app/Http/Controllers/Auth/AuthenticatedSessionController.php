<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\PortalDestinationService;
use App\Services\UnifiedLoginCaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(
        PortalDestinationService $destinations,
        UnifiedLoginCaptcha $captcha,
    ): View|RedirectResponse {
        if (Auth::check()) {
            $destination = $destinations->pathFor(Auth::user());

            if ($destination) {
                return redirect($destination);
            }

            Auth::logout();
        }

        return view('auth.login', [
            'captchaImages' => $captcha->images(),
        ]);
    }

    public function captcha(UnifiedLoginCaptcha $captcha): JsonResponse
    {
        return response()
            ->json($captcha->images(refresh: true))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function store(
        LoginRequest $request,
        PortalDestinationService $destinations,
        UnifiedLoginCaptcha $captcha,
    ): RedirectResponse {
        try {
            $request->authenticate();
        } catch (ValidationException $exception) {
            $captcha->images(refresh: true);

            throw $exception;
        }

        $user = Auth::user();
        $destination = $user ? $destinations->pathFor($user) : null;

        if (! $user || ! $destination) {
            Auth::guard('web')->logout();
            $captcha->images(refresh: true);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $intended = $request->session()->get('url.intended');
        if (! $destinations->intendedUrlIsAllowed($user, is_string($intended) ? $intended : null)) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended($destination);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
