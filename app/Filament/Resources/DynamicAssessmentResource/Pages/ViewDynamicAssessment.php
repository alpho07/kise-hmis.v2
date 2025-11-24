<?php

namespace App\Filament\Resources\DynamicAssessmentResource\Pages;

use App\Filament\Resources\DynamicAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDynamicAssessment extends ViewRecord
{
    protected static string $resource = DynamicAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
