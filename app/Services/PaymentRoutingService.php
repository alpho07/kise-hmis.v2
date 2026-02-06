<?php

namespace App\Services;

use App\Models\AssessmentFormResponse;
use App\Models\Visit;
use App\Models\Service;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ServiceBooking;
use App\Models\Queue;
use App\Models\QueueEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment Routing Service - ENHANCED WITH SPONSOR/CLIENT SPLIT
 * 
 * Handles complete workflow from intake to service queues
 */
class PaymentRoutingService
{
    /**
     * Route client to appropriate queue after intake completion
     */
    public function routeAfterIntake(AssessmentFormResponse $intakeResponse): array
    {
        try {
            DB::beginTransaction();

            $visit = $intakeResponse->visit;
            $responseData = $intakeResponse->response_data;
            $paymentMethod = $responseData['payment_method'] ?? 'cash';

            Log::info('Payment Routing Started', [
                'intake_response_id' => $intakeResponse->id,
                'visit_id' => $visit->id,
                'payment_method' => $paymentMethod,
            ]);

            // Create invoice/billing items with sponsor/client split
            $invoice = $this->createInvoice($visit, $responseData);

            // Determine routing destination
            $routing = $this->determineRouting($paymentMethod, $responseData);

            // Update visit stage
            $visit->update([
                'current_stage' => $routing['next_stage'],
                'current_stage_started_at' => now(),
            ]);

            // Create service bookings
            $this->createServiceBookings($visit, $responseData, $invoice);

            DB::commit();

            Log::info('Payment Routing Completed', [
                'queue' => $routing['queue'],
                'invoice_id' => $invoice?->id,
                'next_stage' => $routing['next_stage'],
                'total_amount' => $invoice->total_amount,
                'sponsor_amount' => $invoice->total_sponsor_amount,
                'client_amount' => $invoice->total_client_amount,
            ]);

            return [
                'success' => true,
                'queue' => $routing['queue'],
                'next_stage' => $routing['next_stage'],
                'invoice_id' => $invoice?->id,
                'message' => $routing['message'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment Routing Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Determine routing based on payment method
     */
    protected function determineRouting(string $paymentMethod, array $responseData): array
    {
        return match ($paymentMethod) {
            'cash', 'mpesa' => [
                'queue' => 'cashier',
                'next_stage' => 'cashier',
                'priority' => 'normal',
                'requires_verification' => false,
                'message' => 'Client routed to Cashier for direct payment',
            ],

            'sha' => [
                'queue' => 'billing_admin',
                'next_stage' => 'billing_admin',
                'priority' => 'normal',
                'requires_verification' => true,
                'verification_type' => 'sha_eligibility',
                'message' => 'Client routed to Billing Admin for SHA verification',
            ],

            'ncpwd' => [
                'queue' => 'billing_admin',
                'next_stage' => 'billing_admin',
                'priority' => 'normal',
                'requires_verification' => true,
                'verification_type' => 'ncpwd_eligibility',
                'message' => 'Client routed to Billing Admin for NCPWD verification',
            ],

            'insurance_private' => [
                'queue' => 'billing_admin',
                'next_stage' => 'billing_admin',
                'priority' => 'high',
                'requires_verification' => true,
                'requires_preauthorization' => true,
                'verification_type' => 'insurance_verification',
                'message' => 'Client routed to Billing Admin for insurance verification and pre-authorization',
            ],

            'waiver' => [
                'queue' => 'billing_admin',
                'next_stage' => 'billing_admin',
                'priority' => 'normal',
                'requires_approval' => true,
                'message' => 'Client routed to Billing Admin for waiver approval',
            ],

            'combination' => [
                'queue' => 'billing_admin',
                'next_stage' => 'billing_admin',
                'priority' => 'normal',
                'requires_verification' => true,
                'requires_split_billing' => true,
                'message' => 'Client routed to Billing Admin for split billing setup',
            ],

            default => [
                'queue' => 'cashier',
                'next_stage' => 'cashier',
                'priority' => 'normal',
                'requires_verification' => false,
                'message' => 'Default routing to Cashier',
            ],
        };
    }

    /**
     * ✅ FIXED: Create invoice with sponsor/client split support
     */
    protected function createInvoice(Visit $visit, array $responseData): Invoice
    {
        $selectedServices = $responseData['selected_services'] ?? [];
        $paymentMethod = $responseData['payment_method'] ?? 'cash';

        // Generate invoice number
        $branch = $visit->branch;
        $branchCode = $branch ? strtoupper(substr($branch->name, 0, 3)) : 'HQ';
        $year = now()->format('Y');
        $month = now()->format('m');
        $sequence = Invoice::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;

        $invoiceNumber = "{$branchCode}/INV/{$year}/{$month}/" . str_pad($sequence, 4, '0', STR_PAD_LEFT);

        // Create invoice
        $invoice = Invoice::create([
            'visit_id' => $visit->id,
            'client_id' => $visit->client_id,
            'branch_id' => $visit->branch_id,
            'invoice_number' => $invoiceNumber,
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'issued_by' => auth()->id(),
            'total_amount' => 0, // Will be calculated from items
            'payment_notes' => $responseData['payment_notes'] ?? null,
        ]);

        // Add insurance provider if applicable
        if (in_array($paymentMethod, ['sha', 'ncpwd', 'insurance_private', 'combination'])) {
            if (!empty($responseData['insurance_provider_name'])) {
                $provider = \App\Models\InsuranceProvider::firstOrCreate(
                    ['name' => $responseData['insurance_provider_name']],
                    [
                        'type' => $paymentMethod,
                        'code' => strtoupper(substr($paymentMethod, 0, 3)),
                        'is_active' => true,
                    ]
                );
                $invoice->update(['insurance_provider_id' => $provider->id]);
            }
        }

        // ✅ FIXED: Create invoice items with sponsor/client split
        $totalAmount = 0;
        $totalSponsorAmount = 0;
        $totalClientAmount = 0;

        foreach ($selectedServices as $serviceData) {
            $service = Service::find($serviceData['service_id']);
            if (!$service) continue;

            $quantity = $serviceData['quantity'] ?? 1;
            $baseCost = $service->base_price;

            // Calculate sponsor/client split
            $clientPays = $this->calculateClientCost($service, $paymentMethod);
            $sponsorPays = $baseCost - $clientPays;
            $sponsorPercentage = $baseCost > 0 ? ($sponsorPays / $baseCost) * 100 : 0;

            $item = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_id' => $service->id,
                'department_id' => $service->department_id,
                'description' => $service->name,
                'quantity' => $quantity,
                'unit_price' => $baseCost,                    // ✅ Full base price
                'subtotal' => $baseCost * $quantity,

                // ✅ NEW: Sponsor/Client Split
                'sponsor_type' => $this->mapPaymentMethodToSponsorType($paymentMethod),
                'sponsor_percentage' => $sponsorPercentage,
                'sponsor_amount' => $sponsorPays * $quantity,
                'client_amount' => $clientPays * $quantity,
                'client_payment_status' => 'pending',
                'sponsor_claim_status' => in_array($paymentMethod, ['sha', 'ncpwd', 'insurance_private'])
                    ? 'pending' : null,
            ]);

            $totalAmount += $item->subtotal;
            $totalSponsorAmount += $item->sponsor_amount;
            $totalClientAmount += $item->client_amount;
        }

        // ✅ FIXED: Update invoice with sponsor/client totals
        $invoice->update([
            'total_amount' => $totalAmount,
            'total_sponsor_amount' => $totalSponsorAmount,
            'total_client_amount' => $totalClientAmount,
            'has_sponsor' => $totalSponsorAmount > 0,
            'sponsor_claim_status' => $totalSponsorAmount > 0 ? 'pending' : null,
            'client_payment_status' => 'pending',
        ]);

        Log::info('Invoice created with sponsor/client split', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_amount' => $totalAmount,
            'sponsor_amount' => $totalSponsorAmount,
            'client_amount' => $totalClientAmount,
            'payment_method' => $paymentMethod,
        ]);

        return $invoice;
    }

    /**
     * Calculate what client pays based on payment method
     */
    protected function calculateClientCost(Service $service, string $paymentMethod): float
    {
        return match ($paymentMethod) {
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
     * ✅ NEW: Map payment method to sponsor type
     */
    protected function mapPaymentMethodToSponsorType(string $paymentMethod): ?string
    {
        return match ($paymentMethod) {
            'sha' => 'sha',
            'ncpwd' => 'ncpwd',
            'insurance_private' => 'insurance_private',
            'waiver' => 'waiver',
            default => null,
        };
    }

    /**
     * ✅ NEW: Calculate sponsor coverage percentage
     */
    protected function calculateSponsorPercentage(string $paymentMethod): float
    {
        return match ($paymentMethod) {
            'sha' => 80.0,        // SHA covers 80%
            'ncpwd' => 90.0,      // NCPWD covers 90%
            'waiver' => 100.0,    // Waiver covers 100%
            default => 0.0,       // Cash/M-PESA no coverage
        };
    }

    /**
     * Create service bookings (status=pending_payment)
     */
    protected function createServiceBookings(Visit $visit, array $responseData, Invoice $invoice): void
    {
        $selectedServices = $responseData['selected_services'] ?? [];

        foreach ($selectedServices as $index => $serviceData) {
            $service = Service::find($serviceData['service_id']);
            if (!$service) continue;

            // Get corresponding invoice item
            $invoiceItem = $invoice->items()->where('service_id', $service->id)->first();

            ServiceBooking::create([
                'visit_id' => $visit->id,
                'client_id' => $visit->client_id,
                'service_id' => $service->id,
                'department_id' => $service->department_id,
                'invoice_item_id' => $invoiceItem?->id,
                'booking_type' => $index === 0 ? 'primary' : 'secondary',
                'priority' => $serviceData['priority'] ?? 'routine',
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'booked_by' => auth()->id(),
                'booking_date' => now(),
                'notes' => $serviceData['notes'] ?? null,
            ]);
        }
    }

    /**
     * Create service queues after payment cleared
     * 
     * Called by:
     * 1. Cashier after processing cash/M-PESA payment
     * 2. Billing Admin after approving waiver (no payment needed)
     */
    public function createServiceQueues(Visit $visit): array
    {
        try {
            DB::beginTransaction();

            // Get all confirmed service bookings
            $bookings = ServiceBooking::where('visit_id', $visit->id)
                ->where('status', 'confirmed')
                ->get();

            $queuesCreated = 0;

            foreach ($bookings as $booking) {

                /**
                 * ===============================
                 * CREATE / GET QUEUE (DEBUGGED)
                 * ===============================
                 */
                $queue = Queue::firstOrCreate(
                    [
                        'department_id' => $booking->department_id,
                        'visit_id'      => $visit->id,
                    ],
                    [
                        'branch_id'      => $visit->branch_id,
                        'queue_name'     => $booking->department->name . ' Queue',
                        'status'         => 'active',
                        'max_capacity'   => $booking->department->queue_capacity ?? 100,
                        'current_number' => 0,
                    ]
                );

                // 🔍 DEBUG LOG
                Log::info('Queue firstOrCreate debug', [
                    'visit_id'             => $visit->id,
                    'department_id'        => $booking->department_id,
                    'exists'               => $queue->exists,
                    'was_recently_created' => $queue->wasRecentlyCreated ?? null,
                    'queue_id'             => $queue->id,
                    'attributes'           => $queue->getAttributes(),
                ]);

                // 🚨 HARD FAIL IF QUEUE WAS NOT SAVED
                if (! $queue->exists || ! $queue->id) {
                    throw new \Exception('Queue was not created or retrieved');
                }

                /**
                 * ===============================
                 * CREATE QUEUE ENTRY
                 * ===============================
                 */
                QueueEntry::create([
                    'queue_id'           => $queue->id,
                    'visit_id'           => $visit->id,
                    'client_id'          => $visit->client_id,
                    'service_booking_id' => $booking->id,
                    'service_id'         => $booking->service_id,
                    'department_id'      => $booking->department_id,
                    'queue_number'       => $queue->getNextNumber(),
                    'priority_level'     => match ($booking->priority) {
                        'urgent'  => 1,
                        'high'    => 2,
                        'routine' => 3,
                        default   => 3,
                    },
                    'status'     => 'waiting',
                    'joined_at'  => now(),
                ]);

                $queuesCreated++;
            }

            // Update visit stage
            $visit->update([
                'current_stage' => 'service',
            ]);

            DB::commit();
            

            Log::info('Service Queues Created', [
                'visit_id'        => $visit->id,
                'queues_created'  => $queuesCreated,
            ]);

            return [
                'success'         => true,
                'queues_created'  => $queuesCreated,
                'message'         => "Client added to {$queuesCreated} service queue(s)",
            ];
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Service Queue Creation Failed', [
                'visit_id' => $visit->id,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
