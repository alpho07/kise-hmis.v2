<?php
namespace App\Filament\Resources\ServiceAvailabilityResource\Pages;

use App\Filament\Resources\ServiceAvailabilityResource;
use Filament\Resources\Pages\EditRecord;

class EditServiceAvailability extends EditRecord
{
    protected static string $resource = ServiceAvailabilityResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();
        return $data;
    }
}
