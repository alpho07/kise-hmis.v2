<?php

namespace App\Filament\Resources\IntakeAssessmentResource\Widgets;

use App\Models\Visit;
use App\Models\IntakeAssessment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IntakeQueueStats extends BaseWidget
{
    protected function getStats(): array
    {
        $today = today();
        
        $pending = Visit::where('current_stage', 'intake')
            ->whereDate('check_in_time', $today)
            ->doesntHave('intakeAssessment')
            ->count();
        
        $completedToday = IntakeAssessment::whereDate('created_at', $today)->count();
        
        $avgTime = IntakeAssessment::whereDate('created_at', $today)
            ->get()
            ->map(function ($intake) {
                $stage = $intake->visit?->stages()
                    ?->where('stage', 'intake')
                    ->whereNotNull('completed_at')
                    ->first();
                
                if ($stage) {
                    return $stage->started_at->diffInMinutes($stage->completed_at);
                }
                return null;
            })
            ->filter()
            ->avg();
        
        $longWait = Visit::where('current_stage', 'intake')
            ->whereDate('check_in_time', $today)
            ->whereHas('stages', function ($q) {
                $q->where('stage', 'intake')
                  ->whereNull('completed_at')
                  ->where('started_at', '<=', now()->subMinutes(45));
            })
            ->count();
        
        $newClients = Visit::where('current_stage', 'intake')
            ->whereDate('check_in_time', $today)
            ->where('visit_type', 'initial')
            ->count();
        
        $totalServicesSelected = \App\Models\ServiceBooking::whereHas('visit', function ($q) use ($today) {
            $q->whereHas('intakeAssessment', fn ($q2) => $q2->whereDate('created_at', $today));
        })->count();

        return [
            Stat::make('Pending Intake', $pending)
                ->description($pending > 0 ? 'Waiting for assessment' : 'Queue clear')
                ->descriptionIcon('heroicon-o-clock')
                ->color($pending > 8 ? 'danger' : ($pending > 4 ? 'warning' : 'success'))
                ->chart([2, 4, 6, 8, 10, $pending]),
            
            Stat::make('Completed Today', $completedToday)
                ->description('Intake assessments done')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->chart([5, 10, 15, 20, 25, $completedToday]),
            
            Stat::make('Avg Assessment Time', round($avgTime ?? 0) . ' min')
                ->description($avgTime > 30 ? 'Above target' : 'On target')
                ->descriptionIcon($avgTime > 30 ? 'heroicon-o-clock' : 'heroicon-o-check-circle')
                ->color($avgTime > 30 ? 'warning' : 'success'),
            
            Stat::make('Waiting > 45 Min', $longWait)
                ->description($longWait > 0 ? '⚠️ Needs attention' : 'All within target')
                ->descriptionIcon($longWait > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($longWait > 0 ? 'danger' : 'success'),
            
            Stat::make('New Clients', $newClients)
                ->description('First-time visitors')
                ->descriptionIcon('heroicon-o-user-plus')
                ->color('info'),
            
            Stat::make('Services Selected', $totalServicesSelected)
                ->description('Total services booked today')
                ->descriptionIcon('heroicon-o-wrench-screwdriver')
                ->color('primary'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}