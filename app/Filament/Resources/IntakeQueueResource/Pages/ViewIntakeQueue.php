<?php

namespace App\Filament\Resources\IntakeQueueResource\Pages;

use App\Filament\Resources\IntakeQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewIntakeQueue extends ViewRecord
{
    protected static string $resource = IntakeQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
