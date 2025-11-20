<?php

namespace App\Filament\Resources\IntakeAssessmentResource\Pages;

use App\Filament\Resources\IntakeAssessmentResource;
use App\Models\Visit;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListIntakeAssessments extends ListRecords
{
    protected static string $resource = IntakeAssessmentResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            IntakeAssessmentResource\Widgets\IntakeQueueStats::class,
        ];
    }

    public function getTabs(): array
    {
        $today = today();
        
        return [
            'pending' => Tab::make('Pending Intake')
                ->badge(Visit::where('current_stage', 'intake')
                    ->whereDate('check_in_time', $today)
                    ->doesntHave('intakeAssessment')
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->doesntHave('intakeAssessment')
                )
                ->icon('heroicon-o-clock')
                ->badgeColor('warning'),
            
            'completed' => Tab::make('Completed Today')
                ->badge(Visit::where('current_stage', 'intake')
                    ->orWhere(function ($q) use ($today) {
                        $q->has('intakeAssessment')
                          ->whereHas('intakeAssessment', fn ($query) => 
                              $query->whereDate('created_at', $today)
                          );
                    })
                    ->whereDate('check_in_time', $today)
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->has('intakeAssessment')
                )
                ->icon('heroicon-o-check-circle')
                ->badgeColor('success'),
            
            'all' => Tab::make('All Today')
                ->badge(Visit::where('current_stage', 'intake')
                    ->whereDate('check_in_time', $today)
                    ->count())
                ->icon('heroicon-o-queue-list'),
        ];
    }
}