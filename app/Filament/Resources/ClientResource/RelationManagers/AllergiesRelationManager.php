<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AllergiesRelationManager extends RelationManager
{
    protected static string $relationship = 'allergies';

    protected static ?string $title = 'Allergies';

    protected static ?string $icon = 'heroicon-o-exclamation-triangle';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Allergy Information')
                    ->schema([
                        Forms\Components\TextInput::make('allergen_name')
                            ->label('Allergen Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Peanuts, Penicillin, Latex')
                            ->datalist([
                                'Peanuts',
                                'Tree Nuts',
                                'Milk',
                                'Eggs',
                                'Wheat',
                                'Soy',
                                'Fish',
                                'Shellfish',
                                'Penicillin',
                                'Aspirin',
                                'Latex',
                                'Pollen',
                                'Dust Mites',
                                'Pet Dander',
                                'Insect Stings',
                            ])
                            ->columnSpan(2),

                        Forms\Components\Select::make('allergy_type')
                            ->label('Allergy Type')
                            ->required()
                            ->options([
                                'food' => 'Food Allergy',
                                'medication' => 'Medication Allergy',
                                'environmental' => 'Environmental Allergy',
                                'insect' => 'Insect Allergy',
                                'contact' => 'Contact Allergy',
                                'other' => 'Other',
                            ])
                            ->native(false)
                            ->columnSpan(1),

                        Forms\Components\Select::make('severity')
                            ->label('Severity Level')
                            ->required()
                            ->options([
                                'mild' => 'Mild',
                                'moderate' => 'Moderate',
                                'severe' => 'Severe',
                                'life_threatening' => 'Life-Threatening',
                            ])
                            ->native(false)
                            ->default('moderate')
                            ->columnSpan(1),

                        Forms\Components\CheckboxList::make('typical_reactions')
                            ->label('Typical Reactions')
                            ->options([
                                'rash' => 'Skin Rash',
                                'hives' => 'Hives',
                                'itching' => 'Itching',
                                'swelling' => 'Swelling',
                                'difficulty_breathing' => 'Difficulty Breathing',
                                'wheezing' => 'Wheezing',
                                'nausea' => 'Nausea',
                                'vomiting' => 'Vomiting',
                                'diarrhea' => 'Diarrhea',
                                'dizziness' => 'Dizziness',
                                'anaphylaxis' => 'Anaphylaxis',
                            ])
                            ->columns(3)
                            ->helperText('Select all that apply')
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('reaction')
                            ->label('Detailed Reaction Description')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Describe the typical reaction in detail')
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('notes')
                            ->label('Additional Notes')
                            ->maxLength(500)
                            ->rows(2)
                            ->placeholder('Treatment, precautions, or other relevant information')
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('allergen_name')
            ->columns([
                Tables\Columns\TextColumn::make('allergen_name')
                    ->label('Allergen')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-o-exclamation-triangle'),

                Tables\Columns\BadgeColumn::make('allergy_type')
                    ->label('Type')
                    ->colors([
                        'danger' => 'food',
                        'warning' => 'medication',
                        'info' => 'environmental',
                        'primary' => 'insect',
                        'success' => 'contact',
                        'gray' => 'other',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\BadgeColumn::make('severity')
                    ->label('Severity')
                    ->colors([
                        'success' => 'mild',
                        'warning' => 'moderate',
                        'danger' => 'severe',
                        'danger' => 'life_threatening',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'life_threatening' => 'LIFE THREATENING',
                        default => strtoupper($state),
                    })
                    ->icon(fn (string $state): string => match($state) {
                        'life_threatening' => 'heroicon-o-shield-exclamation',
                        'severe' => 'heroicon-o-exclamation-circle',
                        default => '',
                    }),

                Tables\Columns\TextColumn::make('typical_reactions')
                    ->label('Reactions')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', ucfirst($state)))
                    ->wrap()
                    ->limit(3),

                Tables\Columns\TextColumn::make('reaction')
                    ->label('Details')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->reaction)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('allergy_type')
                    ->options([
                        'food' => 'Food',
                        'medication' => 'Medication',
                        'environmental' => 'Environmental',
                        'insect' => 'Insect',
                        'contact' => 'Contact',
                        'other' => 'Other',
                    ]),

                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'mild' => 'Mild',
                        'moderate' => 'Moderate',
                        'severe' => 'Severe',
                        'life_threatening' => 'Life-Threatening',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading('Add Allergy')
                    ->modalWidth('2xl'),
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
            ->emptyStateHeading('No allergies recorded')
            ->emptyStateDescription('Add any known allergies for this client.')
            ->emptyStateIcon('heroicon-o-exclamation-triangle')
            ->defaultSort('severity', 'desc');
    }
}