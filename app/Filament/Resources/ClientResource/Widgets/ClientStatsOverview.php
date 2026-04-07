<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Models\Client;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalClients = Client::count();
        $activeClients = Client::active()->count();
        $newThisMonth = Client::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $withNCPWD = Client::whereNotNull('ncpwd_number')->count();
        $minors = Client::where('estimated_age', '<', 18)->count();
        $recentVisits = Client::whereHas('visits', fn($q) => $q->where('check_in_time', '>=', now()->subDays(30)))->count();

        return [
            Stat::make('Total Clients', number_format($totalClients))
                ->description('All registered clients')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary')
                ->chart([7, 12, 15, 18, 20, 23, $totalClients]),
            
            Stat::make('Active Clients', number_format($activeClients))
                ->description(number_format(($activeClients / max($totalClients, 1)) * 100, 1) . '% of total')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
            
            Stat::make('New This Month', number_format($newThisMonth))
                ->description('Registered in ' . now()->format('F Y'))
                ->descriptionIcon('heroicon-o-user-plus')
                ->color('info'),
            
            Stat::make('With NCPWD', number_format($withNCPWD))
                ->description(number_format(($withNCPWD / max($totalClients, 1)) * 100, 1) . '% coverage')
                ->descriptionIcon('heroicon-o-shield-check')
                ->color('warning'),
            
            Stat::make('Minors (< 18)', number_format($minors))
                ->description(number_format(($minors / max($totalClients, 1)) * 100, 1) . '% of clients')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('purple'),
            
            Stat::make('Visited Last 30 Days', number_format($recentVisits))
                ->description('Recent engagement')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('cyan'),
        ];
    }
}