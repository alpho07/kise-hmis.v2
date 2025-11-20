<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Models\County;
use App\Models\SubCounty;
use App\Models\Ward;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Additional Addresses';

    protected static ?string $icon = 'heroicon-o-map-pin';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('address_type')
                            ->label('Address Type')
                            ->required()
                            ->options([
                                'home' => 'Home Address',
                                'work' => 'Work Address',
                                'school' => 'School Address',
                                'temporary' => 'Temporary Address',
                                'other' => 'Other',
                            ])
                            ->native(false)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_primary')
                            ->label('Primary Address')
                            ->helperText('Mark as main address')
                            ->inline(false)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('street_address')
                            ->label('Street Address')
                            ->maxLength(255)
                            ->placeholder('Street name and number')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('building')
                            ->label('Building Name')
                            ->maxLength(100)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('floor')
                            ->label('Floor / Unit')
                            ->maxLength(50)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('city')
                            ->label('City / Town')
                            ->maxLength(100)
                            ->columnSpan(1),

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

                        Forms\Components\TextInput::make('postal_code')
                            ->label('Postal Code')
                            ->maxLength(20)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('notes')
                            ->label('Additional Notes')
                            ->maxLength(500)
                            ->rows(2)
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('street_address')
            ->columns([
                Tables\Columns\BadgeColumn::make('address_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'home',
                        'success' => 'work',
                        'info' => 'school',
                        'warning' => 'temporary',
                        'gray' => 'other',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                Tables\Columns\TextColumn::make('street_address')
                    ->label('Street Address')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->searchable(),

                Tables\Columns\TextColumn::make('county.name')
                    ->label('County')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('address_type')
                    ->options([
                        'home' => 'Home',
                        'work' => 'Work',
                        'school' => 'School',
                        'temporary' => 'Temporary',
                        'other' => 'Other',
                    ]),

                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label('Primary Address')
                    ->placeholder('All addresses')
                    ->trueLabel('Primary only')
                    ->falseLabel('Non-primary'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle'),
            ])
            ->emptyStateHeading('No additional addresses')
            ->emptyStateDescription('Add alternative addresses for this client.')
            ->emptyStateIcon('heroicon-o-map-pin');
    }
}