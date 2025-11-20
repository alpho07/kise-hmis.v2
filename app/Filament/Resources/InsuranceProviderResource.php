<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsuranceProviderResource\Pages;
use App\Filament\Resources\InsuranceProviderResource\RelationManagers;
use App\Models\InsuranceProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InsuranceProviderResource extends Resource
{
    protected static ?string $model = InsuranceProvider::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('short_name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('contact_person')
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\Textarea::make('address')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('claim_submission_method')
                    ->maxLength(255),
                Forms\Components\TextInput::make('claim_email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('claim_portal_url')
                    ->maxLength(255),
                Forms\Components\TextInput::make('default_coverage_percentage')
                    ->required()
                    ->numeric()
                    ->default(100.00),
                Forms\Components\TextInput::make('coverage_limits'),
                Forms\Components\TextInput::make('excluded_services'),
                Forms\Components\TextInput::make('claim_processing_days')
                    ->numeric(),
                Forms\Components\Toggle::make('requires_preauthorization')
                    ->required(),
                Forms\Components\Toggle::make('requires_referral')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('settings'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('short_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('contact_person')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('claim_submission_method')
                    ->searchable(),
                Tables\Columns\TextColumn::make('claim_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('claim_portal_url')
                    ->searchable(),
                Tables\Columns\TextColumn::make('default_coverage_percentage')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('claim_processing_days')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('requires_preauthorization')
                    ->boolean(),
                Tables\Columns\IconColumn::make('requires_referral')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInsuranceProviders::route('/'),
            'create' => Pages\CreateInsuranceProvider::route('/create'),
            'view' => Pages\ViewInsuranceProvider::route('/{record}'),
            'edit' => Pages\EditInsuranceProvider::route('/{record}/edit'),
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
