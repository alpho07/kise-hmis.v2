<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReceptionResource\Pages;
use App\Models\Client;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReceptionResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Reception';
    protected static ?string $navigationGroup = 'Clinical Workflow';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole(['super_admin', 'admin', 'receptionist']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('client_id')
                ->label('Client')
                ->options(fn () => Client::orderBy('first_name')->get()->pluck('full_name', 'id'))
                ->searchable()
                ->required(),

            Forms\Components\Select::make('visit_type')
                ->label('Visit Type')
                ->options([
                    'walk_in'     => 'Walk-in',
                    'appointment' => 'Appointment',
                    'follow_up'   => 'Follow-up',
                ])
                ->default('walk_in')
                ->required(),

            Forms\Components\Select::make('service_available')
                ->label('Service Availability')
                ->options([
                    'yes' => 'Available',
                    'no'  => 'Not available today',
                ])
                ->default('yes')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.full_name')->label('Client')->searchable(),
                Tables\Columns\TextColumn::make('visit_number')->label('Visit #'),
                Tables\Columns\TextColumn::make('visit_type')->label('Type'),
                Tables\Columns\TextColumn::make('current_stage')->label('Stage')
                    ->badge(),
                Tables\Columns\TextColumn::make('check_in_time')->label('Checked In')->since(),
            ])
            ->defaultSort('check_in_time', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReceptions::route('/'),
            'create' => Pages\CreateReception::route('/create'),
            'view'   => Pages\ViewReception::route('/{record}'),
            'edit'   => Pages\EditReception::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('current_stage', 'reception')
            ->orderBy('check_in_time', 'asc');
    }
}
