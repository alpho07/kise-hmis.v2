<?php

namespace App\Filament\Resources\ReceptionResource\Pages;

use App\Filament\Resources\ReceptionResource;
use App\Models\Visit;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReceptions extends ListRecords
{
    protected static string $resource = ReceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Client Check-In')
                ->icon('heroicon-o-user-plus')
                ->color('success'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReceptionResource\Widgets\ReceptionStatsOverview::class,
            ReceptionResource\Widgets\ReceptionQueueStatus::class,
        ];
    }

    public function getTabs(): array
    {
        $today = today();
        
        return [
            'all' => Tab::make('All Today')
                ->badge(Visit::where('current_stage', 'reception')
                    ->whereDate('check_in_time', $today)
                    ->count())
                ->icon('heroicon-o-queue-list'),
            
            'waiting' => Tab::make('Waiting for Triage')
                ->badge(Visit::where('current_stage', 'reception')
                    ->whereDate('check_in_time', $today)
                    ->where('service_available', 'yes')
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('service_available', 'yes')
                )
                ->icon('heroicon-o-clock')
                ->badgeColor('warning'),
            
            'deferred' => Tab::make('Deferred')
                ->badge(Visit::where('current_stage', 'reception')
                    ->whereDate('check_in_time', $today)
                    ->where('service_available', 'no')
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('service_available', 'no')
                )
                ->icon('heroicon-o-pause-circle')
                ->badgeColor('danger'),
            
            'emergency' => Tab::make('Emergency')
                ->badge(Visit::where('current_stage', 'reception')
                    ->whereDate('check_in_time', $today)
                    ->where('is_emergency', true)
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('is_emergency', true)
                )
                ->icon('heroicon-o-exclamation-triangle')
                ->badgeColor('danger'),
            
            'long_wait' => Tab::make('Waiting > 30min')
                ->badge(Visit::where('current_stage', 'reception')
                    ->whereDate('check_in_time', $today)
                    ->where('check_in_time', '<=', now()->subMinutes(30))
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('check_in_time', '<=', now()->subMinutes(30))
                )
                ->icon('heroicon-o-clock')
                ->badgeColor('danger'),
        ];
    }
}