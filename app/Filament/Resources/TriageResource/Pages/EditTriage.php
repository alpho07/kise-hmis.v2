<?php

namespace App\Filament\Resources\TriageResource\Pages;

use App\Filament\Resources\TriageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTriage extends EditRecord
{
    protected static string $resource = TriageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
