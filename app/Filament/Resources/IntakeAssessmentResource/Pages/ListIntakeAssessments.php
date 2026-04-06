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

        // Pending count: visits currently in intake stage that have no assessment yet
        $pendingCount = Visit::where('current_stage', 'intake')
            ->whereDate('check_in_time', $today)
            ->doesntHave('intakeAssessment')
            ->count();

        return [
            'all' => Tab::make('All Assessments')
                ->icon('heroicon-o-queue-list'),

            'today' => Tab::make('Completed Today')
                ->badge(
                    \App\Models\IntakeAssessment::whereDate('created_at', $today)->count()
                )
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->whereDate('created_at', $today)
                )
                ->icon('heroicon-o-check-circle')
                ->badgeColor('success'),

            'pending_info' => Tab::make('Awaiting Intake')
                ->badge($pendingCount)
                ->badgeColor('warning')
                // This tab shows existing assessments — the badge is informational only.
                // True "pending" visits have no IntakeAssessment record yet, so we show all.
                ->modifyQueryUsing(fn (Builder $query) => $query)
                ->icon('heroicon-o-clock'),
        ];
    }
}
