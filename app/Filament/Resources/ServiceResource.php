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
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Services';

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

            Forms\Components\Section::make('Department & Category')
                ->schema([
                    Forms\Components\Select::make('department_id')
                        ->relationship('department', 'name')
                        ->required()
                        ->searchable()
                        ->label('Department'),

                    Forms\Components\Select::make('category')
                        ->label('Service Category')
                        ->required()
                        ->options([
                            'child' => 'Child',
                            'adult' => 'Adult',
                            'both'  => 'Both',
                        ])
                        ->native(false)
                        ->searchable()
                        ->helperText("Specify whether this service is for a Child, Adult, or Both"),
                ])
                ->columns(2),

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

                Tables\Columns\BadgeColumn::make('category')
                    ->label('Category')
                    ->colors([
                        'success' => 'child',
                        'primary' => 'adult',
                        'warning' => 'both',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

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
                Tables\Filters\SelectFilter::make('category')
                    ->label('Service Category')
                    ->options([
                        'child' => 'Child',
                        'adult' => 'Adult',
                        'both'  => 'Both',
                    ]),

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
