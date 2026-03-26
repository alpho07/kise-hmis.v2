<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServicePointDashboardResource\Pages;
use App\Models\QueueEntry;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ServicePointDashboardResource extends Resource
{
    protected static ?string $model = QueueEntry::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Service Point Dashboard';
    protected static ?string $modelLabel = 'Service Point';
    protected static ?string $pluralModelLabel = 'Service Point Queue';
    protected static ?string $navigationGroup = 'Service Delivery';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin', 'service_provider']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereDate('joined_at', today())
            ->where('status', 'waiting')
            ->when(
                auth()->user()->department_id ?? null,
                fn($q) => $q->where('department_id', auth()->user()->department_id)
            )
            ->with(['visit', 'client', 'service', 'department'])
            ->orderBy('priority_level', 'asc')
            ->orderBy('queue_number', 'asc');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('queue_number')
                    ->label('Queue #')
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state <= 3 => 'success',
                        $state <= 6 => 'warning',
                        default => 'gray',
                    })
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Large)
                    ->weight('bold')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client Name')
                    ->icon('heroicon-o-user')
                    ->weight('semibold')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->icon('heroicon-o-identification')
                    ->copyable()
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->icon('heroicon-o-briefcase')
                    ->badge()
                    ->color('info')
                    ->wrap(),

                Tables\Columns\TextColumn::make('visit.visit_number')
                    ->label('Visit #')
                    ->icon('heroicon-o-hashtag')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('priority_level')
                    ->label('Priority')
                    ->colors([
                        'danger' => 1,
                        'warning' => 2,
                        'success' => 3,
                    ])
                    ->formatStateUsing(fn($state) => match($state) {
                        1 => 'Urgent',
                        2 => 'High',
                        3 => 'Normal',
                        default => 'Normal',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('wait_time')
                    ->label('Waiting')
                    ->state(function (QueueEntry $record) {
                        $minutes = now()->diffInMinutes($record->joined_at);
                        if ($minutes < 60) {
                            return $minutes . ' min';
                        }
                        $hours = floor($minutes / 60);
                        $mins = $minutes % 60;
                        return "{$hours}h {$mins}m";
                    })
                    ->color(fn (QueueEntry $record) => 
                        now()->diffInMinutes($record->joined_at) > 30 ? 'danger' : 'success'
                    )
                    ->icon('heroicon-o-clock'),

                Tables\Columns\TextColumn::make('joined_at')
                    ->label('Joined Queue')
                    ->dateTime('H:i')
                    ->sortable()
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('priority_level')
                    ->options([
                        1 => 'Urgent',
                        2 => 'High Priority',
                        3 => 'Normal',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('verify_sign_in')
                    ->label('Verify & Sign In')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->modalHeading('Client Verification & Sign In')
                    ->form([
                        Forms\Components\Section::make('Client Information')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\Placeholder::make('client_info')
                                    ->label('')
                                    ->content(fn($record) => 
                                        "**Client:** {$record->client->full_name}\n" .
                                        "**UCI:** {$record->client->uci}\n" .
                                        "**Service:** {$record->service->name}\n" .
                                        "**Department:** {$record->department->name}\n" .
                                        "**Queue #:** {$record->queue_number}"
                                    ),
                            ]),

                        Forms\Components\Section::make('Verification Checklist')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('payment_verified')
                                            ->label('Payment Verified')
                                            ->default(true)
                                            ->disabled()
                                            ->helperText('Auto-verified'),

                                        Forms\Components\Toggle::make('client_present')
                                            ->label('Client Present')
                                            ->default(true)
                                            ->required(),
                                    ]),

                                Forms\Components\Toggle::make('service_available')
                                    ->label('Service Available Today?')
                                    ->default(true)
                                    ->live()
                                    ->required()
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Section::make('Service Unavailable')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->schema([
                                Forms\Components\Select::make('unavailability_reason')
                                    ->label('Reason for Unavailability')
                                    ->options([
                                        'provider_absent' => 'Provider Absent',
                                        'equipment_down' => 'Equipment Not Available',
                                        'full_schedule' => 'Schedule Full',
                                        'emergency' => 'Emergency/Urgent Case',
                                        'other' => 'Other',
                                    ])
                                    ->required(),

                                Forms\Components\DateTimePicker::make('rescheduled_to')
                                    ->label('Reschedule To')
                                    ->required()
                                    ->minDate(today()->addDay())
                                    ->native(false),

                                Forms\Components\Textarea::make('sensitization_notes')
                                    ->label('Client Sensitization Notes')
                                    ->placeholder('What was communicated to the client?')
                                    ->required()
                                    ->rows(3),
                            ])
                            ->visible(fn(Forms\Get $get) => !$get('service_available'))
                            ->collapsed(false),

                        Forms\Components\Section::make('Provider Assignment')
                            ->icon('heroicon-o-user-plus')
                            ->schema([
                                Forms\Components\Select::make('service_provider_id')
                                    ->label('Assign to Provider')
                                    ->options(function ($record) {
                                        // Get providers from the same department
                                        return User::query()
                                            ->where('department_id', $record->department_id)
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->helperText('Select provider from this department'),

                                Forms\Components\TextInput::make('room_assigned')
                                    ->label('Room/Location')
                                    ->placeholder('e.g., Consultation Room 1, Therapy Room A')
                                    ->helperText('Physical location where service will be delivered'),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Additional Notes')
                                    ->placeholder('Any special instructions or observations')
                                    ->rows(2),
                            ])
                            ->visible(fn(Forms\Get $get) => $get('service_available'))
                            ->collapsed(false),
                    ])
                    ->action(function ($record, array $data) {
                        if ($data['service_available']) {
                            // Service available - mark as ready
                            $record->update([
                                'service_provider_id' => $data['service_provider_id'],
                                'room_assigned' => $data['room_assigned'] ?? null,
                                'status' => 'ready',
                                'verified_at' => now(),
                                'verified_by' => auth()->id(),
                                'notes' => $data['notes'] ?? null,
                            ]);

                            $providerName = User::find($data['service_provider_id'])->name ?? 'Provider';

                            Notification::make()
                                ->success()
                                ->title('Client Signed In Successfully')
                                ->body("{$record->client->full_name} is ready for service. Assigned to {$providerName}.")
                                ->send();
                        } else {
                            // Service unavailable - reschedule
                            $record->update([
                                'status' => 'rescheduled',
                                'notes' => "RESCHEDULED by " . auth()->user()->name . " on " . now()->format('Y-m-d H:i') . "\n" .
                                          "Reason: " . ($data['unavailability_reason'] ?? 'Not specified') . "\n" .
                                          "Rescheduled to: " . ($data['rescheduled_to'] ?? 'Not specified') . "\n" .
                                          "Client Sensitization: " . ($data['sensitization_notes'] ?? 'None'),
                            ]);

                            Notification::make()
                                ->warning()
                                ->title('Service Unavailable - Client Rescheduled')
                                ->body("Client has been informed and rescheduled to " . 
                                      \Carbon\Carbon::parse($data['rescheduled_to'])->format('d/m/Y H:i'))
                                ->send();
                        }
                    })
                    ->modalWidth('3xl'),

                Tables\Actions\ViewAction::make()
                    ->label('Details')
                    ->color('gray'),
            ])
            ->bulkActions([])
            ->poll('10s')
            ->defaultSort('priority_level', 'asc')
            ->emptyStateHeading('No Clients Waiting')
            ->emptyStateDescription('All clients have been verified and signed in.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServicePointDashboards::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'waiting')
            ->where('department_id', auth()->user()->department_id ?? null)
            ->whereDate('joined_at', today())
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::where('status', 'waiting')
            ->where('department_id', auth()->user()->department_id ?? null)
            ->whereDate('joined_at', today())
            ->count();

        return match(true) {
            $count > 10 => 'danger',
            $count > 5 => 'warning',
            $count > 0 => 'success',
            default => 'gray',
        };
    }
}