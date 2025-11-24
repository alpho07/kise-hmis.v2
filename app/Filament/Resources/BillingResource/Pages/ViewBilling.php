<?php

namespace App\Filament\Resources\BillingResource\Pages;

use App\Filament\Resources\BillingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewBilling extends ViewRecord
{
    protected static string $resource = BillingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn() => $this->record->status === 'pending'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Invoice Details')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->badge()
                                    ->color('primary'),
                                
                                Components\TextEntry::make('status')
                                    ->badge()
                                    ->colors([
                                        'warning' => 'pending',
                                        'success' => 'paid',
                                        'danger' => 'overdue',
                                    ]),
                                
                                Components\TextEntry::make('payment_method')
                                    ->badge(),
                            ]),
                    ]),

                Components\Section::make('Client Information')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('client.full_name')
                                    ->label('Client Name'),
                                
                                Components\TextEntry::make('client.uci')
                                    ->label('UCI'),
                                
                                Components\TextEntry::make('visit.visit_number')
                                    ->label('Visit Number'),
                                
                                Components\TextEntry::make('client.phone')
                                    ->label('Phone'),
                            ]),
                    ]),

                Components\Section::make('Invoice Items')
                    ->schema([
                        Components\RepeatableEntry::make('items')
                            ->schema([
                                Components\Grid::make(4)
                                    ->schema([
                                        Components\TextEntry::make('serviceBooking.service.name')
                                            ->label('Service'),
                                        
                                        Components\TextEntry::make('unit_price')
                                            ->money('KES'),
                                        
                                        Components\TextEntry::make('quantity'),
                                        
                                        Components\TextEntry::make('subtotal')
                                            ->money('KES')
                                            ->weight('bold'),
                                    ]),
                            ]),
                    ]),

                Components\Section::make('Financial Summary')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('total_amount')
                                    ->label('Subtotal')
                                    ->money('KES'),
                                
                                Components\TextEntry::make('tax_amount')
                                    ->label('Tax')
                                    ->money('KES'),
                                
                                Components\TextEntry::make('discount_amount')
                                    ->label('Discount')
                                    ->money('KES'),
                                
                                Components\TextEntry::make('final_amount')
                                    ->label('Total Amount')
                                    ->money('KES')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->color('success'),
                            ]),
                    ]),

                Components\Section::make('Additional Information')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('issued_at')
                                    ->label('Issued Date')
                                    ->dateTime(),
                                
                                Components\TextEntry::make('due_date')
                                    ->date(),
                                
                                Components\TextEntry::make('issuedBy.name')
                                    ->label('Issued By'),
                                
                                Components\TextEntry::make('paymentAdministrator.name')
                                    ->label('Payment Admin')
                                    ->placeholder('Not assigned'),
                            ]),
                        
                        Components\TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                    ]),
            ]);
    }
}