<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Resources\VisitResource;
use App\Models\VisitStage;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateVisit extends CreateRecord
{
    protected static string $resource = VisitResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure branch_id is set
        if (empty($data['branch_id'])) {
            $data['branch_id'] = auth()->user()->branch_id;
        }
        
        // Set check-in user
        $data['checked_in_by'] = auth()->id();

        
        $data['visit_date'] = date('Y-m-d');
        
        // Visit number is auto-generated in model boot
        // check_in_time is auto-set in model boot
        // current_stage is auto-set to 'reception' in model boot
        // status is auto-set to 'in_progress' in model boot
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Create initial visit stage (reception)
        VisitStage::create([
            'visit_id' => $this->record->id,
            'stage' => 'reception',
            'started_at' => now(),
            'status' => 'completed', // Reception is completed upon check-in
            'completed_at' => now(),
            'handled_by' => auth()->id(),
            'duration_minutes' => 0, // Instant
            'notes' => 'Client checked in at reception',
        ]);
        
        // Move to triage stage
        $triagePath = $this->record->triage_path ?? 'standard';
        
        $this->record->moveToStage('triage');
        
        // Log activity
        activity()
            ->performedOn($this->record)
            ->causedBy(auth()->user())
            ->log("Visit {$this->record->visit_number} created for client {$this->record->client->full_name}");
        
        // Send notification based on service availability
        if (!$this->record->service_available) {
            Notification::make()
                ->warning()
                ->title('Service Unavailable')
                ->body("Service not available: {$this->record->unavailability_reason}. Client may need to be rescheduled.")
                ->persistent()
                ->send();
        } elseif ($this->record->is_emergency) {
            Notification::make()
                ->danger()
                ->title('Emergency Visit Created')
                ->body("URGENT: Emergency visit {$this->record->visit_number} for {$this->record->client->full_name}")
                ->persistent()
                ->sendToDatabase(auth()->user())
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title('Client Checked In')
                ->body("Visit {$this->record->visit_number} created. Client routed to {$triagePath} triage.")
                ->send();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Client checked in successfully';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Client Checked In')
            ->body("Visit {$this->record->visit_number} - Next: Triage")
            ->icon('heroicon-o-check-circle');
    }
}