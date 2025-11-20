<?php

namespace App\Filament\Resources\TriageQueueResource\Pages;

use App\Filament\Resources\TriageQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTriageQueue extends EditRecord
{
    protected static string $resource = TriageQueueResource::class;

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
