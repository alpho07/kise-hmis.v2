<?php

namespace App\Filament\Resources\ServicePointDashboardResource\Pages;

use App\Filament\Resources\ServicePointDashboardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServicePointDashboard extends EditRecord
{
    protected static string $resource = ServicePointDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
