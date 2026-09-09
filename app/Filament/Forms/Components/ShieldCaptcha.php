<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\TextInput;
use MortezaAshrafi\FilamentShieldCaptcha\CaptchaManager;
use MortezaAshrafi\FilamentShieldCaptcha\Concerns\HasCaptchaOptions;
use MortezaAshrafi\FilamentShieldCaptcha\Enums\Theme;
use MortezaAshrafi\FilamentShieldCaptcha\Rules\CaptchaRule;

final class ShieldCaptcha extends TextInput
{
    use HasCaptchaOptions;

    protected string $view = 'filament.forms.components.shield-captcha';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (ShieldCaptcha $component): Action => $component->getRefreshAction(),
        ]);

        $this->afterStateHydrated(function (ShieldCaptcha $component): void {
            $manager = app(CaptchaManager::class);
            $options = $manager->optionsFromConfig($component->getCaptchaOptionOverrides());
            $manager->ensureChallenge($component->getCaptchaContextKey(), $options);
        });

        $this->rule(function (ShieldCaptcha $component): CaptchaRule {
            return new CaptchaRule(app(CaptchaManager::class), $component->getCaptchaContextKey());
        });
    }

    public function getCaptchaContextKey(): string
    {
        return app(CaptchaManager::class)->contextKey($this->getKey());
    }

    public function getLightImageDataUri(): string
    {
        $manager = app(CaptchaManager::class);
        $options = $manager->optionsFromConfig($this->getCaptchaOptionOverrides());

        return $manager->imageDataUri($this->getCaptchaContextKey(), $options, Theme::Light);
    }

    public function getDarkImageDataUri(): string
    {
        $manager = app(CaptchaManager::class);
        $options = $manager->optionsFromConfig($this->getCaptchaOptionOverrides());

        return $manager->imageDataUri($this->getCaptchaContextKey(), $options, Theme::Dark);
    }

    public function getRefreshAction(): Action
    {
        return Action::make('refresh')
            ->label((string) (config('filament-shield-captcha.ui.refresh.label') ?: 'Muat captcha baru'))
            ->icon((string) (config('filament-shield-captcha.ui.refresh.icon') ?: 'heroicon-m-arrow-path'))
            ->iconButton()
            ->color('gray')
            ->action(function (ShieldCaptcha $component): void {
                $manager = app(CaptchaManager::class);
                $options = $manager->optionsFromConfig($component->getCaptchaOptionOverrides());
                $manager->refreshChallenge($component->getCaptchaContextKey(), $options);
                $component->state(null);
            });
    }

    public function getViewData(): array
    {
        return [
            ...parent::getViewData(),
            'lightDataUri' => $this->getLightImageDataUri(),
            'darkDataUri' => $this->getDarkImageDataUri(),
        ];
    }
}
