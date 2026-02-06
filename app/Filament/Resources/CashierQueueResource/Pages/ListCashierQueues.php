<?php

namespace App\Filament\Resources\CashierQueueResource\Pages;

use App\Filament\Resources\CashierQueueResource;
use Filament\Resources\Pages\ListRecords;

class ListCashierQueues extends ListRecords
{
    protected static string $resource = CashierQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getHeading(): string
    {
        $count = $this->getTableRecords()->count();
        return "Cashier Queue ({$count} waiting)";
    }

    public function getSubheading(): ?string
    {
        return 'Clients ready for payment processing';
    }
}