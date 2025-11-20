<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientResource\Widgets\ClientStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Clients')
                ->badge(Client::count()),
            
            'active' => Tab::make('Active')
                ->badge(Client::active()->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->active()),
            
            'new' => Tab::make('New Clients')
                ->badge(Client::where('client_type', 'new')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('client_type', 'new')),
            
            'returning' => Tab::make('Returning')
                ->badge(Client::where('client_type', 'returning')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('client_type', 'returning')),
            
            'old_new' => Tab::make('Old-New')
                ->badge(Client::where('client_type', 'old_new')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('client_type', 'old_new')),
            
            'minors' => Tab::make('Minors (< 18)')
                ->badge(Client::where('estimated_age', '<', 18)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estimated_age', '<', 18)),
            
            'with_ncpwd' => Tab::make('With NCPWD')
                ->badge(Client::whereNotNull('ncpwd_number')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('ncpwd_number')),
            
            'inactive' => Tab::make('Inactive')
                ->badge(Client::where('is_active', false)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false)),
        ];
    }
}