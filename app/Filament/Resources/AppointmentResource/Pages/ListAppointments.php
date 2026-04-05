<?php
namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    public function getTabs(): array
    {
        return [
            'today'    => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereDate('appointment_date', today())),
            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('appointment_date', '>', today())
                    ->whereIn('status', ['scheduled', 'confirmed'])),
            'past'     => Tab::make('Past')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('appointment_date', '<', today())),
            'all'      => Tab::make('All'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
