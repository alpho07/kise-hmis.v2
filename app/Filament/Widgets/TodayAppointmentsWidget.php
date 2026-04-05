<?php
namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\ServiceAvailability;
use App\Models\Visit;
use App\Services\NotificationService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Notifications\Notification;

class TodayAppointmentsWidget extends BaseWidget
{
    protected static ?string $heading = "Today's Appointments";
    protected static ?string $pollingInterval = '30s';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appointment::today()
                    ->whereHas('department', fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
                    ->with(['client', 'service', 'provider', 'department'])
                    ->orderBy('appointment_time')
            )
            ->columns([
                Tables\Columns\TextColumn::make('appointment_time')
                    ->label('Time')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service'),

                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Provider')
                    ->default('—'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray'    => 'scheduled',
                        'primary' => 'confirmed',
                        'success' => 'checked_in',
                        'danger'  => 'no_show',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_client')
                    ->label('View Client')
                    ->icon('heroicon-o-user-circle')
                    ->color('info')
                    ->url(fn (\App\Models\Appointment $record) => route('filament.admin.pages.client-profile-hub', [
                        'clientId' => $record->client_id,
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('check_in')
                    ->label('Check In')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->disabled(fn (Appointment $record) =>
                        !in_array($record->status, ['scheduled', 'confirmed']) ||
                        !ServiceAvailability::isDepartmentAvailable($record->department_id)
                    )
                    ->tooltip(fn (Appointment $record) =>
                        !ServiceAvailability::isDepartmentAvailable($record->department_id)
                            ? 'Department unavailable today'
                            : 'Check in client'
                    )
                    ->requiresConfirmation()
                    ->modalHeading(fn (Appointment $record) => "Check In: {$record->client->full_name}")
                    ->modalDescription(fn (Appointment $record) => "Service: {$record->service->name}. This will create a new visit and send to triage.")
                    ->action(function (Appointment $record) {
                        // 1. Create the Visit
                        $visit = Visit::create([
                            'client_id'      => $record->client_id,
                            'branch_id'      => auth()->user()->branch_id,
                            'is_appointment' => true,
                            'visit_type'     => 'appointment',
                            'triage_path'    => 'returning',
                            'check_in_time'  => now(),
                            'checked_in_by'  => auth()->id(),
                            'visit_date'     => today(),
                        ]);

                        // 2. Move to triage stage
                        $visit->moveToStage('triage');

                        // 3. Link appointment to visit
                        $record->update([
                            'status'        => 'checked_in',
                            'checked_in_at' => now(),
                            'checked_in_by' => auth()->id(),
                            'visit_id'      => $visit->id,
                            'branch_id'     => auth()->user()->branch_id,
                        ]);

                        // 4. Send mock SMS
                        app(NotificationService::class)->send(
                            $record->client,
                            'check_in_confirmation',
                            ['service' => $record->service->name, 'time' => now()->format('H:i')],
                            $record->id
                        );

                        Notification::make()
                            ->success()
                            ->title('Client Checked In')
                            ->body("{$record->client->full_name} sent to triage queue.")
                            ->send();
                    }),

                Tables\Actions\Action::make('no_show')
                    ->label('No Show')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Appointment $record) => in_array($record->status, ['scheduled', 'confirmed']))
                    ->requiresConfirmation()
                    ->action(fn (Appointment $record) => $record->markNoShow()),
            ]);
    }
}
