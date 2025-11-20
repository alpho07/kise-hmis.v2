<?php

namespace App\Filament\Resources\ReceptionResource\Pages;

use App\Filament\Resources\ReceptionResource;
use App\Models\Visit;
use App\Models\Client;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateReception extends CreateRecord
{
    protected static string $resource = ReceptionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set branch
        $data['branch_id'] = auth()->user()->branch_id;
        
        // Set checked in by
        $data['checked_in_by'] = auth()->id();
        
        // Set visit date
        $data['visit_date'] = today();
        
        // Set check-in time
        $data['check_in_time'] = now();
        
        // Set initial stage
        $data['current_stage'] = 'reception';
        
        // Set status
        $data['status'] = 'in_progress';
        
        // Auto-generate visit number if not set
        if (empty($data['visit_number'])) {
            $data['visit_number'] = $this->generateVisitNumber();
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $visit = $this->record;
        $client = Client::find($visit->client_id);
        
        // Create initial visit stage
        $visit->stages()->create([
            'stage' => 'reception',
            'started_at' => now(),
            'status' => 'in_progress',
        ]);
        
        // Update client's last visit date
        $client->update(['last_visit_date' => today()]);
        
        // Log activity
        activity()
            ->performedOn($visit)
            ->causedBy(auth()->user())
            ->log("Client checked in at reception - Visit {$visit->visit_number}");
        
        // Send notification based on service availability
        if ($visit->service_available === 'no') {
            Notification::make()
                ->warning()
                ->title('Visit Deferred')
                ->body("Visit {$visit->visit_number} - {$client->full_name}: Service unavailable. Client informed of next steps.")
                ->duration(10000)
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title('Check-In Successful')
                ->body("Visit {$visit->visit_number} - {$client->full_name}: Ready for triage")
                ->actions([
                    \Filament\Notifications\Actions\Action::make('send_to_triage')
                        ->button()
                        ->label('Send to Triage Now')
                        ->color('success')
                        ->url(fn () => route('filament.admin.resources.receptions.index')),
                ])
                ->duration(15000)
                ->send();
        }
    }

    protected function generateVisitNumber(): string
    {
        $date = now()->format('Ymd');
        $count = Visit::whereDate('created_at', today())->count() + 1;
        return 'VST-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Client checked in successfully';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // We handle notifications in afterCreate()
    }
}