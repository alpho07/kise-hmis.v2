<?php

namespace App\Filament\Resources\PaymentResource\Widgets;

use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingCount = Payment::whereHas('invoice.visit', function ($query) {
            $query->where('current_stage', 'payment');
        })->count();
        
        $todayCount = Payment::where('status', 'completed')
            ->whereDate('payment_date', today())
            ->count();
        
        $todayAmount = Payment::where('status', 'completed')
            ->whereDate('payment_date', today())
            ->sum('amount_paid');
        
        $cashToday = Payment::where('payment_method', 'cash')
            ->whereDate('payment_date', today())
            ->sum('amount_paid');
        
        $mpesaToday = Payment::where('payment_method', 'mpesa')
            ->whereDate('payment_date', today())
            ->sum('amount_paid');

        return [
            Stat::make('Awaiting Payment', $pendingCount)
                ->description('Clients in payment queue')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart([3, 4, 2, 5, 4, 3, $pendingCount]),

            Stat::make('Payments Today', $todayCount)
                ->description('KES ' . number_format($todayAmount, 2))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([5, 8, 6, 10, 12, 15, $todayCount]),

            Stat::make('Cash Today', 'KES ' . number_format($cashToday, 2))
                ->description('Cash transactions')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('M-PESA Today', 'KES ' . number_format($mpesaToday, 2))
                ->description('Mobile money')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}