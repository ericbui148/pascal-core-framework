<?php

namespace App\Filament\Pages;

use App\Core\DocType\DocTypeRegistry;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\StatsOverviewWidget\Stat;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon  = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int    $navigationSort  = -1;

    public function getColumns(): int | string | array
    {
        return 3;
    }

    public function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverviewWidget::class,
            \App\Filament\Widgets\DocTypeRegistryWidget::class,
        ];
    }
}
