<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Process Payment')
                ->icon('heroicon-o-credit-card'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentResource\Widgets\PaymentStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Payments'),
            
            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-clock')
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('invoice.visit', function ($q) {
                    $q->where('current_stage', 'payment');
                })),
            
            'completed' => Tab::make('Completed')
                ->icon('heroicon-o-check-circle')
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed')),
            
            'today' => Tab::make('Today')
                ->icon('heroicon-o-calendar')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('payment_date', today())),
            
            'cash' => Tab::make('Cash')
                ->icon('heroicon-o-banknotes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_method', 'cash')),
            
            'mpesa' => Tab::make('M-PESA')
                ->icon('heroicon-o-device-phone-mobile')
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_method', 'mpesa')),
        ];
    }
}