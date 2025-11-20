<?php

namespace App\Filament\Resources\ReceptionResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class VisitTimeline extends Widget
{
    protected static string $view = 'filament.resources.reception-resource.widgets.visit-timeline';

    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        if (!$this->record) {
            return ['stages' => collect([])];
        }

        $stages = $this->record->stages()
            ->orderBy('started_at')
            ->get()
            ->map(function ($stage) {
                return [
                    'stage' => $stage->stage,
                    'status' => $stage->status,
                    'started_at' => $stage->started_at,
                    'completed_at' => $stage->completed_at,
                    'duration' => $stage->completed_at 
                        ? $stage->started_at->diffInMinutes($stage->completed_at) . ' min'
                        : $stage->started_at->diffForHumans(),
                    'icon' => match($stage->stage) {
                        'reception' => 'heroicon-o-user-group',
                        'triage' => 'heroicon-o-heart',
                        'intake' => 'heroicon-o-clipboard-document-check',
                        'billing' => 'heroicon-o-currency-dollar',
                        'service' => 'heroicon-o-wrench-screwdriver',
                        default => 'heroicon-o-clock',
                    },
                    'color' => match($stage->status) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'pending' => 'gray',
                        default => 'gray',
                    },
                ];
            });

        return [
            'stages' => $stages,
            'current_stage' => $this->record->current_stage,
        ];
    }
}