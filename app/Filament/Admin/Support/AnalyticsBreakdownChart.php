<?php

namespace App\Filament\Admin\Support;

use App\Services\AnalyticsCenterService;
use Filament\Widgets\ChartWidget;

class AnalyticsBreakdownChart extends ChartWidget
{
    protected static ?string $heading = 'Distribusi utama';

    protected static ?string $maxHeight = '330px';

    public array $filters = [];

    public string $section = 'overview';

    protected function getData(): array
    {
        $data = app(AnalyticsCenterService::class)
            ->chart(auth()->user(), $this->filters, $this->section);

        return [
            'datasets' => [
                ['label' => 'Jumlah', 'data' => $data['values']],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
