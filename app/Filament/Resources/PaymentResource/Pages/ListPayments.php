<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getHeading(): string
    {
        $count = $this->getTableRecords()->count();
        $total = $this->getTableQuery()->sum('amount_paid');
        
        return "Payments ({$count} transactions)";
    }

    public function getSubheading(): ?string
    {
        $total = $this->getTableQuery()->sum('amount_paid');
        return 'Total: KES ' . number_format($total, 2);
    }
}