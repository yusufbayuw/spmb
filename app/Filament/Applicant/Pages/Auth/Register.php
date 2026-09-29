<?php

namespace App\Filament\Applicant\Pages\Auth;

use App\Filament\Support\Concerns\ResetsCaptchaOnValidationError;
use App\Filament\Support\SpmbCaptcha;
use App\Models\User;
use App\Services\AccountConsentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class Register extends BaseRegister
{
    use ResetsCaptchaOnValidationError;

    public function form(Form $form): Form
    {
        $form = parent::form($form);
        $components = $form->getComponents();

        foreach ($components as $component) {
            if (method_exists($component, 'getName') && $component->getName() === 'name') {
                $component->label('Nama Lengkap');
            }
        }

        $policy = app(AccountConsentService::class)->current();

        return $form->schema([
            ...$components,
            Forms\Components\Hidden::make('account_consent_policy_uuid')
                ->default($policy->uuid)
                ->required(),
            Forms\Components\Section::make('Persetujuan Akun')
                ->description('Persetujuan ini berlaku untuk akun platform. Persetujuan khusus data SPMB akan diminta lagi ketika Anda mulai mengisi formulir pendaftaran unit.')
                ->schema([
                    Forms\Components\Checkbox::make('account_consent_accepted')
                        ->label(new HtmlString(
                            e((string) $policy->required_confirmation_text)
                            .' <span class="text-gray-500">('
                            .'<a class="font-medium text-primary-600 underline" href="'.e(route('legal.terms')).'" target="_blank" rel="noopener noreferrer">Ketentuan Penggunaan</a>'
                            .' · '
                            .'<a class="font-medium text-primary-600 underline" href="'.e(route('legal.privacy')).'" target="_blank" rel="noopener noreferrer">Kebijakan Privasi</a>'
                            .')</span>'
                        ))
                        ->accepted()
                        ->required()
                        ->validationMessages([
                            'accepted' => 'Anda harus menyetujui Ketentuan Penggunaan dan membaca Kebijakan Privasi untuk membuat akun.',
                        ]),
                    Forms\Components\Checkbox::make('marketing_consent')
                        ->label((string) $policy->marketing_text)
                        ->visible((bool) $policy->marketing_enabled),
                    Forms\Components\Placeholder::make('account_policy_version')
                        ->label('')
                        ->content('Kebijakan akun versi '.$policy->version.'. Persetujuan informasi/promosi bersifat opsional.')
                        ->columnSpanFull(),
                ])
                ->compact(),
            SpmbCaptcha::make(),
        ]);
    }

    protected function handleRegistration(array $data): Model
    {
        $consentService = app(AccountConsentService::class);
        $policy = $consentService->current();

        if (($data['account_consent_policy_uuid'] ?? null) !== $policy->uuid) {
            throw ValidationException::withMessages([
                'account_consent_accepted' => 'Kebijakan akun telah diperbarui. Muat ulang halaman dan baca versi terbaru sebelum membuat akun.',
            ]);
        }

        if (! (bool) ($data['account_consent_accepted'] ?? false)) {
            throw ValidationException::withMessages([
                'account_consent_accepted' => 'Anda harus menyetujui Ketentuan Penggunaan dan membaca Kebijakan Privasi untuk membuat akun.',
            ]);
        }

        $marketingAccepted = (bool) ($data['marketing_consent'] ?? false);

        unset(
            $data['account_consent_policy_uuid'],
            $data['account_consent_accepted'],
            $data['marketing_consent'],
        );

        $data['role'] = 'user';
        $data['is_active'] = true;

        return DB::transaction(function () use ($data, $policy, $marketingAccepted, $consentService): Model {
            $user = parent::handleRegistration($data);

            $role = Role::firstOrCreate([
                'name' => 'pendaftar',
                'guard_name' => 'web',
            ]);

            $user->assignRole($role);

            $consentService->recordSignupConsents(
                $user,
                $policy,
                $marketingAccepted,
                request()->ip(),
                request()->userAgent(),
            );

            return $user;
        }, 5);
    }

    protected function sendEmailVerificationNotification(Model $user): void
    {
        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail() || ! $user instanceof User) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
