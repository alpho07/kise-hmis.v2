<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ServiceAvailabilityResource\Pages;
use App\Models\ServiceAvailability;
use App\Models\Appointment;
use App\Services\NotificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ServiceAvailabilityResource extends Resource
{
    protected static ?string $model = ServiceAvailability::class;
    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Service Availability';
    protected static ?string $navigationGroup = 'System Settings';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole(['customer_care', 'admin', 'super_admin']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('department_id')
                ->relationship('department', 'name')
                ->required(),

            Forms\Components\DatePicker::make('date')
                ->required()
                ->default(today()),

            Forms\Components\Toggle::make('is_available')
                ->default(true)
                ->live()
                ->label('Available Today'),

            Forms\Components\Select::make('reason_code')
                ->options([
                    'staff_absent'           => 'Staff Absent',
                    'equipment_unavailable'  => 'Equipment Unavailable',
                    'public_holiday'         => 'Public Holiday',
                    'training'               => 'Training',
                    'other'                  => 'Other',
                ])
                ->visible(fn (\Filament\Forms\Get $get) => !$get('is_available'))
                ->required(fn (\Filament\Forms\Get $get) => !$get('is_available')),

            Forms\Components\Textarea::make('comment')
                ->visible(fn (\Filament\Forms\Get $get) => !$get('is_available'))
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('department.name')->label('Department')->searchable(),
                Tables\Columns\TextColumn::make('date')->date('d M Y')->sortable(),
                Tables\Columns\IconColumn::make('is_available')->boolean()->label('Available'),
                Tables\Columns\TextColumn::make('reason_code')
                    ->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),
                Tables\Columns\TextColumn::make('updatedBy.name')->label('Updated By'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d M H:i')->label('Last Updated'),
            ])
            ->filters([
                Tables\Filters\Filter::make('today')
                    ->query(fn ($q) => $q->whereDate('date', today()))
                    ->default(),
                Tables\Filters\TernaryFilter::make('is_available')->label('Availability'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (ServiceAvailability $record) {
                        if (!$record->is_available) {
                            static::notifyAffectedClients($record);
                        }
                    }),
            ]);
    }

    /**
     * After marking unavailable, collect affected appointments and send disruption SMS.
     */
    private static function notifyAffectedClients(ServiceAvailability $record): void
    {
        $appointments = Appointment::where('department_id', $record->department_id)
            ->whereDate('appointment_date', $record->date)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->with('client', 'service')
            ->get();

        if ($appointments->isEmpty()) return;

        $notifier = app(NotificationService::class);
        foreach ($appointments as $appt) {
            $notifier->send($appt->client, 'disruption_alert', [
                'service' => $appt->service->name,
                'date'    => $record->date->format('d M Y'),
                'reason'  => $record->reason_code ?? 'operational reasons',
            ], $appt->id);
        }

        Notification::make()
            ->warning()
            ->title('Disruption SMS Queued (Mock)')
            ->body($appointments->count() . ' clients notified (mock — check notification_logs).')
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServiceAvailabilities::route('/'),
            'create' => Pages\CreateServiceAvailability::route('/create'),
            'edit'   => Pages\EditServiceAvailability::route('/{record}/edit'),
        ];
    }
}
