<?php
namespace App\Filament\Resources\ServiceAvailabilityResource\Pages;

use App\Filament\Resources\ServiceAvailabilityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceAvailability extends CreateRecord
{
    protected static string $resource = ServiceAvailabilityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['updated_by'] = auth()->id();
        if (empty($data['branch_id'])) {
            $dept = \App\Models\Department::find($data['department_id']);
            $data['branch_id'] = $dept?->branch_id ?? auth()->user()->branch_id;
        }
        return $data;
    }
}
