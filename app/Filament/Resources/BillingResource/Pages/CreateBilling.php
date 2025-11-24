<?php

namespace App\Filament\Resources\BillingResource\Pages;

use App\Filament\Resources\BillingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class CreateBilling extends CreateRecord
{
    protected static string $resource = BillingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Invoice Created')
            ->body('The invoice has been created successfully.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['issued_by'] = auth()->id();
        $data['issued_at'] = now();
        $data['status'] = 'pending';

        return $data;
    }

    protected function afterCreate(): void
    {
        // Calculate totals after invoice items are created
        $this->record->calculateTotals();
    }
}