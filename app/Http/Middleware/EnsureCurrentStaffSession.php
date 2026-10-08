<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentStaffSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])) {
            return $next($request);
        }

        $version = (int) $user->auth_version;
        $sessionVersion = $request->session()->get('spmb_staff_auth_version');

        // Existing sessions at version zero remain valid until the first rotation.
        if ($user->is_active && ($version === 0 && $sessionVersion === null
            || $sessionVersion !== null && (int) $sessionVersion === $version)) {
            return $next($request);
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Sesi staf telah berakhir. Silakan masuk kembali.',
        ]);
    }
}
