<?php

namespace App\Filament\Resources\InsuranceProviderResource\Pages;

use App\Filament\Resources\InsuranceProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInsuranceProvider extends ViewRecord
{
    protected static string $resource = InsuranceProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
