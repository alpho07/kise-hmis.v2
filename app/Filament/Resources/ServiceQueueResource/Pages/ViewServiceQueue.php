<?php

namespace App\Filament\Resources\ServiceQueueResource\Pages;

use App\Filament\Resources\ServiceQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceQueue extends ViewRecord
{
    protected static string $resource = ServiceQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
