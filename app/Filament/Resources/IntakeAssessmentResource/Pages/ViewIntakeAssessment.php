<?php

namespace App\Filament\Resources\IntakeAssessmentResource\Pages;

use App\Filament\Resources\IntakeAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewIntakeAssessment extends ViewRecord
{
    protected static string $resource = IntakeAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}