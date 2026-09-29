<?php

namespace App\Filament\Auth\Pages;

use App\Filament\Applicant\Pages\Auth\Login as ApplicantLogin;
use App\Models\User;
use App\Services\PortalDestinationService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Contracts\Support\Htmlable;

class Login extends ApplicantLogin
{
    public function boot(): void
    {
        $panel = Filament::getPanel('pendaftar');

        Filament::setCurrentPanel($panel);
        Filament::bootCurrentPanel();
    }

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $user = Filament::auth()->user();

            if ($user instanceof User) {
                $destination = app(PortalDestinationService::class)->pathFor($user);

                if ($destination) {
                    $this->redirect($destination);

                    return;
                }
            }

            Filament::auth()->logout();
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $credentials = [
            ...$this->getCredentialsFromFormData($data),
            'is_active' => true,
        ];

        if (! Filament::auth()->attempt($credentials, $data['remember'] ?? false)) {
            $this->data['captcha'] = null;
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();
        $destinations = app(PortalDestinationService::class);

        if (! $user instanceof User || ! ($destination = $destinations->pathFor($user))) {
            Filament::auth()->logout();
            $this->data['captcha'] = null;
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        $intended = session()->get('url.intended');
        if (is_string($intended) && $destinations->intendedUrlIsAllowed($user, $intended)) {
            $target = $intended;
        } else {
            session()->forget('url.intended');
            $target = $destination;
        }

        $this->redirect($target);

        return null;
    }

    public function getHeading(): string | Htmlable
    {
        return 'Masuk ke SPMB Taruna Bakti';
    }

    public function getTitle(): string | Htmlable
    {
        return 'Masuk';
    }
}
