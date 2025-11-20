<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class ClientVisitHistory extends Widget
{
    protected static string $view = 'filament.resources.pages.clients.client-visit-history';

    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        if (!$this->record) {
            return ['visits' => collect([])];
        }

        $visits = $this->record->visits()
            ->with(['branch', 'checkedInBy'])
            ->latest('check_in_time')
            ->limit(10)
            ->get();

        return [
            'visits' => $visits,
            'totalVisits' => $this->record->visits()->count(),
        ];
    }
}