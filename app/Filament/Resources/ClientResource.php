<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use App\Models\County;
use App\Models\SubCounty;
use App\Models\Ward;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;

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

                // SECTION 1: MINIMAL REGISTRATION (RECEPTION - 4 CORE FIELDS)
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

                        Forms\Components\Toggle::make('unknown_dob')
                            ->label('Date of Birth Unknown')
                            ->helperText('Toggle if DOB is not available - will enable age estimation')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?bool $state, Get $get) {
                                if ($state) {
                                    // DOB unknown - clear DOB, keep age if entered
                                    $set('date_of_birth', null);
                                } else {
                                    // DOB known - clear age, keep DOB if entered
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
                                            // Calculate age from DOB
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
                                            // Calculate DOB backwards from age
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

                        Forms\Components\TextInput::make('guardian_name')
                            ->label('Guardian / Parent Name')
                            ->maxLength(255)
                            ->placeholder('e.g., Jane Doe')
                            ->prefixIcon('heroicon-o-user-group')
                            ->helperText('Required for clients under 18 years')
                            ->required(fn (Get $get) => $get('estimated_age') && $get('estimated_age') < 18)
                            ->disabled(fn (string $operation, $record) => 
                                $operation === 'edit' && $record?->client_type === 'old_new' && !$isIntake && !$isSuperAdmin
                            )
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('phone_primary')
                            ->label('Primary Phone')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('+254712345678')
                            ->prefixIcon('heroicon-o-phone')
                            ->helperText('Guardian\'s or client\'s phone number')
                            ->disabled(fn (string $operation, $record) => 
                                $operation === 'edit' && $record?->client_type === 'old_new' && !$isIntake && !$isSuperAdmin
                            )
                            ->columnSpan(1),
                    ]),

                // SECTION 2: FULL PERSONAL INFORMATION (INTAKE OFFICER ONLY)
                Forms\Components\Section::make('Additional Personal Information')
                    ->description('Complete client details - Intake Officer')
                    ->icon('heroicon-o-user-circle')
                    ->columns(3)
                    ->visible($isIntake || $isSuperAdmin)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('registration_source')
                            ->label('Registration Source')
                            ->required()
                            ->options([
                                'main_center' => 'Main Center (HQ)',
                                'satellite' => 'Satellite Center',
                                'outreach' => 'Outreach Program',
                                'online' => 'Online Registration',
                                'referral' => 'External Referral',
                            ])
                            ->default('main_center')
                            ->native(false)
                            ->prefixIcon('heroicon-o-building-office')
                            ->helperText('Where was this client first registered?')
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('registration_date')
                            ->label('Registration Date')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->prefixIcon('heroicon-o-calendar')
                            ->helperText('Date of first registration')
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Client')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Is this client currently active?')
                            ->columnSpan(1),
                    ]),

                // ... (rest of the sections remain the same as in previous version)
                // Copy sections 3-7 from the previous ClientResource.php
            ]);
    }

    public static function table(Table $table): Table
    {
        $isSuperAdmin = auth()->user()->hasRole('super_admin');
        
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-building-office-2')
                    ->toggleable()
                    ->visible($isSuperAdmin),

                Tables\Columns\TextColumn::make('uci')
                    ->label('UCI')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-finger-print')
                    ->weight('semibold'),

                Tables\Columns\ImageColumn::make('photo')
                    ->label('Photo')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-o-user')
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('gender')
                    ->label('Gender')
                    ->colors([
                        'primary' => 'male',
                        'warning' => 'female',
                        'gray' => 'other',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('estimated_age')
                    ->label('Age')
                    ->suffix(' yrs')
                    ->icon('heroicon-o-cake')
                    ->badge()
                    ->color(fn ($state) => $state < 18 ? 'info' : 'success')
                    ->formatStateUsing(fn ($state) => $state . ($state < 18 ? ' (Child)' : ' (Adult)'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone_primary')
                    ->label('Phone')
                    ->icon('heroicon-o-phone')
                    ->copyable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('county.name')
                    ->label('County')
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Registered')
                    ->date('d M Y')
                    ->icon('heroicon-o-calendar')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->visible($isSuperAdmin),

                Tables\Filters\SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                        'other' => 'Other',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('county_id')
                    ->label('County')
                    ->relationship('county', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('children')
                    ->label('Children (< 18)')
                    ->query(fn (Builder $query): Builder => $query->where('estimated_age', '<', 18)),

                Tables\Filters\Filter::make('adults')
                    ->label('Adults (18+)')
                    ->query(fn (Builder $query): Builder => $query->where('estimated_age', '>=', 18)),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All Clients')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()->hasAnyRole(['intake_officer', 'admin', 'super_admin'])),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()->hasAnyRole(['admin', 'super_admin'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasAnyRole(['admin', 'super_admin'])),
                    Tables\Actions\RestoreBulkAction::make()
                        ->visible(fn () => auth()->user()->hasAnyRole(['admin', 'super_admin'])),
                ]),
            ])
            ->defaultSort('registration_date', 'desc');
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

    public static function getEloquentQuery(): Builder
    {
        // Super admin sees all clients from all branches
        if (auth()->user()->hasRole('super_admin')) {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]);
        }
        
        // Other users see only their branch's clients (handled by BelongsToBranch trait)
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['receptionist', 'intake_officer', 'triage_nurse', 'admin', 'super_admin']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['receptionist', 'intake_officer', 'admin', 'super_admin']);
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->hasAnyRole(['intake_officer', 'admin', 'super_admin']);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super_admin']);
    }
}