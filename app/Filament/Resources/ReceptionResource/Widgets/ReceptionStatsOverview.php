<?php

namespace App\Filament\Resources\ReceptionResource\Widgets;

use App\Models\Visit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReceptionStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $today = today();
        
        $totalToday = Visit::where('current_stage', 'reception')
            ->whereDate('check_in_time', $today)
            ->count();
        
        $waitingForTriage = Visit::where('current_stage', 'reception')
            ->whereDate('check_in_time', $today)
            ->where('service_available', 'yes')
            ->count();
        
        $deferred = Visit::where('current_stage', 'reception')
            ->whereDate('check_in_time', $today)
            ->where('service_available', 'no')
            ->count();
        
        $emergency = Visit::where('current_stage', 'reception')
            ->whereDate('check_in_time', $today)
            ->where('is_emergency', true)
            ->count();
        
        $longWait = Visit::where('current_stage', 'reception')
            ->whereDate('check_in_time', $today)
            ->where('check_in_time', '<=', now()->subMinutes(30))
            ->count();
        
        $avgWaitTime = Visit::where('current_stage', 'reception')
            ->whereDate('check_in_time', $today)
            ->get()
            ->avg(fn ($visit) => $visit->check_in_time->diffInMinutes(now()));

        return [
            Stat::make('Total Check-Ins Today', $totalToday)
                ->description('All clients at reception')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('primary')
                ->chart([5, 8, 12, 15, 18, 20, $totalToday]),
            
            Stat::make('Waiting for Triage', $waitingForTriage)
                ->description($waitingForTriage > 0 ? 'Ready to proceed' : 'Queue clear')
                ->descriptionIcon('heroicon-o-clock')
                ->color($waitingForTriage > 10 ? 'danger' : ($waitingForTriage > 5 ? 'warning' : 'success')),
            
            Stat::make('Average Wait Time', round($avgWaitTime) . ' min')
                ->description($avgWaitTime > 30 ? 'Above target' : 'Within target')
                ->descriptionIcon($avgWaitTime > 30 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($avgWaitTime > 30 ? 'danger' : 'success'),
            
            Stat::make('Waiting > 30 Min', $longWait)
                ->description($longWait > 0 ? 'Needs attention!' : 'All within time')
                ->descriptionIcon($longWait > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($longWait > 0 ? 'danger' : 'success'),
            
            Stat::make('Emergency Cases', $emergency)
                ->description($emergency > 0 ? 'Priority handling required' : 'No emergencies')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($emergency > 0 ? 'danger' : 'gray'),
            
            Stat::make('Deferred Visits', $deferred)
                ->description('Service unavailable')
                ->descriptionIcon('heroicon-o-pause-circle')
                ->color($deferred > 0 ? 'warning' : 'gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}