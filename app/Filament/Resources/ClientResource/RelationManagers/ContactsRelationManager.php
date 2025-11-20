<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Emergency Contacts';

    protected static ?string $icon = 'heroicon-o-phone';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('contact_type')
                            ->label('Contact Type')
                            ->required()
                            ->options([
                                'emergency' => 'Emergency Contact',
                                'family' => 'Family Member',
                                'friend' => 'Friend',
                                'caregiver' => 'Caregiver',
                                'professional' => 'Professional Contact',
                                'other' => 'Other',
                            ])
                            ->native(false)
                            ->default('emergency')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contact person name')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('relationship')
                            ->label('Relationship to Client')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Mother, Brother, Friend')
                            ->datalist([
                                'Mother',
                                'Father',
                                'Spouse',
                                'Sibling',
                                'Child',
                                'Grandparent',
                                'Friend',
                                'Neighbor',
                                'Caregiver',
                                'Social Worker',
                            ])
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->required()
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('+254712345678')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('contact@example.com')
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_emergency')
                            ->label('Emergency Contact')
                            ->helperText('Primary emergency contact')
                            ->inline(false)
                            ->default(true)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_primary')
                            ->label('Primary Contact')
                            ->helperText('Main point of contact')
                            ->inline(false)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('notes')
                            ->label('Additional Notes')
                            ->maxLength(500)
                            ->rows(2)
                            ->placeholder('Any special instructions or information')
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->weight('semibold')
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('relationship')
                    ->label('Relationship')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\BadgeColumn::make('contact_type')
                    ->label('Type')
                    ->colors([
                        'danger' => 'emergency',
                        'success' => 'family',
                        'warning' => 'caregiver',
                        'info' => 'professional',
                        'gray' => fn ($state) => in_array($state, ['friend', 'other']),
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->icon('heroicon-o-phone')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->icon('heroicon-o-envelope')
                    ->copyable()
                    ->toggleable()
                    ->placeholder('N/A'),

                Tables\Columns\IconColumn::make('is_emergency')
                    ->label('Emergency')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('danger')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('contact_type')
                    ->options([
                        'emergency' => 'Emergency',
                        'family' => 'Family',
                        'friend' => 'Friend',
                        'caregiver' => 'Caregiver',
                        'professional' => 'Professional',
                        'other' => 'Other',
                    ]),

                Tables\Filters\TernaryFilter::make('is_emergency')
                    ->label('Emergency Contacts')
                    ->placeholder('All contacts')
                    ->trueLabel('Emergency only')
                    ->falseLabel('Non-emergency'),

                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label('Primary Contacts')
                    ->placeholder('All contacts')
                    ->trueLabel('Primary only')
                    ->falseLabel('Non-primary'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading('Add Emergency Contact'),
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
            ->emptyStateHeading('No emergency contacts')
            ->emptyStateDescription('Add emergency contacts for this client.')
            ->emptyStateIcon('heroicon-o-phone');
    }
}