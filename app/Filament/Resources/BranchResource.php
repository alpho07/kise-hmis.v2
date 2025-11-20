<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Models\Branch;
use App\Models\County;
use App\Models\SubCounty;
use App\Models\Ward;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Branches';

    protected static ?string $navigationGroup = 'System Setup';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // SECTION 1: BASIC INFORMATION
                Forms\Components\Section::make('Branch Information')
                    ->description('Basic branch details and identification')
                    ->icon('heroicon-o-building-office')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Branch Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., KS-MAIN, MB-SAT')
                            ->helperText('Unique identifier for this branch')
                            ->columnSpan(1),

                        Forms\Components\Select::make('type')
                            ->label('Branch Type')
                            ->required()
                            ->options([
                                'main' => 'Main Center (HQ)',
                                'satellite' => 'Satellite Center',
                                'outreach' => 'Outreach Center',
                            ])
                            ->default('main')
                            ->native(false)
                            ->helperText('Type of service delivery point')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('name')
                            ->label('Branch Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Kasarani Main Center')
                            ->columnSpan(2),

                        Forms\Components\Select::make('manager_id')
                            ->label('Branch Manager')
                            ->relationship('manager', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Staff member responsible for this branch')
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Is this branch currently operational?')
                            ->inline(false)
                            ->columnSpan(1),
                    ]),

                // SECTION 2: CONTACT INFORMATION
                Forms\Components\Section::make('Contact Information')
                    ->description('How to reach this branch')
                    ->icon('heroicon-o-phone')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('+254712345678')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('branch@kise.ac.ke')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('address')
                            ->label('Physical Address')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Full physical address')
                            ->columnSpan(1),
                    ]),

                // SECTION 3: LOCATION
                Forms\Components\Section::make('Location Details')
                    ->description('Geographic location of the branch')
                    ->icon('heroicon-o-map-pin')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('county_id')
                            ->label('County')
                            ->required()
                            ->options(County::active()->ordered()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('sub_county_id', null);
                                $set('ward_id', null);
                            })
                            ->columnSpan(1),

                        Forms\Components\Select::make('sub_county_id')
                            ->label('Sub-County')
                            ->options(function (Get $get) {
                                $countyId = $get('county_id');
                                if (!$countyId) {
                                    return [];
                                }
                                return SubCounty::where('county_id', $countyId)
                                    ->active()
                                    ->ordered()
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('ward_id', null))
                            ->disabled(fn (Get $get): bool => !$get('county_id'))
                            ->columnSpan(1),

                        Forms\Components\Select::make('ward_id')
                            ->label('Ward')
                            ->options(function (Get $get) {
                                $subCountyId = $get('sub_county_id');
                                if (!$subCountyId) {
                                    return [];
                                }
                                return Ward::where('sub_county_id', $subCountyId)
                                    ->active()
                                    ->ordered()
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->disabled(fn (Get $get): bool => !$get('sub_county_id'))
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->placeholder('-1.2921')
                            ->helperText('GPS coordinates (optional)')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->placeholder('36.8219')
                            ->helperText('GPS coordinates (optional)')
                            ->columnSpan(1),
                    ]),

                // SECTION 4: OPERATING DETAILS
                Forms\Components\Section::make('Operating Schedule')
                    ->description('Working hours and capacity')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DatePicker::make('opened_at')
                            ->label('Opening Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->helperText('When did this branch start operations?')
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('closed_at')
                            ->label('Closing Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->helperText('Leave empty if still operational')
                            ->columnSpan(1),

                        Forms\Components\TimePicker::make('operating_hours_start')
                            ->label('Opening Time')
                            ->seconds(false)
                            ->default('08:00')
                            ->helperText('Daily opening time')
                            ->columnSpan(1),

                        Forms\Components\TimePicker::make('operating_hours_end')
                            ->label('Closing Time')
                            ->seconds(false)
                            ->default('17:00')
                            ->helperText('Daily closing time')
                            ->columnSpan(1),

                        Forms\Components\CheckboxList::make('operating_days')
                            ->label('Operating Days')
                            ->options([
                                'monday' => 'Monday',
                                'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday',
                                'friday' => 'Friday',
                                'saturday' => 'Saturday',
                                'sunday' => 'Sunday',
                            ])
                            ->default(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
                            ->columns(4)
                            ->helperText('Select days when branch is open')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('max_daily_clients')
                            ->label('Maximum Daily Clients')
                            ->numeric()
                            ->default(100)
                            ->helperText('Maximum clients that can be served per day')
                            ->columnSpan(2),
                    ]),

                // SECTION 5: SETTINGS
                Forms\Components\Section::make('Additional Settings')
                    ->description('Branch-specific configuration')
                    ->icon('heroicon-o-cog')
                    ->columns(1)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->label('Custom Settings')
                            ->keyLabel('Setting Name')
                            ->valueLabel('Value')
                            ->helperText('Add any branch-specific settings as key-value pairs'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-o-hashtag'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-building-office')
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'main',
                        'success' => 'satellite',
                        'info' => 'outreach',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'main' => 'Main HQ',
                        'satellite' => 'Satellite',
                        'outreach' => 'Outreach',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('county.name')
                    ->label('County')
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Manager')
                    ->icon('heroicon-o-user')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->icon('heroicon-o-phone')
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('departments_count')
                    ->label('Departments')
                    ->counts('departments')
                    ->icon('heroicon-o-building-storefront')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Staff')
                    ->counts('users')
                    ->icon('heroicon-o-users')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'main' => 'Main Center',
                        'satellite' => 'Satellite',
                        'outreach' => 'Outreach',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('county_id')
                    ->label('County')
                    ->relationship('county', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All Branches')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Branch Overview')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->label('Branch Code')
                                    ->icon('heroicon-o-hashtag')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('name')
                                    ->label('Branch Name')
                                    ->icon('heroicon-o-building-office')
                                    ->weight('bold')
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'main' => 'primary',
                                        'satellite' => 'success',
                                        'outreach' => 'info',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('manager.name')
                                    ->label('Manager')
                                    ->icon('heroicon-o-user'),

                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Status')
                                    ->boolean(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Contact Information')
                    ->icon('heroicon-o-phone')
                    ->collapsible()
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('phone')
                                    ->icon('heroicon-o-phone')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('email')
                                    ->icon('heroicon-o-envelope')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('address')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Location')
                    ->icon('heroicon-o-map-pin')
                    ->collapsible()
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('county.name')
                                    ->label('County'),

                                Infolists\Components\TextEntry::make('subCounty.name')
                                    ->label('Sub-County'),

                                Infolists\Components\TextEntry::make('ward.name')
                                    ->label('Ward'),

                                Infolists\Components\TextEntry::make('latitude')
                                    ->placeholder('Not set'),

                                Infolists\Components\TextEntry::make('longitude')
                                    ->placeholder('Not set'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Operating Schedule')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('opened_at')
                                    ->date('d M Y')
                                    ->placeholder('Not set'),

                                Infolists\Components\TextEntry::make('operating_hours_start')
                                    ->label('Opens'),

                                Infolists\Components\TextEntry::make('operating_hours_end')
                                    ->label('Closes'),

                                Infolists\Components\TextEntry::make('operating_days')
                                    ->badge()
                                    ->separator(',')
                                    ->columnSpanFull(),

                                Infolists\Components\TextEntry::make('max_daily_clients')
                                    ->label('Max Daily Capacity')
                                    ->suffix(' clients'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Statistics')
                    ->icon('heroicon-o-chart-bar')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('departments_count')
                                    ->label('Departments')
                                    ->state(fn ($record) => $record->departments()->count()),

                                Infolists\Components\TextEntry::make('users_count')
                                    ->label('Staff Members')
                                    ->state(fn ($record) => $record->users()->count()),

                                Infolists\Components\TextEntry::make('visits_count')
                                    ->label('Total Visits')
                                    ->state(fn ($record) => $record->visits()->count()),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'view' => Pages\ViewBranch::route('/{record}'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}