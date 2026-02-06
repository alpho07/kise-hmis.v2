<?php

namespace App\Filament\Resources\CashierQueueResource\Pages;

use App\Filament\Resources\CashierQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCashierQueue extends ViewRecord
{
    protected static string $resource = CashierQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
