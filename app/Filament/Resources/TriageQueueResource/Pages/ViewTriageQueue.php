<?php

namespace App\Filament\Resources\TriageQueueResource\Pages;

use App\Filament\Resources\TriageQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTriageQueue extends ViewRecord
{
    protected static string $resource = TriageQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
