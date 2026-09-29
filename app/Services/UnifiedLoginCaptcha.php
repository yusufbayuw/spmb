<?php

namespace App\Services;

use Illuminate\Support\Str;
use MortezaAshrafi\FilamentShieldCaptcha\CaptchaManager;
use MortezaAshrafi\FilamentShieldCaptcha\Enums\Theme;
use MortezaAshrafi\FilamentShieldCaptcha\Rules\CaptchaRule;

class UnifiedLoginCaptcha
{
    private const SESSION_KEY = '_unified_login_captcha_context';

    public function __construct(private readonly CaptchaManager $manager)
    {
    }

    public function contextKey(): string
    {
        $nonce = session()->get(self::SESSION_KEY);

        if (! is_string($nonce) || $nonce === '') {
            $nonce = Str::random(40);
            session()->put(self::SESSION_KEY, $nonce);
        }

        return 'unified-login:'.$nonce;
    }

    /** @return array{light:string,dark:string} */
    public function images(bool $refresh = false): array
    {
        $contextKey = $this->contextKey();
        $options = $this->manager->optionsFromConfig([]);

        if ($refresh) {
            $this->manager->refreshChallenge($contextKey, $options);
        } else {
            $this->manager->ensureChallenge($contextKey, $options);
        }

        return [
            'light' => $this->manager->imageDataUri($contextKey, $options, Theme::Light),
            'dark' => $this->manager->imageDataUri($contextKey, $options, Theme::Dark),
        ];
    }

    public function rule(): CaptchaRule
    {
        return new CaptchaRule($this->manager, $this->contextKey());
    }
}
