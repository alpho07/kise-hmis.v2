<?php

namespace App\Filament\Resources\TriageQueueResource\Pages;

use App\Filament\Resources\TriageQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTriageQueues extends ListRecords
{
    protected static string $resource = TriageQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
