<?php

namespace App\Filament\Resources\DynamicAssessmentResource\Pages;

use App\Filament\Resources\DynamicAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDynamicAssessment extends EditRecord
{
    protected static string $resource = DynamicAssessmentResource::class;

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
