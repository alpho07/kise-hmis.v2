<?php
namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use App\Services\NotificationService;
use Filament\Resources\Pages\CreateRecord;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        // Populate branch_id from the department's branch
        if (!empty($data['department_id'])) {
            $dept = \App\Models\Department::find($data['department_id']);
            $data['branch_id'] = $dept?->branch_id;
        }
        // Remove virtual field before saving
        unset($data['send_sms']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $appointment = $this->record;
        $sendSms = $this->data['send_sms'] ?? false;

        if ($sendSms) {
            app(NotificationService::class)->send(
                $appointment->client,
                'appointment_reminder',
                [
                    'service' => $appointment->service->name,
                    'date'    => $appointment->appointment_date->toDateString(),
                    'time'    => $appointment->appointment_time->format('H:i'),
                ],
                $appointment->id
            );
            $appointment->update(['reminder_sent' => true, 'reminder_sent_at' => now()]);
        }
    }
}
