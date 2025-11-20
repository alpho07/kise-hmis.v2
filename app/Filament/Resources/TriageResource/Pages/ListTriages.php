<?php

namespace App\Filament\Resources\TriageResource\Pages;

use App\Filament\Resources\TriageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTriages extends ListRecords
{
    protected static string $resource = TriageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
