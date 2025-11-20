<?php

namespace App\Filament\Resources\CountyResource\RelationManagers;

use App\Filament\Resources\SubCountyResource;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class SubCountiesRelationManager extends RelationManager
{
    protected static string $relationship = 'subCounties';

    public  function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
        ]);
    }

    public  function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
        ])
        ->headerActions([
            Tables\Actions\CreateAction::make(),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            
            Tables\Actions\ViewAction::make()
                ->url(fn ($record) => SubCountyResource::getUrl('view', ['record' => $record]))
                ->openUrlInNewTab(false),

            Tables\Actions\DeleteAction::make(),
        ]);
    }
}
