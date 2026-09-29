<?php

namespace App\Services;

use MortezaAshrafi\FilamentShieldCaptcha\CaptchaManager;
use MortezaAshrafi\FilamentShieldCaptcha\Enums\Theme;
use MortezaAshrafi\FilamentShieldCaptcha\Rules\CaptchaRule;

class UnifiedLoginCaptcha
{
    private const COMPONENT_KEY = 'unified-login-captcha';

    public function __construct(private readonly CaptchaManager $manager)
    {
    }

    public function contextKey(): string
    {
        return $this->manager->contextKey(self::COMPONENT_KEY);
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
