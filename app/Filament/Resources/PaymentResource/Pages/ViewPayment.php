<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_receipt')
                ->label('Print Receipt')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->visible(fn() => $this->record->receipt)
                ->url(fn() => route('filament.admin.resources.receipts.view', $this->record->receipt->id))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Payment Details')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('reference_number')
                                    ->label('Payment Reference')
                                    ->badge()
                                    ->color('success'),
                                
                                Components\TextEntry::make('status')
                                    ->badge()
                                    ->colors([
                                        'warning' => 'pending',
                                        'success' => 'completed',
                                        'danger' => 'failed',
                                    ]),
                                
                                Components\TextEntry::make('payment_method')
                                    ->badge()
                                    ->colors([
                                        'success' => 'cash',
                                        'warning' => 'mpesa',
                                        'primary' => 'sha',
                                    ]),
                            ]),
                    ]),

                Components\Section::make('Invoice Information')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('invoice.invoice_number')
                                    ->label('Invoice Number')
                                    ->url(fn($record) => route('filament.admin.resources.invoices.view', $record->invoice_id)),
                                
                                Components\TextEntry::make('invoice.final_amount')
                                    ->label('Invoice Amount')
                                    ->money('KES'),
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
                                
                                Components\TextEntry::make('invoice.visit.visit_number')
                                    ->label('Visit Number'),
                                
                                Components\TextEntry::make('client.phone')
                                    ->label('Phone'),
                            ]),
                    ]),

                Components\Section::make('Payment Information')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('amount_paid')
                                    ->label('Amount Paid')
                                    ->money('KES')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->color('success'),
                                
                                Components\TextEntry::make('payment_date')
                                    ->dateTime(),
                                
                                Components\TextEntry::make('processedBy.name')
                                    ->label('Processed By'),
                            ]),
                    ]),

                Components\Section::make('Transaction Details')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('mpesa_receipt_number')
                                    ->label('M-PESA Receipt')
                                    ->placeholder('N/A')
                                    ->visible(fn($record) => $record->payment_method === 'mpesa'),
                                
                                Components\TextEntry::make('transaction_id')
                                    ->label('Transaction ID')
                                    ->placeholder('N/A'),
                            ]),
                        
                        Components\TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                    ])
                    ->visible(fn($record) => $record->mpesa_receipt_number || $record->transaction_id || $record->notes),

                Components\Section::make('Receipt Information')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('receipt.receipt_number')
                                    ->label('Receipt Number')
                                    ->badge()
                                    ->color('success'),
                                
                                Components\TextEntry::make('receipt.issued_date')
                                    ->label('Receipt Date')
                                    ->dateTime(),
                            ]),
                    ])
                    ->visible(fn($record) => $record->receipt),
            ]);
    }
}