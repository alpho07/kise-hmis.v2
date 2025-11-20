<?php

namespace App\Filament\Resources\VisitResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class VisitStageTimeline extends Widget
{
    protected static string $view = 'filament.resources.visit-resource.widgets.visit-stage-timeline';

    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        if (!$this->record) {
            return ['stages' => collect([]), 'currentStage' => null];
        }

        $stages = $this->record->stages()
            ->orderBy('started_at')
            ->get()
            ->map(function ($stage) {
                $duration = null;
                $durationMinutes = null;
                
                if ($stage->completed_at) {
                    $durationMinutes = $stage->started_at->diffInMinutes($stage->completed_at);
                    if ($durationMinutes < 60) {
                        $duration = $durationMinutes . ' min';
                    } else {
                        $hours = floor($durationMinutes / 60);
                        $mins = $durationMinutes % 60;
                        $duration = $hours . 'h ' . $mins . 'm';
                    }
                } else {
                    $duration = $stage->started_at->diffForHumans();
                }

                return [
                    'stage' => $stage->stage,
                    'status' => $stage->status,
                    'started_at' => $stage->started_at,
                    'completed_at' => $stage->completed_at,
                    'duration' => $duration,
                    'duration_minutes' => $durationMinutes,
                    'is_current' => $stage->stage === $this->record->current_stage && $stage->status === 'in_progress',
                    'icon' => $this->getStageIcon($stage->stage),
                    'color' => $this->getStageColor($stage->stage, $stage->status),
                    'label' => $this->getStageLabel($stage->stage),
                ];
            });

        // Calculate overall visit duration
        $totalDuration = null;
        if ($this->record->check_in_time) {
            $endTime = $this->record->check_out_time ?? now();
            $totalMinutes = $this->record->check_in_time->diffInMinutes($endTime);
            
            if ($totalMinutes < 60) {
                $totalDuration = $totalMinutes . ' minutes';
            } else {
                $hours = floor($totalMinutes / 60);
                $mins = $totalMinutes % 60;
                $totalDuration = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $mins . ' min';
            }
        }

        return [
            'stages' => $stages,
            'currentStage' => $this->record->current_stage,
            'visitStatus' => $this->record->status,
            'checkInTime' => $this->record->check_in_time,
            'checkOutTime' => $this->record->check_out_time,
            'totalDuration' => $totalDuration,
        ];
    }

    protected function getStageIcon(string $stage): string
    {
        return match($stage) {
            'reception' => 'heroicon-o-user-group',
            'triage' => 'heroicon-o-heart',
            'intake' => 'heroicon-o-clipboard-document-check',
            'billing' => 'heroicon-o-currency-dollar',
            'payment' => 'heroicon-o-credit-card',
            'service' => 'heroicon-o-wrench-screwdriver',
            'crisis_management' => 'heroicon-o-shield-exclamation',
            'medical_hold' => 'heroicon-o-pause-circle',
            'completed' => 'heroicon-o-check-circle',
            default => 'heroicon-o-clock',
        };
    }

    protected function getStageColor(string $stage, string $status): string
    {
        if ($status === 'completed') {
            return 'success';
        }

        if ($status === 'in_progress') {
            return 'warning';
        }

        return match($stage) {
            'reception' => 'gray',
            'triage' => 'blue',
            'intake' => 'purple',
            'billing' => 'yellow',
            'payment' => 'orange',
            'service' => 'green',
            'crisis_management' => 'red',
            'medical_hold' => 'amber',
            default => 'gray',
        };
    }

    protected function getStageLabel(string $stage): string
    {
        return match($stage) {
            'reception' => 'Reception',
            'triage' => 'Triage',
            'intake' => 'Intake Assessment',
            'billing' => 'Billing',
            'payment' => 'Payment',
            'service' => 'Service Delivery',
            'crisis_management' => 'Crisis Management',
            'medical_hold' => 'Medical Hold',
            'completed' => 'Completed',
            default => ucfirst(str_replace('_', ' ', $stage)),
        };
    }
}