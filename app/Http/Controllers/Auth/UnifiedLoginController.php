<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PortalDestinationService;
use App\Services\UnifiedLoginCaptcha;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
        Request $request,
        PortalDestinationService $destinations,
        UnifiedLoginCaptcha $captcha,
    ): RedirectResponse {
        try {
            $data = $request->validate([
                'email' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string'],
                'captcha' => ['required', $captcha->rule()],
            ], attributes: [
                'email' => 'email atau username',
                'captcha' => 'kode keamanan',
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));

            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $login = trim((string) $data['email']);
        $attribute = str_contains($login, '@') ? 'email' : 'username';

        if ($attribute === 'username') {
            $login = mb_strtolower($login);
        }

        if (! Auth::attempt([
            $attribute => $login,
            'password' => (string) $data['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);
            $captcha->images(refresh: true);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        if (! $user instanceof User || ! ($destination = $destinations->pathFor($user))) {
            Auth::logout();
            $captcha->images(refresh: true);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        // New staff sessions must match the generation checked by
        // EnsureCurrentStaffSession, including after a password rotation.
        if ($user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])) {
            $request->session()->put('spmb_staff_auth_version', (int) $user->auth_version);
        } else {
            $request->session()->forget('spmb_staff_auth_version');
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

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower(trim((string) $request->input('email'))).'|'.$request->ip(),
        );
    }
}
