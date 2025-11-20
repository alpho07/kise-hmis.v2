<?php

namespace App\Filament\Resources\TriageQueueResource\Widgets;

use App\Models\Visit;
use App\Models\Triage;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TriageQueueStats extends BaseWidget
{
    protected function getStats(): array
    {
        $today = today();
        
        $inQueue = Visit::where('current_stage', 'triage')
            ->whereDate('check_in_time', $today)
            ->count();
        
        $emergency = Visit::where('current_stage', 'triage')
            ->whereDate('check_in_time', $today)
            ->where('is_emergency', true)
            ->count();
        
        $completedToday = Triage::whereDate('created_at', $today)->count();
        
        $longWait = Visit::where('current_stage', 'triage')
            ->whereDate('check_in_time', $today)
            ->whereHas('stages', function ($q) {
                $q->where('stage', 'triage')
                  ->whereNull('completed_at')
                  ->where('started_at', '<=', now()->subMinutes(45));
            })
            ->count();
        
        $avgTriageTime = Triage::whereDate('created_at', $today)
            ->get()
            ->map(function ($triage) {
                $stage = $triage->visit->stages()
                    ->where('stage', 'triage')
                    ->whereNotNull('completed_at')
                    ->first();
                
                if ($stage) {
                    return $stage->started_at->diffInMinutes($stage->completed_at);
                }
                return null;
            })
            ->filter()
            ->avg();
        
        $crisisToday = Triage::whereDate('created_at', $today)
            ->where('triage_status', 'crisis')
            ->count();

        return [
            Stat::make('Waiting for Triage', $inQueue)
                ->description($inQueue > 0 ? 'Active queue' : 'Queue clear')
                ->descriptionIcon('heroicon-o-users')
                ->color($inQueue > 15 ? 'danger' : ($inQueue > 8 ? 'warning' : 'success'))
                ->chart([3, 5, 8, 10, 12, 14, $inQueue]),
            
            Stat::make('Emergency Cases', $emergency)
                ->description($emergency > 0 ? '🚨 Priority required' : 'No emergencies')
                ->descriptionIcon($emergency > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($emergency > 0 ? 'danger' : 'success'),
            
            Stat::make('Triaged Today', $completedToday)
                ->description('Completed assessments')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('info')
                ->chart([10, 15, 20, 25, 28, 30, $completedToday]),
            
            Stat::make('Average Triage Time', round($avgTriageTime ?? 0) . ' min')
                ->description($avgTriageTime > 20 ? 'Above target' : 'On target')
                ->descriptionIcon($avgTriageTime > 20 ? 'heroicon-o-clock' : 'heroicon-o-check-circle')
                ->color($avgTriageTime > 20 ? 'warning' : 'success'),
            
            Stat::make('Waiting > 45 Min', $longWait)
                ->description($longWait > 0 ? '⚠️ Action needed' : 'All within target')
                ->descriptionIcon($longWait > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($longWait > 0 ? 'danger' : 'success'),
            
            Stat::make('Crisis Cases Today', $crisisToday)
                ->description($crisisToday > 0 ? 'Crisis protocols activated' : 'No crisis cases')
                ->descriptionIcon($crisisToday > 0 ? 'heroicon-o-shield-exclamation' : 'heroicon-o-shield-check')
                ->color($crisisToday > 0 ? 'danger' : 'gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}