<?php

namespace App\Filament\Resources\TriageResource\Pages;

use App\Filament\Resources\TriageResource;
use App\Models\Visit;
use App\Models\TriageRedFlag;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateTriage extends CreateRecord
{
    protected static string $resource = TriageResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set triaged_by
        $data['triaged_by'] = auth()->id();
        
        // Ensure client_id is set from visit
        if (!empty($data['visit_id'])) {
            $visit = Visit::find($data['visit_id']);
            if ($visit) {
                $data['client_id'] = $visit->client_id;
            }
        }
        
        // Get red flags from form data
        $redFlags = [];
        $redFlagFields = [
            'red_flag_active_bleeding' => 'Active Bleeding',
            'red_flag_severe_pain' => 'Severe Pain',
            'red_flag_seizure' => 'Seizure/Convulsions',
            'red_flag_altered_consciousness' => 'Altered Consciousness',
            'red_flag_respiratory_distress' => 'Respiratory Distress',
            'red_flag_low_oxygen' => 'SpO₂ < 92%',
            'red_flag_fever_convulsions' => 'Fever with Convulsions',
            'red_flag_suicidal_ideation' => 'Suicidal Ideation',
            'red_flag_violent_behavior' => 'Violent Behavior',
            'red_flag_suspected_abuse' => 'Suspected Abuse/Neglect',
        ];
        
        foreach ($redFlagFields as $field => $label) {
            if (!empty($data[$field])) {
                $redFlags[] = [
                    'flag_type' => $field,
                    'description' => $label,
                    'severity' => 'high',
                ];
            }
            // Remove from main data (not in Triage fillable)
            unset($data[$field]);
        }
        
        // Store red flags temporarily
        $this->redFlagsToCreate = $redFlags;
        
        // Remove safeguarding fields (not in Triage fillable)
        unset($data['safeguarding_risk']);
        unset($data['safeguarding_notes']);
        unset($data['child_accompanied']);
        unset($data['computed_risk']);
        unset($data['client_age']);
        unset($data['client_dob']);
        unset($data['bmi_category']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            DB::beginTransaction();
            
            $triage = $this->record;
            $visit = Visit::with('client')->find($triage->visit_id);
            
            if (!$visit) {
                throw new \Exception("Visit not found for triage ID: {$triage->id}");
            }
            
            // Log the current state
            Log::info('Triage Created', [
                'triage_id' => $triage->id,
                'visit_id' => $visit->id,
                'visit_number' => $visit->visit_number,
                'triage_status' => $triage->triage_status,
                'risk_level' => $triage->risk_level,
                'current_stage_before' => $visit->current_stage,
            ]);
            
            // Create red flag records
            if (!empty($this->redFlagsToCreate)) {
                foreach ($this->redFlagsToCreate as $flagData) {
                    TriageRedFlag::create([
                        'triage_id' => $triage->id,
                        //'flag_category' => $flagData['flag_category'],
                        'description' => $flagData['description'],
                        'severity' => $flagData['severity'],
                    ]);
                }
            }
            
            // Complete triage stage in visit
            $visit->completeStage();
            
            // Determine next step based on triage decision
            $nextStage = $this->determineNextStage($triage, $visit);
            
            // Move visit to next stage
            $visit->moveToStage($nextStage);
            
            // Refresh visit to get updated stage
            $visit->refresh();
            
            // Log after stage transition
            Log::info('Triage Stage Transition', [
                'triage_id' => $triage->id,
                'visit_id' => $visit->id,
                'current_stage_after' => $visit->current_stage,
                'next_stage_determined' => $nextStage,
                'triage_status' => $triage->triage_status,
            ]);
            
            // Verify the transition happened
            if ($visit->current_stage !== $nextStage) {
                Log::error('Stage transition failed', [
                    'expected_stage' => $nextStage,
                    'actual_stage' => $visit->current_stage,
                    'visit_id' => $visit->id,
                ]);
                
                throw new \Exception("Failed to move visit to stage: {$nextStage}");
            }
            
            // Log activity
            activity()
                ->performedOn($triage)
                ->causedBy(auth()->user())
                ->log("Triage completed for visit {$visit->visit_number} - Risk: {$triage->risk_level}, Status: {$triage->triage_status}, Next: {$nextStage}");
            
            DB::commit();
            
            // Send appropriate notification
            $this->sendNotification($triage, $visit, $nextStage);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Triage Creation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            Notification::make()
                ->danger()
                ->title('Triage Save Error')
                ->body('Failed to complete triage workflow: ' . $e->getMessage())
                ->persistent()
                ->send();
            
            throw $e;
        }
    }

    /**
     * Determine next stage based on triage outcome
     */
    protected function determineNextStage($triage, $visit): string
    {
        // Crisis Protocol
        if ($triage->triage_status === 'crisis') {
            return 'crisis_management';
        }
        
        // Medical Hold
        if ($triage->triage_status === 'medical_hold') {
            return 'medical_hold';
        }
        
        // Route by client_type — set at reception sign-in
        // new / old_new → full intake assessment
        // returning     → skip intake, go straight to billing
        $clientType = $visit->client?->client_type ?? 'new';

        if ($clientType === 'returning') {
            Log::info('Routing to Billing', [
                'visit_id'    => $visit->id,
                'reason'      => 'Returning client — skipping intake',
                'client_type' => $clientType,
            ]);
            return 'billing';
        }

        Log::info('Routing to Intake', [
            'visit_id'    => $visit->id,
            'reason'      => 'New or Old-New client — requires intake assessment',
            'client_type' => $clientType,
        ]);
        return 'intake';
    }

    /**
     * Send appropriate notification
     */
    protected function sendNotification($triage, $visit, $nextStage): void
    {
        $client = $visit->client;
        
        if ($triage->triage_status === 'crisis') {
            Notification::make()
                ->danger()
                ->title('🚨 CRISIS PROTOCOL ACTIVATED')
                ->body("Visit {$visit->visit_number} - {$client->full_name}: HIGH RISK - Crisis team notified")
                ->persistent()
                ->sendToDatabase(auth()->user())
                ->send();
        } elseif ($triage->triage_status === 'medical_hold') {
            Notification::make()
                ->warning()
                ->title('⏸️ Medical Hold')
                ->body("Visit {$visit->visit_number} - {$client->full_name}: Requires medical clearance")
                ->persistent()
                ->send();
        } else {
            $nextStepLabel = match($nextStage) {
                'intake' => 'Intake Assessment',
                'billing' => 'Billing',
                'service_booking' => 'Service Booking',
                default => ucfirst(str_replace('_', ' ', $nextStage)),
            };
            
            Notification::make()
                ->success()
                ->title('✅ Triage Completed')
                ->body("Visit {$visit->visit_number} cleared. Risk: {$triage->risk_level}. Next: {$nextStepLabel}")
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view_visit')
                        ->button()
                        ->label('View Visit')
                        ->url(route('filament.admin.resources.visits.view', $visit->id)),
                ])
                ->duration(10000)
                ->send();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Triage completed successfully';
    }

    protected function getCreatedNotification(): ?Notification
    {
        // Return null to prevent default notification (we handle it in afterCreate)
        return null;
    }
}