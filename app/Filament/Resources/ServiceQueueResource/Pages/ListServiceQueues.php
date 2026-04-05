<?php

namespace App\Filament\Resources\ServiceQueueResource\Pages;

use App\Filament\Resources\ServiceQueueResource;
use App\Models\QueueEntry;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListServiceQueues extends ListRecords
{
    protected static string $resource = ServiceQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active Queue')
                ->badge(fn () => QueueEntry::whereIn('status', ['ready', 'in_service'])
                    ->when(auth()->user()->department_id, fn ($q) => $q->where('department_id', auth()->user()->department_id))
                    ->count()
                )
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->whereIn('status', ['ready', 'in_service'])
                ),

            'served_today' => Tab::make('Served Today')
                ->badge(fn () => QueueEntry::where('status', 'completed')
                    ->whereDate('serving_completed_at', today())
                    ->when(auth()->user()->department_id, fn ($q) => $q->where('department_id', auth()->user()->department_id))
                    ->count()
                )
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->where('status', 'completed')
                      ->whereDate('serving_completed_at', today())
                ),
        ];
    }
}
