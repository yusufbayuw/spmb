<?php

namespace App\Filament\Admin\Pages;

use App\Models\MailDeliveryAttempt;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;

class NotificationDeliveryCenter extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';
    protected static ?string $navigationLabel = 'Pengiriman Email';
    protected static ?string $title = 'Monitoring Pengiriman Email';
    protected static ?string $navigationGroup = 'Sistem & Akses';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.admin.pages.notification-delivery-center';

    public string $statusFilter = 'all';
    public string $typeFilter = 'all';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return (bool) $actor?->is_active
            && ($actor?->isAdmin() || ($actor?->isAdminUnit() && $actor?->unit_id !== null));
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function scopedAttempts(): Builder
    {
        abort_unless(static::canAccess(), 403);

        return MailDeliveryAttempt::query()
            ->when(! auth()->user()->isAdmin(),
                fn (Builder $query) => $query->where('unit_id', auth()->user()->unit_id));
    }

    protected function getViewData(): array
    {
        $query = $this->scopedAttempts();
        $filtered = clone $query;

        if (in_array($this->statusFilter, ['queued', 'sent', 'failed', 'skipped'], true)) {
            $filtered->where('status', $this->statusFilter);
        }
        if (array_key_exists($this->typeFilter, MailDeliveryAttempt::TYPES)) {
            $filtered->where('type', $this->typeFilter);
        }

        return [
            'attempts' => $filtered->with(['registration', 'requester'])->latest('id')->paginate(25),
            'queuedCount' => (clone $query)->where('status', 'queued')->count(),
            'stuckCount' => (clone $query)->where('status', 'queued')
                ->where('created_at', '<', now()->subMinutes(15))->count(),
            'failedCount' => (clone $query)->where('status', 'failed')->count(),
            'sentCount' => (clone $query)->where('status', 'sent')
                ->where('created_at', '>=', now()->subDay())->count(),
            'types' => MailDeliveryAttempt::TYPES,
        ];
    }
}
