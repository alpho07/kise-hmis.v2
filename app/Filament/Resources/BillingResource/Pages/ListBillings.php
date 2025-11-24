<?php

namespace App\Filament\Resources\BillingResource\Pages;

use App\Filament\Resources\BillingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBillings extends ListRecords
{
    protected static string $resource = BillingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Invoice')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BillingResource\Widgets\BillingStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Invoices'),
            
            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-clock')
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),
            
            'paid' => Tab::make('Paid')
                ->icon('heroicon-o-check-circle')
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid')),
            
            'overdue' => Tab::make('Overdue')
                ->icon('heroicon-o-exclamation-circle')
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue()),
            
            'today' => Tab::make('Today')
                ->icon('heroicon-o-calendar')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('issued_at', today())),
        ];
    }
}