<?php

namespace App\Filament\Resources\IntakeAssessmentResource\Pages;

use App\Filament\Resources\IntakeAssessmentResource;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class CreateIntakeAssessment extends CreateRecord
{
    protected static string $resource = IntakeAssessmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        DB::transaction(function () {
            $intakeAssessment = $this->record;
            $visit = $intakeAssessment->visit;
            $client = $visit->client;
            
            $selectedServices = $this->data['selected_services'] ?? [];
            $billingRoute = $this->data['billing_route'] ?? 'billing';
            $primaryPaymentMethod = $this->data['primary_payment_method'] ?? 'cash';
            $billingNotes = $this->data['billing_notes'] ?? null;

            if (empty($selectedServices)) {
                Notification::make()
                    ->warning()
                    ->title('No Services Selected')
                    ->body('Please select at least one service.')
                    ->send();
                return;
            }

            // Create service bookings
            $services = Service::whereIn('id', $selectedServices)->get();
            $serviceBookings = [];

            foreach ($services as $service) {
                $booking = ServiceBooking::create([
                    'visit_id' => $visit->id,
                    'client_id' => $client->id,
                    'service_id' => $service->id,
                    'department_id' => $service->department_id,
                    'booking_date' => now(),
                    'estimated_duration' => $service->estimated_duration ?? 30,
                    'payment_status' => 'pending',
                    'service_status' => 'scheduled',
                    'priority_level' => 'normal',
                ]);

                $serviceBookings[] = $booking;
            }

            // Create invoice (DRAFT status)
            $invoice = Invoice::create([
                'visit_id' => $visit->id,
                'client_id' => $client->id,
                'invoice_number' => 'INV-' . date('Ymd') . '-' . str_pad(Invoice::count() + 1, 5, '0', STR_PAD_LEFT),
                'total_amount' => $this->data['quote_subtotal'] ?? 0,
                'tax_amount' => $this->data['quote_tax'] ?? 0,
                'discount_amount' => 0,
                'final_amount' => $this->data['quote_total'] ?? 0,
                'payment_method' => $primaryPaymentMethod,
                'status' => $billingRoute === 'billing' ? 'draft' : 'pending', // draft if going to billing
                'issued_by' => auth()->id(),
                'issued_at' => now(),
                'due_date' => now()->addDays(7),
                'notes' => $billingNotes,
            ]);

            // Create invoice items
            foreach ($serviceBookings as $booking) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'service_booking_id' => $booking->id,
                    'service_id' => $booking->service_id,
                    'service_name' => $booking->service->name,
                    'unit_price' => $booking->service->price,
                    'quantity' => 1,
                    'subtotal' => $booking->service->price,
                ]);
            }

            // Route based on selection
            if ($billingRoute === 'billing') {
                // Send to billing department
                $visit->moveToStage('billing');
                
                Notification::make()
                    ->success()
                    ->title('Sent to Billing Department')
                    ->body("Invoice {$invoice->invoice_number} created. Client routed to billing for verification.")
                    ->send();
            } else {
                // Send directly to cashier
                $invoice->update(['status' => 'pending']);
                $visit->moveToStage('payment');
                
                Notification::make()
                    ->success()
                    ->title('Sent to Cashier')
                    ->body("Invoice {$invoice->invoice_number} created. Client routed directly to cashier.")
                    ->send();
            }
        });
    }
}