<?php

namespace App\Filament\Resources\BillingResource\Pages;

use App\Filament\Resources\BillingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditBilling extends EditRecord
{
    protected static string $resource = BillingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Invoice Updated')
            ->body('The invoice has been updated successfully.');
    }

    protected function afterSave(): void
    {
        // Recalculate totals after updating
        $this->record->calculateTotals();
    }
}