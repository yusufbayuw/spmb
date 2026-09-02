<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Models\Registration;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class RegistrationStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Distribusi Status Pendaftaran';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $query = Registration::query();
        $user = auth()->user();

        if ($user?->isTU() && $user->unit_id) {
            $query->where('unit_id', $user->unit_id);
        }

        $counts = $query
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statuses = RegistrationResource::statusOptions();

        return [
            'datasets' => [
                [
                    'label' => 'Pendaftar',
                    'data' => collect(array_keys($statuses))->map(fn (string $status) => (int) ($counts[$status] ?? 0))->all(),
                ],
            ],
            'labels' => array_values($statuses),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
