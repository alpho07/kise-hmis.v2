<?php

namespace App\Filament\Resources\InsuranceBatchInvoiceResource\Pages;

use App\Filament\Resources\InsuranceBatchInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInsuranceBatchInvoice extends EditRecord
{
    protected static string $resource = InsuranceBatchInvoiceResource::class;

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
