<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class VisitPipelineWidget extends Widget
{
    protected static string $view = 'filament.widgets.visit-pipeline';

    protected static ?int $sort = -2;

    protected int | string | array $columnSpan = 'full';

    public function getStages(): array
    {
        $today = today();

        $counts = Visit::query()
            ->whereDate('check_in_time', $today)
            ->select('current_stage', DB::raw('count(*) as total'))
            ->groupBy('current_stage')
            ->pluck('total', 'current_stage')
            ->toArray();

        $stages = [
            ['key' => 'reception',  'label' => 'Reception',  'icon' => '🔵'],
            ['key' => 'triage',     'label' => 'Triage',     'icon' => '🔵'],
            ['key' => 'intake',     'label' => 'Intake',     'icon' => '🔵'],
            ['key' => 'billing',    'label' => 'Billing',    'icon' => '🔵'],
            ['key' => 'payment',    'label' => 'Payment',    'icon' => '🔵'],
            ['key' => 'service',    'label' => 'Service',    'icon' => '🔵'],
            ['key' => 'completed',  'label' => 'Completed',  'icon' => '🔵'],
        ];

        $total = array_sum($counts) ?: 1;

        foreach ($stages as &$stage) {
            $count = $counts[$stage['key']] ?? 0;
            $stage['count']   = $count;
            $stage['percent'] = round(($count / $total) * 100);

            // colour class
            if ($stage['key'] === 'completed') {
                $stage['class'] = $count > 0 ? 'active' : '';
            } elseif ($count > 5) {
                $stage['class'] = 'warning';
            } elseif ($count > 0) {
                $stage['class'] = 'active';
            } else {
                $stage['class'] = '';
            }
        }
        unset($stage);

        return $stages;
    }

    public function getTodayTotal(): int
    {
        return Visit::query()->whereDate('check_in_time', today())->count();
    }
}
