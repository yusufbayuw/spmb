<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class ApplicantEmailVerificationUrl
{
    public function for(User $user): string
    {
        $relativeUrl = URL::temporarySignedRoute(
            'applicant.email-verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'user' => $user,
                'hash' => sha1($user->getEmailForVerification()),
            ],
            absolute: false,
        );

        return rtrim((string) config('app.url'), '/').'/'.ltrim($relativeUrl, '/');
    }
}
