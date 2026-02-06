<?php

namespace App\Filament\Resources\IntakeQueueResource\Pages;

use App\Filament\Resources\IntakeQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIntakeQueue extends EditRecord
{
    protected static string $resource = IntakeQueueResource::class;

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
