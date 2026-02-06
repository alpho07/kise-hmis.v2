<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Payment Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('reference_number')
                                    ->label('Receipt Number')
                                    ->weight('bold')
                                    ->copyable()
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                                
                                Infolists\Components\TextEntry::make('payment_date')
                                    ->label('Payment Date')
                                    ->dateTime('d M Y, H:i'),
                                
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn($state) => match($state) {
                                        'completed' => 'success',
                                        'pending' => 'warning',
                                        'failed' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),

                Infolists\Components\Section::make('Client Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('client.full_name')
                                    ->label('Client Name'),
                                
                                Infolists\Components\TextEntry::make('client.uci')
                                    ->label('UCI')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('client.phone')
                                    ->label('Phone')
                                    ->placeholder('N/A'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Invoice & Visit Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('invoice.invoice_number')
                                    ->label('Invoice Number')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('invoice.visit.visit_number')
                                    ->label('Visit Number')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('invoice.total_amount')
                                    ->label('Invoice Amount')
                                    ->money('KES'),
                            ]),
                    ])
                    ->visible(fn($record) => $record->invoice()->exists()),

                Infolists\Components\Section::make('Payment Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('amount_paid')
                                    ->label('Amount Paid')
                                    ->money('KES')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight('bold')
                                    ->color('success'),
                                
                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Payment Method')
                                    ->formatStateUsing(fn($state) => match($state) {
                                        'cash' => 'Cash',
                                        'mpesa' => 'M-PESA',
                                        'card' => 'Card',
                                        'bank_transfer' => 'Bank Transfer',
                                        'account_credit' => 'Account Credit',
                                        'hybrid' => 'Hybrid Payment',
                                        default => strtoupper($state ?? 'N/A'),
                                    })
                                    ->badge()
                                    ->color('primary'),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('transaction_id')
                                    ->label('Transaction ID')
                                    ->placeholder('N/A')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('mpesa_receipt_number')
                                    ->label('M-PESA Receipt')
                                    ->placeholder('N/A')
                                    ->copyable()
                                    ->visible(fn($record) => ($record->payment_method ?? '') === 'mpesa'),
                            ]),

                        // CREDIT ACCOUNT INFO
                        Infolists\Components\TextEntry::make('account_credit_used')
                            ->label('Credit Account Used')
                            ->money('KES')
                            ->visible(fn($record) => $record->account_credit_used > 0)
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('creditAccount.account_number')
                            ->label('Credit Account Number')
                            ->copyable()
                            ->visible(fn($record) => $record->creditAccount()->exists()),

                        Infolists\Components\TextEntry::make('change_given')
                            ->label('Change Given')
                            ->money('KES')
                            ->visible(fn($record) => $record->change_given > 0)
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('notes')
                            ->label('Payment Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Service Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('invoice.items')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('service.name')
                                    ->label('Service'),
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Qty'),
                                Infolists\Components\TextEntry::make('unit_price')
                                    ->label('Price')
                                    ->money('KES'),
                                Infolists\Components\TextEntry::make('subtotal')
                                    ->label('Total')
                                    ->money('KES'),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => $record->invoice()->exists() && $record->invoice->items()->count() > 0),

                Infolists\Components\Section::make('Processing Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('processedBy.name')
                            ->label('Processed By')
                            ->placeholder('System'),
                        
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y, H:i:s'),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_receipt')
                ->label('Print Receipt')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->button()
                ->url(fn() => route('receipts.print', ['payment' => $this->record->id]))
                ->openUrlInNewTab(),
            
            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn() => route('receipts.pdf', ['payment' => $this->record->id]))
                ->openUrlInNewTab()
                ->visible(fn() => false), // Enable when PDF is implemented
            
            Actions\Action::make('view_invoice')
                ->label('View Invoice')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->visible(function () {
                    return $this->record->invoice()->exists() && 
                           class_exists('\App\Filament\Resources\BillingResource');
                })
                ->url(function () {
                    if ($this->record->invoice && class_exists('\App\Filament\Resources\BillingResource')) {
                        return \App\Filament\Resources\BillingResource::getUrl('view', ['record' => $this->record->invoice_id]);
                    }
                    return null;
                })
                ->openUrlInNewTab(),
        ];
    }
}