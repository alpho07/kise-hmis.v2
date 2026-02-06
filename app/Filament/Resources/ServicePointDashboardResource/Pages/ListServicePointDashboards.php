<?php

namespace App\Filament\Resources\ServicePointDashboardResource\Pages;

use App\Filament\Resources\ServicePointDashboardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServicePointDashboards extends ListRecords
{
    protected static string $resource = ServicePointDashboardResource::class;

    protected static ?string $title = 'Service Point Dashboard';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh Queue')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->redirect(request()->header('Referer'))),
            
            Actions\Action::make('stats')
                ->label('Today\'s Statistics')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalContent(fn() => view('filament.pages.service-point-stats', [
                    'departmentId' => auth()->user()->department_id,
                    'date' => today(),
                ]))
                ->modalWidth('5xl')
                ->slideOver(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // ServicePointStatsWidget::class,
        ];
    }
}