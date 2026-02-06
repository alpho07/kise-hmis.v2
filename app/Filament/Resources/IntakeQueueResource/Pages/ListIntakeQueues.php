<?php

namespace App\Filament\Resources\IntakeQueueResource\Pages;

use App\Filament\Resources\IntakeQueueResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListIntakeQueues extends ListRecords
{
    protected static string $resource = IntakeQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh Queue')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->redirect(static::getUrl())),
        ];
    }
}