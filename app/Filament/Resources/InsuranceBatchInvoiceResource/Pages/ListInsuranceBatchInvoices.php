<?php

namespace App\Filament\Resources\InsuranceBatchInvoiceResource\Pages;

use App\Filament\Resources\InsuranceBatchInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInsuranceBatchInvoices extends ListRecords
{
    protected static string $resource = InsuranceBatchInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
