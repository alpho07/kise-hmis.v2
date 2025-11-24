<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Receipt;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Services\PaymentProcessingService;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['processed_by'] = auth()->id();
        $data['payment_date'] = $data['payment_date'] ?? now();
        $data['status'] = 'completed';

        // Handle payment type
        if ($data['payment_type'] === 'multiple') {
            // Calculate total from splits
            $totalPaid = collect($data['payment_splits'] ?? [])->sum('amount');
            $data['amount_paid'] = $totalPaid;
            $data['payment_method'] = 'multiple';
            
            // Store payment splits as JSON
            $data['payment_details'] = json_encode($data['payment_splits']);
        }

        // Auto-generate reference number
        if (empty($data['reference_number'])) {
            $data['reference_number'] = 'PAY-' . date('Ymd') . '-' . str_pad(\App\Models\Payment::count() + 1, 5, '0', STR_PAD_LEFT);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $payment = $this->record;
        
        // Use the payment processing service with triage verification
        $paymentService = new PaymentProcessingService();
        $result = $paymentService->processPaymentAndCreateQueues($payment);
        
        // Handle the result
        if (!$result['success']) {
            // Service access was blocked - notifications already sent by service
            return;
        }
        
        // Success - continue with receipt generation if needed
        $this->generateReceipt($payment);
        
        // Redirect to success page or queue view
        $this->redirect(route('filament.admin.resources.queue-entries.index'));
    }
}