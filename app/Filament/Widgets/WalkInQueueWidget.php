<?php
namespace App\Filament\Widgets;

use App\Models\Visit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WalkInQueueWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected int|string|array $columnSpan = 2;

    protected function getStats(): array
    {
        $count = Visit::where('current_stage', 'reception')
            ->where('is_appointment', false)
            ->where('branch_id', auth()->user()->branch_id)
            ->count();

        return [
            Stat::make('Walk-Ins at Reception', $count)
                ->description('Awaiting reception processing')
                ->descriptionIcon('heroicon-m-users')
                ->color($count > 10 ? 'danger' : ($count > 5 ? 'warning' : 'success'))
                ->url(url('/admin/visits')),
        ];
    }
}
