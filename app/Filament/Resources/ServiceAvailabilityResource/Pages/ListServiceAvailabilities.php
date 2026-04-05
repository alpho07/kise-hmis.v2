<?php
namespace App\Filament\Resources\ServiceAvailabilityResource\Pages;

use App\Filament\Resources\ServiceAvailabilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceAvailabilities extends ListRecords
{
    protected static string $resource = ServiceAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
