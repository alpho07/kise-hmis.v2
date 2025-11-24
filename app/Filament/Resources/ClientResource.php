<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use App\Models\County;
use App\Models\SubCounty;
use App\Models\Ward;
use App\Models\Branch;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Clients';
    protected static ?string $navigationGroup = 'Client Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Form $form): Form
    {
        // Determine user role
        $isSuperAdmin = auth()->user()->hasRole('super_admin');
        $isReception = auth()->user()->hasRole('receptionist');
        $isIntake = auth()->user()->hasRole('intake_officer');
        $isTriage = auth()->user()->hasRole('triage_nurse');
        
        return $form
            ->schema([
                // SECTION 0: BRANCH SELECTION (SUPER ADMIN ONLY)
                Forms\Components\Section::make('Branch Assignment')
                    ->description('Select the branch for this client (Super Admin only)')
                    ->icon('heroicon-o-building-office-2')
                    ->visible($isSuperAdmin)
                    ->schema([
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->required()
                            ->options(Branch::active()->pluck('name', 'id'))
                            ->default(fn () => auth()->user()->branch_id)
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->prefixIcon('heroicon-o-building-office-2')
                            ->helperText('Select which branch this client belongs to')
                            ->columnSpanFull(),
                    ]),

                // SECTION 1: CLIENT REGISTRATION
                Forms\Components\Section::make('Client Registration')
                    ->description('Essential client information - Registration at Reception')
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->visible($isReception || $isIntake || $isSuperAdmin)
                    ->schema([
                        Forms\Components\Placeholder::make('uci_display')
                            ->label('UCI (Unique Client Identifier)')
                            ->content(fn ($record) => $record?->uci ?? 'Will be auto-generated: KISE/A/000XXX/2025')
                            ->helperText('Format: KISE/A/000XXX/YEAR')
                            ->columnSpan(2),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('e.g., John')
                                    ->prefixIcon('heroicon-o-user')
                                    ->helperText('Client\'s first name')
                                    ->disabled(fn (string $operation, $record) => 
                                        $operation === 'edit' && $record?->client_type === 'old_new' && !$isIntake && !$isSuperAdmin
                                    )
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('middle_name')
                                    ->label('Middle Name')
                                    ->maxLength(100)
                                    ->placeholder('e.g., Mary (Optional)')
                                    ->prefixIcon('heroicon-o-user')
                                    ->helperText('Middle name if applicable')
                                    ->visible($isIntake || $isSuperAdmin)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('e.g., Doe')
                                    ->prefixIcon('heroicon-o-user')
                                    ->helperText('Client\'s surname/family name')
                                    ->disabled(fn (string $operation, $record) => 
                                        $operation === 'edit' && $record?->client_type === 'old_new' && !$isIntake && !$isSuperAdmin
                                    )
                                    ->columnSpan(1),
                            ])
                            ->columnSpan(2),

                        Forms\Components\Select::make('gender')
                            ->label('Gender')
                            ->required()
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ])
                            ->native(false)
                            ->prefixIcon('heroicon-o-user-circle')
                            ->helperText('Client\'s gender/sex')
                            ->disabled(fn (string $operation, $record) => 
                                $operation === 'edit' && $record?->client_type === 'old_new' && !$isIntake && !$isSuperAdmin
                            )
                            ->columnSpan(1),

                        // ==========================================
                        // DOB/AGE TOGGLE LOGIC (PRESERVED)
                        // ==========================================
                        Forms\Components\Toggle::make('unknown_dob')
                            ->label('Date of Birth Unknown')
                            ->helperText('Toggle if DOB is not available - will enable age estimation')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?bool $state, Get $get) {
                                if ($state) {
                                    $set('date_of_birth', null);
                                } else {
                                    $set('estimated_age', null);
                                }
                            })
                            ->columnSpan(1),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('date_of_birth')
                                    ->label('Date of Birth')
                                    ->required(fn (Get $get) => !$get('unknown_dob'))
                                    ->native(false)
                                    ->maxDate(now())
                                    ->displayFormat('d/m/Y')
                                    ->prefixIcon('heroicon-o-cake')
                                    ->helperText(fn (Get $get) => $get('unknown_dob') 
                                        ? 'Auto-calculated from estimated age' 
                                        : 'Select client\'s actual date of birth'
                                    )
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                        if ($state && !$get('unknown_dob')) {
                                            $dob = Carbon::parse($state);
                                            $age = $dob->age;
                                            $set('estimated_age', $age);
                                        }
                                    })
                                    ->disabled(fn (Get $get, string $operation, $record) => 
                                        $get('unknown_dob') || 
                                        ($operation === 'edit' && $record?->client_type === 'old_new' && !$isIntake && !$isSuperAdmin)
                                    )
                                    ->dehydrated(true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('estimated_age')
                                    ->label('Estimated Age (Years)')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(120)
                                    ->placeholder('e.g., 5')
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->helperText(fn (Get $get) => $get('unknown_dob') 
                                        ? 'Enter estimated age in years (whole numbers only)' 
                                        : 'Auto-calculated from date of birth'
                                    )
                                    ->required(fn (Get $get) => $get('unknown_dob'))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?int $state, Get $get) {
                                        if ($state && $get('unknown_dob')) {
                                            $calculatedDob = now()->subYears($state)->format('Y-m-d');
                                            $set('date_of_birth', $calculatedDob);
                                        }
                                    })
                                    ->suffix(function (Get $get)  {
                                        $age = $get('estimated_age');
                                        if (!$age) return '';
                                        return $age < 18 ? '(Child)' : '(Adult)';
                                    })
                                    ->disabled(fn (Get $get) => !$get('unknown_dob'))
                                    ->dehydrated(true)
                                    ->columnSpan(1),
                            ])
                            ->columnSpan(2),

                        Forms\Components\Placeholder::make('age_category')
                            ->label('Age Category')
                            ->content(function (Get $get) {
                                $age = $get('estimated_age');
                                if (!$age) return 'Enter DOB or Age to see category';
                                
                                if ($age < 2) return '👶 Infant (< 2 years)';
                                if ($age < 5) return '🧒 Toddler (2-4 years)';
                                if ($age < 13) return '👦 Child (5-12 years)';
                                if ($age < 18) return '👨 Adolescent (13-17 years)';
                                return '👤 Adult (18+ years)';
                            })
                            ->columnSpan(2),

                        // Guardian/Phone fields
                        Forms\Components\TextInput::make('guardian_name')
                            ->label('Guardian / Parent Name')
                            ->maxLength(255)
                            ->placeholder('e.g., Jane Doe')
                            ->prefixIcon('heroicon-o-user-group')
                            ->helperText('Required for clients under 18 years')
                            ->required(fn (Get $get) => $get('estimated_age') && $get('estimated_age') < 18)
                            ->visible(fn (Get $get) => $get('estimated_age') && $get('estimated_age') < 18)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('guardian_phone')
                            ->label('Guardian Phone Number')
                            ->tel()
                            ->placeholder('e.g., 0712345678')
                            ->prefixIcon('heroicon-o-phone')
                            ->helperText('Primary contact for minor')
                            ->required(fn (Get $get) => $get('estimated_age') && $get('estimated_age') < 18)
                            ->visible(fn (Get $get) => $get('estimated_age') && $get('estimated_age') < 18)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('phone_primary')
                            ->label('Phone Number')
                            ->tel()
                            ->placeholder('e.g., 0712345678')
                            ->prefixIcon('heroicon-o-phone')
                            ->helperText('Client\'s direct contact')
                            ->required(fn (Get $get) => !$get('estimated_age') || $get('estimated_age') >= 18)
                            ->visible(fn (Get $get) => !$get('estimated_age') || $get('estimated_age') >= 18)
                            ->columnSpan(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uci')
                    ->label('UCI')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary')
                    ->icon('heroicon-o-identification'),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Client Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->description(fn (Client $record) => $record->phone_primary)
                    ->weight('semibold')
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('age')
                    ->label('Age')
                    ->suffix(' yrs')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('gender')
                    ->colors([
                        'info' => 'male',
                        'danger' => 'female',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                // ACTIVE VISIT STATUS COLUMN
                Tables\Columns\TextColumn::make('activeVisit.current_stage')
                    ->label('Visit Status')
                    ->badge()
                    ->default('No Active Visit')
                    ->color(fn ($state): string => match($state) {
                        'reception' => 'gray',
                        'triage' => 'warning',
                        'intake' => 'primary',
                        'billing' => 'orange',
                        'payment' => 'success',
                        'service_point' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : 'No Active Visit')
                    ->description(fn (Client $record) => 
                        $record->activeVisit 
                            ? '🕐 ' . $record->activeVisit->check_in_time->format('h:i A') . ' • ' . $record->activeVisit->visit_number
                            : 'Ready for check-in'
                    )
                    ->icon(fn ($state) => $state ? 'heroicon-o-clock' : 'heroicon-o-check-circle')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ncpwd_number')
                    ->label('NCPWD #')
                    ->placeholder('Not registered')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('has_active_visit')
                    ->label('Visit Status')
                    ->options([
                        'active' => 'Has Active Visit',
                        'none' => 'No Active Visit',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'active') {
                            $query->whereHas('visits', function ($q) {
                                $q->where('status', 'in_progress');
                            });
                        } elseif ($data['value'] === 'none') {
                            $query->whereDoesntHave('visits', function ($q) {
                                $q->where('status', 'in_progress');
                            });
                        }
                    }),

                Tables\Filters\SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ]),

                Tables\Filters\Filter::make('children')
                    ->label('Children Only')
                    ->query(fn (Builder $query): Builder => $query->children()),

                Tables\Filters\Filter::make('adults')
                    ->label('Adults Only')
                    ->query(fn (Builder $query): Builder => $query->adults()),
            ])
            ->actions([
                // ============================================
                // PRIMARY ACTION: START VISIT / VIEW VISIT
                // ============================================
                Tables\Actions\Action::make('start_visit')
                    ->label(fn (Client $record) => 
                        $record->hasActiveVisit() 
                            ? 'Visit In Progress' 
                            : 'Start Visit'
                    )
                    ->icon(fn (Client $record) => 
                        $record->hasActiveVisit() 
                            ? 'heroicon-o-clock' 
                            : 'heroicon-o-play-circle'
                    )
                    ->color(fn (Client $record) => 
                        $record->hasActiveVisit() 
                            ? 'warning' 
                            : 'success'
                    )
                    ->button()
                    ->requiresConfirmation(fn (Client $record) => !$record->hasActiveVisit())
                    ->modalHeading(fn (Client $record) => 
                        $record->hasActiveVisit() 
                            ? 'Visit In Progress' 
                            : 'Start New Visit'
                    )
                    ->modalDescription(fn (Client $record) => 
                        $record->hasActiveVisit() 
                            ? null 
                            : "Start a new visit for {$record->full_name}"
                    )
                    ->modalIcon(fn (Client $record) => 
                        $record->hasActiveVisit() 
                            ? 'heroicon-o-information-circle' 
                            : 'heroicon-o-clipboard-document-check'
                    )
                    ->modalWidth(fn (Client $record) => 
                        $record->hasActiveVisit() ? 'md' : 'lg'
                    )
                    ->form(fn (Client $record) => 
                        $record->hasActiveVisit() 
                            ? []
                            : [
                                Forms\Components\Section::make('Visit Details')
                                    ->schema([
                                        Forms\Components\Select::make('visit_type')
                                            ->label('Visit Type')
                                            ->options([
                                                'new' => '🆕 New Visit',
                                                'follow_up' => '🔄 Follow-up',
                                                'review' => '📋 Review',
                                                'emergency' => '🚨 Emergency',
                                            ])
                                            ->required()
                                            ->default('new')
                                            ->native(false)
                                            ->columnSpanFull(),

                                        Forms\Components\Select::make('visit_purpose')
                                            ->label('Purpose of Visit')
                                            ->options([
                                                'assessment' => 'Assessment',
                                                'therapy' => 'Therapy Session',
                                                'device_fitting' => 'Device Fitting',
                                                'consultation' => 'Consultation',
                                                'review' => 'Review/Follow-up',
                                                'placement' => 'Placement Discussion',
                                                'other' => 'Other',
                                            ])
                                            ->required()
                                            ->native(false)
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('purpose_notes')
                                            ->label('Additional Notes')
                                            ->placeholder('Any specific reason for visit...')
                                            ->rows(2)
                                            ->columnSpanFull(),

                                        Forms\Components\Toggle::make('service_available')
                                            ->label('Service Available Today?')
                                            ->default(true)
                                            ->reactive()
                                            ->inline(false)
                                            ->helperText('Toggle off if service is not available'),

                                        Forms\Components\Select::make('unavailability_reason')
                                            ->label('Reason for Unavailability')
                                            ->options([
                                                'staff_unavailable' => 'Staff Unavailable',
                                                'equipment_down' => 'Equipment Out of Order',
                                                'room_unavailable' => 'Room Not Available',
                                                'full_booking' => 'Fully Booked',
                                                'other' => 'Other',
                                            ])
                                            ->visible(fn (Forms\Get $get) => !$get('service_available'))
                                            ->required(fn (Forms\Get $get) => !$get('service_available'))
                                            ->native(false),

                                        Forms\Components\Textarea::make('unavailability_notes')
                                            ->label('Unavailability Notes')
                                            ->visible(fn (Forms\Get $get) => !$get('service_available'))
                                            ->rows(2),
                                    ]),
                            ]
                    )
                    ->modalContent(fn (Client $record) => 
                        $record->hasActiveVisit() 
                            ? view('filament.components.visit-in-progress', [
                                'visit' => $record->activeVisit,
                                'client' => $record,
                              ])
                            : null
                    )
                    
                    ->action(function (Client $record, array $data) {
                        if ($record->hasActiveVisit()) {
                            $existingVisit = $record->activeVisit;
                            
                            Notification::make()
                                ->warning()
                                ->title('Visit Already In Progress')
                                ->body("Client has an active visit at stage: {$existingVisit->current_stage}")
                                ->persistent()
                                ->send();
                            
                            return;
                        }

                        // Create new visit - START AT TRIAGE
                        $visit = Visit::create([
                            'client_id' => $record->id,
                            'branch_id' => Auth::user()->branch_id,
                            'visit_type' => $data['visit_type'],
                            'visit_date'=>date('Y-m-d'),
                            'visit_purpose' => $data['visit_purpose'],
                            'purpose_notes' => $data['purpose_notes'] ?? null,
                            'service_available' => $data['service_available'] ?? true,
                            'unavailability_reason' => $data['unavailability_reason'] ?? null,
                            'unavailability_notes' => $data['unavailability_notes'] ?? null,
                            'checked_in_by' => Auth::id(),
                            'check_in_time' => now(),
                            'current_stage' => 'triage', // ✅ START DIRECTLY AT TRIAGE
                            'status' => 'in_progress',
                        ]);

                        // Show success notification
                        Notification::make()
                            ->success()
                            ->title('Visit Started')
                            ->body("Visit {$visit->visit_number} created for {$record->full_name} - Client is now in Triage queue")
                            ->persistent()
                            ->send();
                    }),

                // View Client Details
                Tables\Actions\ViewAction::make()
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray'),

                // Quick Actions Group
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('end_visit')
                        ->label('End Visit')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Client $record) => $record->hasActiveVisit())
                        ->form([
                            Forms\Components\Select::make('reason')
                                ->label('Reason for Ending Visit')
                                ->options([
                                    'service_unavailable' => 'Service Not Available',
                                    'incomplete_documents' => 'Incomplete Documents',
                                    'client_request' => 'Client Request',
                                    'emergency' => 'Emergency',
                                    'other' => 'Other',
                                ])
                                ->required()
                                ->native(false),
                            
                            Forms\Components\Textarea::make('notes')
                                ->label('Notes')
                                ->required()
                                ->rows(3),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Client $record, array $data) {
                            $visit = $record->activeVisit;
                            
                            $visit->update([
                                'status' => 'deferred',
                                'check_out_time' => now(),
                                'purpose_notes' => ($visit->purpose_notes ?? '') . "\n\nEnded: {$data['reason']} - {$data['notes']}",
                            ]);

                            Notification::make()
                                ->warning()
                                ->title('Visit Ended')
                                ->body("Visit {$visit->visit_number} has been ended")
                                ->send();
                        }),

                    Tables\Actions\Action::make('visit_history')
                        ->label('Visit History')
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->url(fn (Client $record) => 
                            route('filament.admin.resources.visits.index', [
                                'tableFilters[client_id][value]' => $record->id
                            ])
                        ),
                ])
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray')
                ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AddressesRelationManager::class,
            RelationManagers\ContactsRelationManager::class,
            RelationManagers\InsurancesRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\AllergiesRelationManager::class,
            RelationManagers\VisitsRelationManager::class,

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereHas('visits', function ($query) {
            $query->where('status', 'in_progress')
                ->whereDate('check_in_time', today());
        })->count();
    }
}