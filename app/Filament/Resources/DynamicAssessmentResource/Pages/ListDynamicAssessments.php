<?php

namespace App\Filament\Resources\DynamicAssessmentResource\Pages;

use App\Filament\Resources\DynamicAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDynamicAssessments extends ListRecords
{
    protected static string $resource = DynamicAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
