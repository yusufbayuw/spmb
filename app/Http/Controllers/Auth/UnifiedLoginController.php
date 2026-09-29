<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UnifiedLoginRequest;
use App\Models\User;
use App\Services\PortalDestinationService;
use App\Services\UnifiedLoginCaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UnifiedLoginController extends Controller
{
    public function create(
        PortalDestinationService $destinations,
        UnifiedLoginCaptcha $captcha,
    ): View|RedirectResponse {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user instanceof User && ($destination = $destinations->pathFor($user))) {
                return redirect($destination);
            }

            Auth::logout();
        }

        return view('auth.unified-login', [
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
        UnifiedLoginRequest $request,
        PortalDestinationService $destinations,
        UnifiedLoginCaptcha $captcha,
    ): RedirectResponse {
        try {
            $request->authenticate();
        } catch (ValidationException $exception) {
            $captcha->images(refresh: true);

            throw $exception;
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user instanceof User || ! ($destination = $destinations->pathFor($user))) {
            Auth::logout();
            $captcha->images(refresh: true);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $intended = $request->session()->get('url.intended');

        if (! $destinations->intendedUrlIsAllowed(
            $user,
            is_string($intended) ? $intended : null,
        )) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended($destination);
    }
}
