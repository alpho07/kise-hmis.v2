<?php

namespace App\Filament\Resources\ServiceQueueResource\Pages;

use App\Filament\Resources\ServiceQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceQueue extends EditRecord
{
    protected static string $resource = ServiceQueueResource::class;

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
