<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Visit;
use App\Models\ServiceBooking;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class PaymentProcessingService
{
    /**
     * Process payment and create queue entries with triage verification
     * 
     * This is the CRITICAL safety check that prevents unscreened clients
     * from accessing services
     */
    public function processPaymentAndCreateQueues(Payment $payment): array
    {
        $results = [
            'success' => false,
            'message' => '',
            'queues_created' => 0,
            'blocked_reason' => null,
        ];

        DB::transaction(function () use ($payment, &$results) {
            $invoice = $payment->invoice;
            $visit = $invoice->visit;

            // ================================================================
            // STEP 1: CRITICAL TRIAGE VERIFICATION
            // ================================================================
            
            // Check if triage exists
            if (!$visit->triage()->exists()) {
                $this->handleTriageRequired($visit);
                $results['blocked_reason'] = 'no_triage';
                $results['message'] = 'Triage assessment required before service delivery.';
                return;
            }

            // Check if triage is valid (not expired)
            if (!$visit->hasValidTriage()) {
                $this->handleTriageExpired($visit);
                $results['blocked_reason'] = 'triage_expired';
                $results['message'] = 'Triage has expired. Reassessment required.';
                return;
            }

            // Check for medical hold
            if ($visit->hasMedicalHold() || $visit->hasActiveMedicalHold()) {
                $this->handleMedicalHold($visit);
                $results['blocked_reason'] = 'medical_hold';
                $results['message'] = 'Medical hold active. Clearance required before service delivery.';
                return;
            }

            // Check for crisis protocol
            if ($visit->hasCrisisProtocol()) {
                $this->handleCrisisProtocol($visit);
                $results['blocked_reason'] = 'crisis_protocol';
                $results['message'] = 'Crisis protocol active. Immediate intervention required.';
                return;
            }

            // ================================================================
            // STEP 2: ALL CHECKS PASSED - PROCEED WITH SERVICE ACCESS
            // ================================================================

            // Mark invoice as paid
            $invoice->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'paid_by' => auth()->id(),
            ]);

            // Move visit to service point stage
            $visit->moveToStage('service_point');

            // Get triage priority for queue ordering
            $triagePriority = $visit->getTriagePriority();

            // Create queue entries for each service booking
            $queuesCreated = 0;
            foreach ($invoice->items as $item) {
                if ($item->serviceBooking && $item->serviceBooking->isReadyForQueue()) {
                    $queueEntry = $item->serviceBooking->createQueueEntry();
                    
                    // Set priority from triage
                    $queueEntry->update([
                        'priority_level' => $triagePriority,
                        'arrived_at' => now(),
                        'waiting_started_at' => now(),
                    ]);
                    
                    $queuesCreated++;
                }
            }

            $results['success'] = true;
            $results['queues_created'] = $queuesCreated;
            $results['message'] = "Payment processed. {$queuesCreated} service(s) added to queue.";

            // Send success notification
            Notification::make()
                ->success()
                ->title('Payment Processed Successfully')
                ->body("Client cleared for {$queuesCreated} service(s). Priority: " . ucfirst($triagePriority))
                ->send();
        });

        return $results;
    }

    /**
     * Handle missing triage
     */
    private function handleTriageRequired(Visit $visit): void
    {
        $visit->moveToStage('triage');

        Notification::make()
            ->danger()
            ->title('⚠️ TRIAGE REQUIRED')
            ->body('Client must complete triage assessment before service delivery. Visit redirected to triage.')
            ->persistent()
            ->send();
    }

    /**
     * Handle expired triage
     */
    private function handleTriageExpired(Visit $visit): void
    {
        $visit->moveToStage('triage');

        Notification::make()
            ->warning()
            ->title('⏰ Triage Expired')
            ->body('Previous triage has expired. Reassessment required before service delivery.')
            ->persistent()
            ->send();
    }

    /**
     * Handle medical hold
     */
    private function handleMedicalHold(Visit $visit): void
    {
        Notification::make()
            ->danger()
            ->title('⏸️ MEDICAL HOLD')
            ->body('Client has active medical hold. Cannot proceed to services until cleared by medical staff.')
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('view_hold')
                    ->button()
                    ->url(route('filament.admin.resources.medical-holds.index', [
                        'tableFilters[visit_id][value]' => $visit->id
                    ]))
            ])
            ->send();

        // Log this attempt for audit
        activity()
            ->performedOn($visit)
            ->causedBy(auth()->user())
            ->withProperties([
                'action' => 'blocked_service_access',
                'reason' => 'medical_hold',
                'triage_status' => $visit->currentTriage()?->clearance_status
            ])
            ->log('Service access blocked due to medical hold');
    }

    /**
     * Handle crisis protocol
     */
    private function handleCrisisProtocol(Visit $visit): void
    {
        Notification::make()
            ->danger()
            ->title('🚨 CRISIS PROTOCOL ACTIVE')
            ->body('Client under crisis protocol. All service access blocked. Immediate intervention required.')
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('view_crisis')
                    ->button()
                    ->color('danger')
                    ->url(route('filament.admin.resources.crisis-incidents.index', [
                        'tableFilters[visit_id][value]' => $visit->id
                    ]))
            ])
            ->send();

        // Log this attempt for audit
        activity()
            ->performedOn($visit)
            ->causedBy(auth()->user())
            ->withProperties([
                'action' => 'blocked_service_access',
                'reason' => 'crisis_protocol',
                'triage_status' => $visit->currentTriage()?->clearance_status,
                'risk_flag' => $visit->currentTriage()?->risk_flag
            ])
            ->log('Service access blocked due to crisis protocol');

        // TODO: Trigger immediate crisis team notification
        // This will be implemented in Phase 2
    }

    /**
     * Check if client can proceed to service (public method for use in forms)
     */
    public static function canProceedToService(Visit $visit): array
    {
        $canProceed = true;
        $reason = null;
        $message = null;

        if (!$visit->triage()->exists()) {
            $canProceed = false;
            $reason = 'no_triage';
            $message = '⚠️ Triage assessment required';
        } elseif (!$visit->hasValidTriage()) {
            $canProceed = false;
            $reason = 'triage_expired';
            $message = '⏰ Triage expired - reassessment required';
        } elseif ($visit->hasMedicalHold() || $visit->hasActiveMedicalHold()) {
            $canProceed = false;
            $reason = 'medical_hold';
            $message = '⏸️ Medical hold active';
        } elseif ($visit->hasCrisisProtocol()) {
            $canProceed = false;
            $reason = 'crisis_protocol';
            $message = '🚨 Crisis protocol active';
        }

        return [
            'can_proceed' => $canProceed,
            'reason' => $reason,
            'message' => $message,
        ];
    }
}