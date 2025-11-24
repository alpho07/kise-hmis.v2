<?php

namespace App\Filament\Resources\BillingResource\Widgets;

use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BillingStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingCount = Invoice::where('status', 'pending')->count();
        $pendingAmount = Invoice::where('status', 'pending')->sum('final_amount');
        
        $paidToday = Invoice::where('status', 'paid')
            ->whereDate('updated_at', today())
            ->count();
        
        $paidTodayAmount = Invoice::where('status', 'paid')
            ->whereDate('updated_at', today())
            ->sum('final_amount');
        
        $overdueCount = Invoice::where('status', 'pending')
            ->where('due_date', '<', today())
            ->count();
        
        $totalRevenue = Invoice::where('status', 'paid')
            ->whereMonth('updated_at', now()->month)
            ->sum('final_amount');

        return [
            Stat::make('Pending Invoices', $pendingCount)
                ->description('Worth KES ' . number_format($pendingAmount, 2))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning')
                ->chart([7, 3, 4, 5, 6, 3, $pendingCount]),

            Stat::make('Paid Today', $paidToday)
                ->description('KES ' . number_format($paidTodayAmount, 2))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([3, 5, 2, 4, 6, 7, $paidToday]),

            Stat::make('Overdue', $overdueCount)
                ->description('Requires immediate action')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger')
                ->chart([2, 1, 3, 2, 1, 0, $overdueCount]),

            Stat::make('Monthly Revenue', 'KES ' . number_format($totalRevenue, 2))
                ->description('Current month')
                ->descriptionIcon('heroicon-m-trending-up')
                ->color('primary')
                ->chart([100, 150, 120, 180, 200, 220, $totalRevenue / 1000]),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}