<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
     protected static ?string $navigationGroup = 'System Setup';
    protected static ?string $navigationLabel = 'Services';

       public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole(['super_admin','admin']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Service Code / Abbreviation')
                        ->required()
                        ->maxLength(50)
                        ->afterStateUpdated(fn ($state, callable $set) =>
                            $set('code', strtoupper($state))
                        )
                        ->helperText('Abbreviations will be auto-capitalized'),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(200),

                    Forms\Components\Textarea::make('description')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Department & Classification')
                ->schema([
                    Forms\Components\Select::make('department_id')
                        ->relationship('department', 'name')
                        ->required()
                        ->searchable()
                        ->label('Department'),

                    Forms\Components\Select::make('age_group')
                        ->label('Age Group')
                        ->required()
                        ->options(Service::ageGroupOptions())
                        ->default(Service::AGE_GROUP_ALL)
                        ->native(false)
                        ->helperText('Who this service is for — used at intake to surface only age-appropriate options.'),

                    Forms\Components\Select::make('category')
                        ->label('Service Category')
                        ->required()
                        ->options(Service::categoryOptions())
                        ->native(false)
                        ->searchable()
                        ->helperText('The clinical/business type of service.'),
                ])
                ->columns(3),

            Forms\Components\Section::make('Pricing')
                ->schema([
                    Forms\Components\TextInput::make('base_price')
                        ->required()
                        ->numeric()
                        ->default(0.00)
                        ->prefix('KES'),

                    Forms\Components\Toggle::make('sha_covered')
                        ->label('SHA Covered?'),

                    Forms\Components\TextInput::make('sha_price')
                        ->numeric()
                        ->prefix('KES'),

                    Forms\Components\Toggle::make('ncpwd_covered')
                        ->label('NCPWD Covered?'),

                    Forms\Components\TextInput::make('ncpwd_price')
                        ->numeric()
                        ->prefix('KES'),
                ])
                ->columns(3),

            Forms\Components\Section::make('Service Attributes')
                ->schema([
                    Forms\Components\Toggle::make('requires_assessment')
                        ->label('Requires Assessment?')
                        ->default(false),

                    Forms\Components\Toggle::make('is_recurring')
                        ->label('Recurring Service?')
                        ->default(false),

                    Forms\Components\TextInput::make('duration_minutes')
                        ->numeric()
                        ->suffix('min'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active Service?')
                        ->default(true),

                    Forms\Components\DatePicker::make('available_from'),
                    Forms\Components\DatePicker::make('available_until'),
                ])
                ->columns(3),

            Forms\Components\Section::make('Classification Metadata')
                ->schema([
                    Forms\Components\TextInput::make('subcategory')
                        ->label('Subcategory')
                        ->maxLength(100),

                    Forms\Components\Textarea::make('notes')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Insurance Pricing')
                ->description('Set how much each insurer covers and what the client pays.')
                ->schema([
                    Forms\Components\Repeater::make('insurancePrices')
                        ->relationship('insurancePrices')
                        ->schema([
                            Forms\Components\Select::make('insurance_provider_id')
                                ->label('Insurance Provider')
                                ->options(function () {
                                    return \App\Models\InsuranceProvider::active()->ordered()
                                        ->get()
                                        ->groupBy(fn($p) => ucwords(str_replace('_', ' ', $p->type)))
                                        ->map(fn($group) => $group->pluck('name', 'id'))
                                        ->toArray();
                                })
                                ->required()
                                ->searchable(),

                            Forms\Components\TextInput::make('covered_amount')
                                ->label('Insurer Pays (KES)')
                                ->numeric()
                                ->required(),

                            Forms\Components\TextInput::make('client_copay')
                                ->label('Client Pays (KES)')
                                ->numeric()
                                ->required(),

                            Forms\Components\Toggle::make('is_active')
                                ->default(true),

                            Forms\Components\Textarea::make('notes')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->addActionLabel('Add Insurance Price')
                        ->collapsible(),
                ])
                ->collapsible(),

            Forms\Components\Section::make('Session Configuration')
                ->schema([
                    Forms\Components\Select::make('service_type')
                        ->options([
                            'assessment'           => 'Assessment',
                            'therapy'              => 'Therapy',
                            'assistive_technology' => 'Assistive Technology',
                            'consultation'         => 'Consultation',
                        ])
                        ->required()
                        ->default('assessment'),

                    Forms\Components\Toggle::make('requires_sessions')
                        ->label('Requires Multiple Sessions')
                        ->live()
                        ->helperText('Enable for therapy services where a course of sessions is prescribed.'),

                    Forms\Components\TextInput::make('default_session_count')
                        ->label('Default Session Count')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->visible(fn (\Filament\Forms\Get $get) => $get('requires_sessions'))
                        ->required(fn (\Filament\Forms\Get $get) => $get('requires_sessions')),
                ])
                ->columns(3)
                ->collapsible(),

            Forms\Components\Section::make('Linked Assessment Forms')
                ->schema([
                    Forms\Components\Select::make('assessmentForms')
                        ->label('Forms')
                        ->multiple()
                        ->relationship('assessmentForms', 'name')
                        ->preload()
                        ->searchable()
                        ->helperText('Forms that specialists fill out for this service.'),
                ])
                ->collapsible(),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('age_group')
                    ->label('Age Group')
                    ->colors([
                        'success' => Service::AGE_GROUP_CHILD,
                        'primary' => Service::AGE_GROUP_ADULT,
                        'gray'    => Service::AGE_GROUP_ALL,
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        Service::AGE_GROUP_CHILD => 'Child',
                        Service::AGE_GROUP_ADULT => 'Adult',
                        default                  => 'All Ages',
                    })
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('category')
                    ->label('Category')
                    ->colors([
                        'info'    => Service::CATEGORY_ASSESSMENT,
                        'success' => Service::CATEGORY_THERAPY,
                        'warning' => Service::CATEGORY_COUNSELING,
                        'gray'    => Service::CATEGORY_CONSULTATION,
                        'primary' => Service::CATEGORY_ASSISTIVE_TECH,
                    ]),

                Tables\Columns\TextColumn::make('base_price')
                    ->label('Price')
                    ->numeric()
                    ->prefix('KES ')
                    ->sortable(),

                Tables\Columns\IconColumn::make('sha_covered')->boolean(),
                Tables\Columns\TextColumn::make('sha_price')->numeric()->prefix('KES '),

                Tables\Columns\IconColumn::make('ncpwd_covered')->boolean(),
                Tables\Columns\TextColumn::make('ncpwd_price')->numeric()->prefix('KES '),

                Tables\Columns\IconColumn::make('is_recurring')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),

                Tables\Columns\TextColumn::make('available_from')->date()->sortable(),
                Tables\Columns\TextColumn::make('available_until')->date()->sortable(),

                Tables\Columns\TextColumn::make('subcategory')->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])

            ->filters([
                Tables\Filters\SelectFilter::make('age_group')
                    ->label('Age Group')
                    ->options(Service::ageGroupOptions()),

                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options(Service::categoryOptions()),

                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Service'),

                Tables\Filters\TrashedFilter::make(),
            ])

            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'view' => Pages\ViewService::route('/{record}'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
