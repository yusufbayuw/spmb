<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Filament\Admin\Resources\DocumentResource;
use App\Filament\Admin\Widgets\DocumentUploadStats;
use App\Services\DocumentUploadOverview;
use App\Services\RegistrationManualEmailService;
use App\Models\Registration;
use Filament\Actions;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    #[Url]
    public ?int $registrationId = null;

    #[Url]
    public ?bool $uploaders = null;

    public string $uploadSearch = '';

    public int $uploadPage = 1;

    // Preference lives in this staff member's session, never in applicant data.
    public bool $defaultGroupCollapsed = false;

    public function mount(): void
    {
        parent::mount();

        $this->defaultGroupCollapsed = (bool) session()->get($this->groupPreferenceKey(), false);

        if ($this->uploaders) {
            $this->mountAction('showUploaders');
        }
    }

    public function table(Table $table): Table
    {
        // Keep the resource's operational and tenant scopes. Narrow only the
        // selected registration, never fetch it through an unscoped query.
        return parent::table($table)
            ->header(fn () => view('filament.admin.document-group-controls', [
                'collapsed' => $this->defaultGroupCollapsed,
            ]))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->when($this->registrationId, fn (Builder $query): Builder => $query
                    ->where('registration_id', $this->registrationId)));
    }

    public function setDefaultGroupCollapsed(bool $collapsed): void
    {
        $this->defaultGroupCollapsed = $collapsed;
        session()->put($this->groupPreferenceKey(), $collapsed);
    }

    private function groupPreferenceKey(): string
    {
        return 'spmb.documents.default-group-collapsed.'.(auth()->id() ?: 'guest');
    }

    /** The only way to open the grouped email action from the accordion. */
    public function openApplicantEmail(int $registrationId): void
    {
        // Resolve and authorize BEFORE opening a modal, including for crafted calls.
        $registration = $this->emailRecipient($registrationId);

        $this->mountAction('composeApplicantEmail', ['registrationId' => $registration->getKey()]);
    }

    private function emailRecipient(int $registrationId): Registration
    {
        // This query inherits the document resource's operational and unit scopes;
        // a crafted ID for another unit cannot be used to open or send email.
        $document = DocumentResource::getEloquentQuery()
            ->where('registration_id', $registrationId)
            ->firstOrFail();

        $registration = $document->registration;
        abort_unless(
            $registration !== null
                && auth()->user() !== null
                && app(RegistrationManualEmailService::class)->canCompose(auth()->user(), $registration),
            403,
        );

        return $registration;
    }

    private function composeApplicantEmailAction(): Actions\Action
    {
        return Actions\Action::make('composeApplicantEmail')
            ->label('Kirim Pesan')
            ->icon('heroicon-o-paper-airplane')
            ->modalHeading(fn (array $arguments): string => 'Kirim email kepada '.$this
                ->emailRecipient((int) ($arguments['registrationId'] ?? 0))->full_name)
            ->modalDescription('Email informasi satu arah. Tidak mengubah status, tahapan pendaftaran, ataupun data seleksi. Pengiriman menggunakan antrean.')
            ->form([
                TextInput::make('subject')->label('Subjek')->required()->maxLength(150),
                RichEditor::make('body')
                    ->label('Isi pesan')
                    ->toolbarButtons(['bold', 'italic', 'underline', 'strike', 'link', 'h2', 'h3', 'blockquote', 'bulletList', 'orderedList', 'undo', 'redo'])
                    ->required()->maxLength(15000)->columnSpanFull(),
                Textarea::make('reason')->label('Alasan administrasi')->required()->maxLength(300),
            ])
            ->modalSubmitActionLabel('Antrekan Email')
            ->requiresConfirmation()
            ->action(function (array $arguments, array $data): void {
                $registration = $this->emailRecipient((int) ($arguments['registrationId'] ?? 0));

                app(RegistrationManualEmailService::class)->send(
                    $registration,
                    auth()->user(),
                    $data['subject'],
                    $data['body'],
                    $data['reason'],
                );

                Notification::make()
                    ->title('Pesan masuk antrean email')
                    ->body('Pantau status pengiriman pada Riwayat Email.')
                    ->success()->send();
            });
    }

    protected function getHeaderWidgets(): array
    {
        return [DocumentUploadStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('showUploaders')
                ->label('Lihat Pendaftar Sudah Upload')
                ->icon('heroicon-o-users')
                ->color('gray')
                ->modalHeading('Pendaftar yang Sudah Mengunggah Berkas')
                ->modalWidth('6xl')
                ->modalContent(fn () => view('filament.admin.document-upload-applicants'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),
            Actions\Action::make('clearApplicant')
                ->label('Tampilkan Semua Pendaftar')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->visible(fn (): bool => (bool) $this->registrationId)
                ->url(fn (): string => DocumentResource::getUrl('index')),
        ];
    }

    public function updatedUploadSearch(): void
    {
        $this->uploadPage = 1;
    }

    public function uploadApplicants(): LengthAwarePaginator
    {
        return app(DocumentUploadOverview::class)->applicants($this->uploadSearch, $this->uploadPage);
    }

    public function previousUploadPage(): void
    {
        $this->uploadPage = max(1, $this->uploadPage - 1);
    }

    public function nextUploadPage(): void
    {
        if ($this->uploadApplicants()->hasMorePages()) {
            $this->uploadPage++;
        }
    }

    public function getTabs(): array
    {
        // Every tab and its counter operate on physical document records,
        // even though table rows are displayed in applicant accordions.
        return [
            'all' => Tab::make('Semua')
                ->badge(fn (): int => DocumentResource::getEloquentQuery()->count()),
            'pending' => Tab::make('Menunggu Verifikasi')
                ->query(fn (Builder $query): Builder => $query->where('is_verified', false)->whereNull('rejection_reason'))
                ->badge(fn (): int => DocumentResource::getEloquentQuery()->where('is_verified', false)->whereNull('rejection_reason')->count())
                ->badgeColor('warning'),
            'rejected' => Tab::make('Ditolak')
                ->query(fn (Builder $query): Builder => $query->whereNotNull('rejection_reason'))
                ->badge(fn (): int => DocumentResource::getEloquentQuery()->whereNotNull('rejection_reason')->count())
                ->badgeColor('danger'),
            'verified' => Tab::make('Terverifikasi')
                ->query(fn (Builder $query): Builder => $query->where('is_verified', true))
                ->badge(fn (): int => DocumentResource::getEloquentQuery()->where('is_verified', true)->count())
                ->badgeColor('success'),
        ];
    }
}
