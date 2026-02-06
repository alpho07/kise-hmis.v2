<?php

namespace App\Filament\Resources\InsuranceBatchInvoiceResource\Pages;

use App\Filament\Resources\InsuranceBatchInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInsuranceBatchInvoice extends ViewRecord
{
    protected static string $resource = InsuranceBatchInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
