<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Registration;
use App\Services\ApplicantFileStorage;
use App\Services\RegistrationWorkflowService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class PaymentUpload extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Pembayaran';
    protected static ?string $slug = 'pembayaran/{registration}';
    protected static string $view = 'filament.applicant.pages.payment-upload';

    public Registration $registrationRecord;
    public ?array $data = [];

    public function mount(int|string $registration): void
    {
        $this->registrationRecord = Registration::query()
            ->where('user_id', auth()->id())
            ->with(['unit', 'latestPayment'])
            ->findOrFail($registration);

        abort_unless(
            $this->registrationRecord->latestPayment
            && in_array($this->registrationRecord->current_stage, ['payment', 'payment_verification'], true),
            403,
        );

        $this->form->fill([
            'payment_method' => $this->registrationRecord->latestPayment->payment_method,
        ]);
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::FourExtraLarge;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('proof')
                    ->label('Bukti pembayaran')
                    ->helperText('PDF/JPG/PNG, maksimal 5 MB. File tersimpan privat dan hanya dapat diakses oleh akun berwenang.')
                    ->disk(ApplicantFileStorage::PRIVATE_DISK)
                    ->directory(fn (): string => 'payments/'.$this->registrationRecord->id)
                    ->visibility('private')
                    ->previewable(false)
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(5120)
                    ->required(),
                Select::make('payment_method')
                    ->label('Metode pembayaran')
                    ->options([
                        'bank_transfer' => 'Transfer bank',
                        'mobile_banking' => 'Mobile banking',
                        'internet_banking' => 'Internet banking',
                        'atm' => 'ATM',
                        'other' => 'Lainnya',
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(
        RegistrationWorkflowService $workflow,
        ApplicantFileStorage $storage,
    ): void {
        abort_unless($this->registrationRecord->fresh()->current_stage === 'payment', 403);

        $data = $this->form->getState();
        $payment = $this->registrationRecord->latestPayment;
        $oldPath = $payment->proof_path;
        $newPath = $data['proof'];

        if ($oldPath && $oldPath !== $newPath) {
            $storage->delete($oldPath);
        }

        $payment->update([
            'proof_path' => $newPath,
            'proof_original_name' => basename($newPath),
            'payment_method' => $data['payment_method'] ?? null,
        ]);

        $workflow->markPaymentUploaded($payment);

        Notification::make()
            ->title('Bukti pembayaran berhasil dikirim')
            ->body('Bukti tersimpan privat. Tata Usaha akan memverifikasi pembayaran Anda.')
            ->success()
            ->send();

        $this->redirect(RegistrationStatus::getUrl(['registration' => $this->registrationRecord->id]));
    }
}
