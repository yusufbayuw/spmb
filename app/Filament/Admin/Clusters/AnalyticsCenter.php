<?php

namespace App\Filament\Admin\Clusters;

use Filament\Clusters\Cluster;

class AnalyticsCenter extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Pusat Analitik';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 0;

    protected static ?string $clusterBreadcrumb = 'Pusat Analitik';
}
