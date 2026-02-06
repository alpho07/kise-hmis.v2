<?php

namespace App\Filament\Resources\InsuranceClaimResource\Pages;

use App\Filament\Resources\InsuranceClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInsuranceClaim extends ViewRecord
{
    protected static string $resource = InsuranceClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
