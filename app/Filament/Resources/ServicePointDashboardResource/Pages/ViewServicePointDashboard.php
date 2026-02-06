<?php

namespace App\Filament\Resources\ServicePointDashboardResource\Pages;

use App\Filament\Resources\ServicePointDashboardResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewServicePointDashboard extends ViewRecord
{
    protected static string $resource = ServicePointDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
