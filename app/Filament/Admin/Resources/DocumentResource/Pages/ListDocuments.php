<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Filament\Admin\Resources\DocumentResource;
use App\Filament\Admin\Widgets\DocumentUploadStats;
use App\Services\DocumentUploadOverview;
use Filament\Actions;
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

    public function mount(): void
    {
        parent::mount();

        if ($this->uploaders) {
            $this->mountAction('showUploaders');
        }
    }

    public function table(Table $table): Table
    {
        // Keep the resource's operational and tenant scopes. Narrow only the
        // selected registration, never fetch it through an unscoped query.
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->when($this->registrationId, fn (Builder $query): Builder => $query
                    ->where('registration_id', $this->registrationId)));
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
