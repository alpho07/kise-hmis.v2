<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash'),
            
            Actions\Action::make('new_visit')
                ->label('New Visit')
                ->icon('heroicon-o-calendar-days')
                ->color('success')
                ->url(fn ($record): string => route('filament.admin.resources.visits.create', ['client' => $record->id])),
            
            Actions\Action::make('print_profile')
                ->label('Print Profile')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(function ($record) {
                    // Implement print functionality
                    return redirect()->route('client.print', $record);
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ClientResource\Widgets\ClientVisitHistory::class,
        ];
    }
}