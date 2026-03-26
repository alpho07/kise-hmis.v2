<?php

namespace App\Filament\Resources\TriageResource\Pages;

use App\Filament\Resources\TriageResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTriage extends ViewRecord
{
    protected static string $resource = TriageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Radio and Checkbox fields don't render in Filament infolists.
        // Redirect to the edit page so the full form is always shown.
        $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
    }
}
