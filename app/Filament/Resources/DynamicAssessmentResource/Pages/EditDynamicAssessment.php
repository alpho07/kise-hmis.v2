<?php

namespace App\Filament\Resources\DynamicAssessmentResource\Pages;

use App\Filament\Resources\DynamicAssessmentResource;
use App\Models\Visit;
use App\Models\Invoice;
use App\Services\PaymentRoutingService;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class EditDynamicAssessment extends EditRecord
{
    protected static string $resource = DynamicAssessmentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (!isset($data['visit_id']) && request()->has('visit_id')) {
            $data['visit_id'] = request()->query('visit_id');
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Preserve critical fields
        if (!isset($data['visit_id']) && $this->record->visit_id) {
            $data['visit_id'] = $this->record->visit_id;
        }

        if (!isset($data['client_id']) && $this->record->client_id) {
            $data['client_id'] = $this->record->client_id;
        }

        if (!isset($data['branch_id']) && $this->record->branch_id) {
            $data['branch_id'] = $this->record->branch_id;
        }

        if (!isset($data['status'])) {
            $data['status'] = $this->record->status ?? 'in_progress';
        }

        \Log::info('Updating assessment', [
            'assessment_id' => $this->record->id,
            'visit_id' => $data['visit_id'] ?? 'NULL',
            'status' => $data['status'],
        ]);

        return $data;
    }

    protected function afterSave(): void
    {
        $assessment = $this->record;

        // Only process intake assessments
        $isIntake = $assessment->schema->slug === 'intake-assessment';
        if (!$isIntake) {
            return;
        }

        \Log::info('Intake assessment updated, checking for changes', [
            'assessment_id' => $assessment->id,
            'visit_id' => $assessment->visit_id,
        ]);

        $visit = $assessment->visit;
        if (!$visit) {
            \Log::error('Visit not found for assessment', ['assessment_id' => $assessment->id]);
            return;
        }

        $responseData = $assessment->response_data;
        $newPaymentMethod = $responseData['payment_method'] ?? null;
        $selectedServices = $responseData['selected_services'] ?? [];

        if (!$newPaymentMethod) {
            Notification::make()
                ->warning()
                ->title('Payment Method Required')
                ->body('Please select a payment method to complete routing')
                ->send();
            return;
        }

        if (empty($selectedServices)) {
            Notification::make()
                ->warning()
                ->title('Services Required')
                ->body('Please select at least one service to create invoice')
                ->send();
            return;
        }

        $existingInvoice = Invoice::where('visit_id', $visit->id)->latest()->first();

        if ($existingInvoice) {
            $this->updateExistingInvoice($existingInvoice, $responseData, $visit);
        } else {
            $this->createNewInvoiceFromEdit($assessment, $visit, $responseData);
        }

        if ($assessment->status !== 'completed') {
            $assessment->update(['status' => 'completed']);
        }
    }

    /**
     * ✅ Update existing invoice with sponsor/client split
     */
    protected function updateExistingInvoice(Invoice $invoice, array $responseData, Visit $visit): void
    {
        DB::transaction(function () use ($invoice, $responseData, $visit) {
            $oldPaymentMethod = $invoice->payment_method;
            $newPaymentMethod = $responseData['payment_method'] ?? $oldPaymentMethod;
            $selectedServices = $responseData['selected_services'] ?? [];

            $paymentMethodChanged = $oldPaymentMethod !== $newPaymentMethod;

            \Log::info('Updating invoice with sponsor/client split', [
                'invoice_id' => $invoice->id,
                'old_payment_method' => $oldPaymentMethod,
                'new_payment_method' => $newPaymentMethod,
                'payment_changed' => $paymentMethodChanged,
            ]);

            // Delete old invoice items
            $invoice->items()->delete();

            // ✅ Recreate invoice items with sponsor/client split
            $totalAmount = 0;
            $totalSponsorAmount = 0;
            $totalClientAmount = 0;

            foreach ($selectedServices as $serviceData) {
                $service = \App\Models\Service::find($serviceData['service_id']);
                if (!$service) continue;

                $quantity = $serviceData['quantity'] ?? 1;
                $baseCost = $service->base_price;
                
                // Calculate sponsor/client split
                $clientPays = $this->calculateClientCost($service, $newPaymentMethod);
                $sponsorPays = $baseCost - $clientPays;
                $sponsorPercentage = $baseCost > 0 ? ($sponsorPays / $baseCost) * 100 : 0;

                $item = \App\Models\InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'service_id' => $service->id,
                    'department_id' => $service->department_id,
                    'description' => $service->name,
                    'quantity' => $quantity,
                    'unit_price' => $baseCost,
                    'subtotal' => $baseCost * $quantity,
                    
                    // ✅ Sponsor/Client Split
                    'sponsor_type' => $this->mapPaymentMethodToSponsorType($newPaymentMethod),
                    'sponsor_percentage' => $sponsorPercentage,
                    'sponsor_amount' => $sponsorPays * $quantity,
                    'client_amount' => $clientPays * $quantity,
                    'client_payment_status' => 'pending',
                    'sponsor_claim_status' => in_array($newPaymentMethod, ['sha', 'ncpwd', 'insurance_private']) 
                        ? 'pending' : null,
                ]);

                $totalAmount += $item->subtotal;
                $totalSponsorAmount += $item->sponsor_amount;
                $totalClientAmount += $item->client_amount;
            }

            // ✅ Update invoice with sponsor/client totals
            $updateData = [
                'payment_method' => $newPaymentMethod,
                'total_amount' => $totalAmount,
                'total_sponsor_amount' => $totalSponsorAmount,
                'total_client_amount' => $totalClientAmount,
                'has_sponsor' => $totalSponsorAmount > 0,
                'sponsor_claim_status' => $totalSponsorAmount > 0 ? 'pending' : null,
                'payment_notes' => $responseData['payment_notes'] ?? $invoice->payment_notes,
            ];

            // Reset verification if payment method changed
            if ($paymentMethodChanged) {
                $updateData['status'] = 'pending';
                $updateData['client_payment_status'] = 'pending';
            }

            $invoice->update($updateData);

            \Log::info('Invoice updated with totals', [
                'invoice_id' => $invoice->id,
                'total_amount' => $totalAmount,
                'sponsor_amount' => $totalSponsorAmount,
                'client_amount' => $totalClientAmount,
            ]);

            // Re-route if payment method changed
            if ($paymentMethodChanged) {
                $routing = $this->determineRouting($newPaymentMethod);
                
                $visit->update([
                    'current_stage' => $routing['next_stage'],
                    'current_stage_started_at' => now(),
                ]);

                Notification::make()
                    ->success()
                    ->title('Invoice Updated & Re-routed')
                    ->body($routing['message'])
                    ->duration(10000)
                    ->send();

                \Log::info('Invoice re-routed', [
                    'invoice_id' => $invoice->id,
                    'new_stage' => $routing['next_stage'],
                ]);
            } else {
                Notification::make()
                    ->success()
                    ->title('Invoice Updated')
                    ->body("Invoice {$invoice->invoice_number} updated successfully. Total: KES " . number_format($totalAmount, 2))
                    ->send();
            }
        });
    }

    protected function createNewInvoiceFromEdit($assessment, Visit $visit, array $responseData): void
    {
        \Log::info('Creating invoice from edited assessment', [
            'assessment_id' => $assessment->id,
            'visit_id' => $visit->id,
        ]);

        if (class_exists('\App\Services\PaymentRoutingService')) {
            $routingService = new PaymentRoutingService();
            $result = $routingService->routeAfterIntake($assessment);

            if ($result['success']) {
                Notification::make()
                    ->success()
                    ->title('Invoice Created & Routed')
                    ->body($result['message'])
                    ->duration(10000)
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Invoice Creation Failed')
                    ->body($result['error'] ?? 'Failed to create invoice')
                    ->persistent()
                    ->send();
            }
        }
    }

    /**
     * Calculate client cost based on payment method
     */
    protected function calculateClientCost($service, string $paymentMethod): float
    {
        return match($paymentMethod) {
            'sha' => $service->sha_covered 
                ? ($service->sha_price ?? ($service->base_price * 0.2))
                : $service->base_price * 0.2,
            
            'ncpwd' => $service->ncpwd_covered
                ? ($service->ncpwd_price ?? ($service->base_price * 0.1))
                : $service->base_price * 0.1,
            
            'waiver' => 0.00,
            
            default => $service->base_price,
        };
    }

    /**
     * Map payment method to sponsor type
     */
    protected function mapPaymentMethodToSponsorType(string $paymentMethod): ?string
    {
        return match($paymentMethod) {
            'sha' => 'sha',
            'ncpwd' => 'ncpwd',
            'insurance_private' => 'insurance_private',
            'waiver' => 'waiver',
            default => null,
        };
    }

    /**
     * Determine routing based on payment method
     */
    protected function determineRouting(string $paymentMethod): array
    {
        return match($paymentMethod) {
            'cash', 'mpesa' => [
                'next_stage' => 'cashier',
                'message' => 'Re-routed to Cashier for direct payment',
            ],
            
            'sha' => [
                'next_stage' => 'billing_admin',
                'message' => 'Re-routed to Billing Admin for SHA verification',
            ],
            
            'ncpwd' => [
                'next_stage' => 'billing_admin',
                'message' => 'Re-routed to Billing Admin for NCPWD verification',
            ],
            
            'insurance_private' => [
                'next_stage' => 'billing_admin',
                'message' => 'Re-routed to Billing Admin for insurance verification',
            ],
            
            'waiver' => [
                'next_stage' => 'billing_admin',
                'message' => 'Re-routed to Billing Admin for waiver approval',
            ],
            
            'combination' => [
                'next_stage' => 'billing_admin',
                'message' => 'Re-routed to Billing Admin for split billing setup',
            ],
            
            default => [
                'next_stage' => 'cashier',
                'message' => 'Re-routed to Cashier',
            ],
        };
    }

    protected function getRedirectUrl(): string
    {
        $formSlug = $this->record->schema->slug ?? '';
        
        // Redirect to intake queue if this was an intake assessment
        if ($formSlug === 'intake-assessment') {
            if (class_exists('\App\Filament\Resources\IntakeQueueResource')) {
                return \App\Filament\Resources\IntakeQueueResource::getUrl('index');
            }
        }

        // Default: return to view page
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Assessment Updated';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('view_invoice')
                ->label('View Invoice')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->visible(function () {
                    $visit = $this->record->visit;
                    return $visit && $visit->invoices()->exists();
                })
                ->url(function () {
                    $invoice = $this->record->visit->invoices()->latest()->first();
                    
                    if (class_exists('\App\Filament\Resources\BillingResource')) {
                        return \App\Filament\Resources\BillingResource::getUrl('view', ['record' => $invoice->id]);
                    }
                    
                    return '#';
                })
                ->openUrlInNewTab(),

            \Filament\Actions\Action::make('force_reroute')
                ->label('Force Re-route')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Force Re-route to Payment Queue')
                ->modalDescription('This will re-calculate routing based on current payment method and update the invoice.')
                ->visible(fn() => $this->record->schema->slug === 'intake-assessment')
                ->action(function () {
                    $assessment = $this->record;
                    $visit = $assessment->visit;
                    $invoice = $visit->invoices()->latest()->first();

                    if ($invoice) {
                        $responseData = $assessment->response_data;
                        $this->updateExistingInvoice($invoice, $responseData, $visit);
                    } else {
                        $this->createNewInvoiceFromEdit($assessment, $visit, $assessment->response_data);
                    }
                }),

            \Filament\Actions\DeleteAction::make(),
            
            \Filament\Actions\ViewAction::make(),
        ];
    }
}