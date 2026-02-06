<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceQueueResource\Pages;
use App\Models\QueueEntry;
use App\Models\ServiceBooking;
use App\Models\AssessmentFormSchema;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class ServiceQueueResource extends Resource
{
    protected static ?string $model = QueueEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationLabel = 'Service Queue';
    protected static ?string $modelLabel = 'Queue Entry';
    protected static ?string $pluralModelLabel = 'Service Queue';
    protected static ?string $navigationGroup = 'Service Delivery';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('status', ['ready', 'in_service'])
          //  ->where('department_id', auth()->user()->department_id) // Department-scoped
            ->with([
                'client',
                'visit',
                'visit.serviceBookings.service.department',
                'visit.intakeAssessment',
                'visit.triage',
                'service',
                'service.department',
                'department',
                'serviceProvider',
                'serviceBooking',
            ])
            ->orderBy('priority_level', 'asc')
            ->orderBy('queue_number', 'asc');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Queue Information')
                    ->schema([
                        Forms\Components\TextInput::make('queue_number')
                            ->disabled(),

                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'first_name')
                            ->disabled(),

                        Forms\Components\Select::make('service_id')
                            ->relationship('service', 'name')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'ready' => 'Ready',
                                'in_service' => 'In Service',
                            ])
                            ->required(),

                        Forms\Components\Select::make('priority_level')
                            ->options([
                                1 => 'Urgent',
                                2 => 'High',
                                3 => 'Normal',
                            ])
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Service Provider')
                    ->schema([
                        Forms\Components\Select::make('service_provider_id')
                            ->relationship('serviceProvider', 'name')
                            ->searchable(),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('queue_number')
                    ->label('Queue #')
                    ->badge()
                    ->color('primary')
                    ->size('lg')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (QueueEntry $record) => $record->client->phone ?? ''),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('priority_level')
                    ->label('Priority')
                    ->colors([
                        'danger' => 1,
                        'warning' => 2,
                        'success' => 3,
                    ])
                    ->formatStateUsing(fn ( $state): string => match ($state) {
                        1 => 'Urgent',
                        2 => 'High',
                        3 => 'Normal',
                        default => 'Unknown',
                    })
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'ready',
                        'primary' => 'in_service',
                        'success' => 'completed',
                    ])
                    ->icons([
                        'heroicon-o-check-circle' => 'ready',
                        'heroicon-o-play' => 'in_service',
                        'heroicon-o-check-badge' => 'completed',
                    ]),

                Tables\Columns\TextColumn::make('serviceProvider.name')
                    ->label('Provider')
                    ->default('Not assigned')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('wait_time')
                    ->label('Wait Time')
                    ->state(function (QueueEntry $record) {
                        $minutes = now()->diffInMinutes($record->joined_at);
                        if ($minutes < 60) {
                            return $minutes . ' min';
                        }
                        $hours = floor($minutes / 60);
                        $mins = $minutes % 60;
                        return $hours . 'h ' . $mins . 'm';
                    })
                    ->badge()
                    ->color(fn (QueueEntry $record) => match(true) {
                        now()->diffInMinutes($record->joined_at) > 60 => 'danger',
                        now()->diffInMinutes($record->joined_at) > 30 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
            ])
            ->defaultSort('queue_number', 'asc')
            ->poll('5s')
            ->actions([
                // ========================================
                // 👤 VIEW CLIENT PROFILE HUB (NEW!)
                // ========================================
                Tables\Actions\Action::make('view_client_profile')
                    ->label('Client Profile')
                    ->icon('heroicon-o-user-circle')
                    ->color('info')
                    ->url(fn (QueueEntry $record): string => 
                        route('filament.admin.pages.client-profile-hub', [
                            'clientId' => $record->client_id,
                            'visitId' => $record->visit_id,
                        ])
                    )
                    ->openUrlInNewTab()
                    ->tooltip('Open comprehensive client profile'),

                // ========================================
                // 🎬 START SERVICE
                // ========================================
                Tables\Actions\Action::make('start_service')
                    ->label('Start Service')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (QueueEntry $record) => $record->status === 'ready')
                    ->requiresConfirmation()
                    ->modalHeading(fn (QueueEntry $record) => "Start Service for Queue #{$record->queue_number}")
                    ->modalDescription('Are you ready to begin serving this client?')
                    ->form([
                        Forms\Components\Select::make('service_provider_id')
                            ->label('Assign Provider')
                            ->relationship('serviceProvider', 'name')
                            ->searchable()
                            ->preload()
                            ->default(auth()->id())
                            ->required()
                            ->helperText('Select the provider who will deliver this service'),
                    ])
                    ->action(function (QueueEntry $record, array $data) {
                        $record->update([
                            'status' => 'in_service',
                            'service_provider_id' => $data['service_provider_id'],
                            'serving_started_at' => now(),
                        ]);

                        // Also update the service booking status
                        $record->serviceBooking?->update([
                            'service_status' => 'in_progress',
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Service Started')
                            ->body("Queue #{$record->queue_number} is now being served.")
                            ->send();
                    }),

                // ========================================
                // ✅ COMPLETE SERVICE
                // ========================================
                Tables\Actions\Action::make('complete_service')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (QueueEntry $record) => $record->status === 'in_service')
                    ->requiresConfirmation()
                    ->modalHeading(fn (QueueEntry $record) => "Complete Service for Queue #{$record->queue_number}")
                    ->modalDescription('Mark this service as completed?')
                    ->form([
                        Forms\Components\Textarea::make('completion_notes')
                            ->label('Completion Notes')
                            ->rows(3)
                            ->placeholder('Enter any notes about the service delivery...'),

                        Forms\Components\TextInput::make('actual_duration')
                            ->label('Actual Duration (minutes)')
                            ->numeric()
                            ->default(function (QueueEntry $record) {
                                if ($record->serving_started_at) {
                                    return now()->diffInMinutes($record->serving_started_at);
                                }
                                return null;
                            }),
                    ])
                    ->action(function (QueueEntry $record, array $data) {
                        $record->update([
                            'status' => 'completed',
                            'serving_completed_at' => now(),
                            'notes' => ($record->notes ?? '') . "\n\n" . ($data['completion_notes'] ?? ''),
                        ]);

                        // Update service booking
                        if ($record->serviceBooking) {
                            $record->serviceBooking->update([
                                'service_status' => 'completed',
                                'actual_duration' => $data['actual_duration'] ?? null,
                                'notes' => ($record->serviceBooking->notes ?? '') . "\n\n" . ($data['completion_notes'] ?? ''),
                            ]);
                        }

                        Notification::make()
                            ->success()
                            ->title('Service Completed')
                            ->body("Queue #{$record->queue_number} has been completed.")
                            ->send();
                    }),

                // ========================================
                // 🔄 CALL NEXT (for ready status)
                // ========================================
                Tables\Actions\Action::make('call_next')
                    ->label('Call Client')
                    ->icon('heroicon-o-phone')
                    ->color('warning')
                    ->visible(fn (QueueEntry $record) => $record->status === 'ready')
                    ->action(function (QueueEntry $record) {
                        Notification::make()
                            ->success()
                            ->title('Client Called')
                            ->body("Queue #{$record->queue_number} - {$record->client->full_name} has been called.")
                            ->send();
                    }),

                // ========================================
                // 📋 VIEW DETAILS
                // ========================================
                Tables\Actions\ViewAction::make()
                    ->label('Details')
                    ->modalWidth('7xl'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // ========================================
                // 👤 CLIENT INFORMATION
                // ========================================
                Infolists\Components\Section::make('Client Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('client.uci')
                                    ->label('UCI')
                                    ->badge()
                                    ->color('info')
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('client.full_name')
                                    ->label('Full Name')
                                    ->size('lg')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('client.age')
                                    ->label('Age')
                                    ->state(fn($record) => $record->client->date_of_birth?->age . ' years'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('client.phone')
                                    ->icon('heroicon-o-phone'),

                                Infolists\Components\TextEntry::make('client.email')
                                    ->icon('heroicon-o-envelope'),

                                Infolists\Components\TextEntry::make('client.gender')
                                    ->formatStateUsing(fn($state) => ucfirst($state ?? 'Not specified')),
                            ]),
                    ])
                    ->icon('heroicon-o-user')
                    ->collapsible(),

                // ========================================
                // 🏥 VISIT INFORMATION
                // ========================================
                Infolists\Components\Section::make('Visit Information')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('visit.visit_number')
                                    ->label('Visit Number')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('visit.visit_type')
                                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state ?? 'walk_in')))
                                    ->badge(),

                                Infolists\Components\TextEntry::make('visit.current_stage')
                                    ->badge()
                                    ->color('warning'),

                                Infolists\Components\TextEntry::make('visit.check_in_time')
                                    ->dateTime(),
                            ]),
                    ])
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsible(),

                // ========================================
                // 💉 TRIAGE INFORMATION
                // ========================================
                Infolists\Components\Section::make('Triage Assessment')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('visit.triage.vital_signs_bp')
                                    ->label('Blood Pressure')
                                    ->default('N/A')
                                    ->icon('heroicon-o-heart'),

                                Infolists\Components\TextEntry::make('visit.triage.vital_signs_temperature')
                                    ->label('Temperature')
                                    ->default('N/A')
                                    ->suffix('°C')
                                    ->icon('heroicon-o-fire'),

                                Infolists\Components\TextEntry::make('visit.triage.vital_signs_pulse')
                                    ->label('Pulse')
                                    ->default('N/A')
                                    ->suffix(' bpm')
                                    ->icon('heroicon-o-heart'),

                                Infolists\Components\TextEntry::make('visit.triage.vital_signs_weight')
                                    ->label('Weight')
                                    ->default('N/A')
                                    ->suffix(' kg')
                                    ->icon('heroicon-o-scale'),
                            ]),

                        Infolists\Components\TextEntry::make('visit.triage.chief_complaint')
                            ->label('Chief Complaint')
                            ->default('No complaint recorded')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('visit.triage.red_flags')
                            ->label('Red Flags')
                            ->badge()
                            ->color('danger')
                            ->visible(fn($record) => $record->visit?->triage?->red_flags),
                    ])
                    ->visible(fn($record) => $record->visit?->triage)
                    ->collapsible()
                    ->collapsed(),

                // ========================================
                // 📝 SERVICES REQUESTED (WITH ASSESSMENT FORMS)
                // ========================================
                Infolists\Components\Section::make('Services Requested for This Visit')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('visit.serviceBookings')
                            ->label('')
                            ->schema([
                                Infolists\Components\Grid::make(4)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('service.name')
                                            ->label('Service')
                                            ->weight('bold')
                                            ->icon('heroicon-o-clipboard-document-check'),

                                        Infolists\Components\TextEntry::make('service_status')
                                            ->badge()
                                            ->color(fn($state) => match($state) {
                                                'scheduled' => 'gray',
                                                'in_progress' => 'warning',
                                                'completed' => 'success',
                                                default => 'gray',
                                            }),

                                        Infolists\Components\TextEntry::make('service.assessmentFormSchemas')
                                            ->label('Assessment Forms')
                                            ->listWithLineBreaks()
                                            ->formatStateUsing(fn($state) => $state->form_name)
                                            ->badge()
                                            ->color('info')
                                            ->visible(fn($record) => $record->service->assessmentFormSchemas->count() > 0),

                                        Infolists\Components\Actions::make([
                                            Infolists\Components\Actions\Action::make('complete_service')
                                                ->label('Mark Complete')
                                                ->icon('heroicon-o-check-circle')
                                                ->color('success')
                                                ->visible(fn($record) => $record->service_status !== 'completed')
                                                ->requiresConfirmation()
                                                ->action(function ($record) {
                                                    $record->update(['service_status' => 'completed']);
                                                    Notification::make()
                                                        ->success()
                                                        ->title('Service Marked Complete')
                                                        ->send();
                                                }),
                                        ]),
                                    ]),
                            ]),
                    ])
                    ->icon('heroicon-o-clipboard-document-list')
                    ->description('All services selected during intake with their assessment forms'),

                // ========================================
                // ⏱️ QUEUE & TIMING
                // ========================================
                Infolists\Components\Section::make('Queue Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('queue_number')
                                    ->size('lg')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('priority_level')
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        1 => 'Urgent',
                                        2 => 'High',
                                        3 => 'Normal',
                                        default => 'Unknown',
                                    })
                                    ->badge()
                                    ->color(fn ($state) => match($state) {
                                        1 => 'danger',
                                        2 => 'warning',
                                        3 => 'success',
                                        default => 'gray',
                                    }),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Infolists\Components\Section::make('Service Provider')
                    ->schema([
                        Infolists\Components\TextEntry::make('serviceProvider.name')
                            ->default('Not assigned'),
                        Infolists\Components\TextEntry::make('department.name'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Infolists\Components\Section::make('Timing')
                    ->schema([
                        Infolists\Components\TextEntry::make('joined_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('serving_started_at')
                            ->label('Started At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('serving_completed_at')
                            ->label('Completed At')
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceQueues::route('/'),
            'view' => Pages\ViewServiceQueue::route('/{record}'),
            'edit' => Pages\EditServiceQueue::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $invoiceCount = Invoice::where('status', 'pending')->count(); 
        $serviceRequestCount = ServiceRequest::where('status', 'pending_payment')->count();
        
        $total = $invoiceCount + $serviceRequestCount;
        
        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}