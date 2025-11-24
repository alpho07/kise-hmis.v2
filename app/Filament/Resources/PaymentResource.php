<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Receipt;
use App\Models\InvoiceItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Cashier / Payments';
    protected static ?string $navigationGroup = 'Financial';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['invoice.visit', 'invoice.items', 'client'])
            ->whereHas('invoice.visit', function ($query) {
                $query->where('current_stage', 'payment');
            })
            ->orWhere('status', 'pending')
            ->latest();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    
                    Forms\Components\Wizard\Step::make('Invoice & Client')
                        ->schema([
                            Forms\Components\Section::make('Triage Clearance Status')
    ->description('Verify triage clearance before processing payment')
    ->schema([
        Forms\Components\Placeholder::make('triage_status')
            ->label('')
            ->content(function () {
                $visitId = request()->query('visit');
                if (!$visitId) return new \Illuminate\Support\HtmlString('<p class="text-gray-500">No visit selected</p>');
                
                $visit = \App\Models\Visit::find($visitId);
                if (!$visit) return new \Illuminate\Support\HtmlString('<p class="text-red-600">Visit not found</p>');
                
                $check = \App\Services\PaymentProcessingService::canProceedToService($visit);
                
                if ($check['can_proceed']) {
                    return new \Illuminate\Support\HtmlString('
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold text-green-800">✅ Cleared for Service</p>
                                    <p class="text-xs text-green-700 mt-1">Triage completed. Client can proceed to services.</p>
                                </div>
                            </div>
                        </div>
                    ');
                } else {
                    return new \Illuminate\Support\HtmlString('
                        <div class="bg-red-50 border-l-4 border-red-600 p-4 rounded">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold text-red-800">⚠️ NOT Cleared</p>
                                    <p class="text-xs text-red-700 mt-1">' . $check['message'] . '</p>
                                    <p class="text-xs text-red-600 mt-2 font-medium">Payment will be processed but client CANNOT access services until cleared.</p>
                                </div>
                            </div>
                        </div>
                    ');
                }
            }),
    ])
    ->collapsible()
    ->collapsed(false)
    ->columnSpanFull(),
                            Forms\Components\Select::make('invoice_id')
                                ->label('Select Invoice')
                                ->relationship('invoice', 'invoice_number', function ($query) {
                                    return $query->whereHas('visit', function ($q) {
                                        $q->where('current_stage', 'payment');
                                    })->orWhere('status', 'pending');
                                })
                                ->searchable()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $invoice = Invoice::with('client', 'items.service')->find($state);
                                        if ($invoice) {
                                            $set('client_id', $invoice->client_id);
                                            $set('client_name', $invoice->client->full_name);
                                            $set('client_uci', $invoice->client->uci);
                                            $set('client_phone', $invoice->client->phone);
                                            $set('invoice_amount', $invoice->final_amount);
                                            $set('amount_due', $invoice->final_amount);
                                            
                                            // Load invoice items for editing
                                            $items = $invoice->items->map(function ($item) {
                                                return [
                                                    'id' => $item->id,
                                                    'service_id' => $item->service_id,
                                                    'service_name' => $item->service_name,
                                                    'unit_price' => $item->unit_price,
                                                    'quantity' => $item->quantity,
                                                    'discount' => $item->discount ?? 0,
                                                    'subtotal' => $item->subtotal,
                                                ];
                                            })->toArray();
                                            
                                            $set('invoice_items', $items);
                                        }
                                    }
                                })
                                ->disabled(fn($context) => $context === 'edit'),

                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\TextInput::make('client_name')
                                        ->label('Client Name')
                                        ->disabled()
                                        ->dehydrated(false),

                                    Forms\Components\TextInput::make('client_uci')
                                        ->label('UCI')
                                        ->disabled()
                                        ->dehydrated(false),

                                    Forms\Components\TextInput::make('client_phone')
                                        ->label('Phone')
                                        ->disabled()
                                        ->dehydrated(false),
                                ]),
                        ]),

                    Forms\Components\Wizard\Step::make('Review & Edit Items')
                        ->description('Modify invoice items if needed')
                        ->schema([
                            Forms\Components\Placeholder::make('items_info')
                                ->label('')
                                ->content('You can add, modify, or remove items before payment'),

                            Forms\Components\Repeater::make('invoice_items')
                                ->label('Invoice Items')
                                ->schema([
                                    Forms\Components\Grid::make(6)
                                        ->schema([
                                            Forms\Components\Select::make('service_id')
                                                ->label('Service')
                                                ->options(Service::pluck('name', 'id'))
                                                ->required()
                                                ->searchable()
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    if ($state) {
                                                        $service = Service::find($state);
                                                        if ($service) {
                                                            $set('service_name', $service->name);
                                                            $set('unit_price', $service->price);
                                                            $quantity = $get('quantity') ?? 1;
                                                            $set('subtotal', $service->price * $quantity);
                                                        }
                                                    }
                                                })
                                                ->columnSpan(2),

                                            Forms\Components\TextInput::make('unit_price')
                                                ->label('Unit Price')
                                                ->numeric()
                                                ->prefix('KES')
                                                ->required()
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                    $quantity = $get('quantity') ?? 1;
                                                    $discount = $get('discount') ?? 0;
                                                    $set('subtotal', ($state * $quantity) - $discount);
                                                })
                                                ->columnSpan(1),

                                            Forms\Components\TextInput::make('quantity')
                                                ->label('Qty')
                                                ->numeric()
                                                ->default(1)
                                                ->required()
                                                ->minValue(1)
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                    $unitPrice = $get('unit_price') ?? 0;
                                                    $discount = $get('discount') ?? 0;
                                                    $set('subtotal', ($unitPrice * $state) - $discount);
                                                })
                                                ->columnSpan(1),

                                            Forms\Components\TextInput::make('discount')
                                                ->label('Discount')
                                                ->numeric()
                                                ->prefix('KES')
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                    $unitPrice = $get('unit_price') ?? 0;
                                                    $quantity = $get('quantity') ?? 1;
                                                    $set('subtotal', ($unitPrice * $quantity) - $state);
                                                })
                                                ->columnSpan(1),

                                            Forms\Components\TextInput::make('subtotal')
                                                ->label('Subtotal')
                                                ->numeric()
                                                ->prefix('KES')
                                                ->disabled()
                                                ->dehydrated()
                                                ->columnSpan(1),
                                        ]),
                                    
                                    Forms\Components\Hidden::make('id'),
                                    Forms\Components\Hidden::make('service_name'),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Add Item')
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['service_name'] ?? 'New Item')
                                ->live()
                                ->afterStateUpdated(function (callable $get, callable $set) {
                                    $items = collect($get('invoice_items') ?? []);
                                    $newTotal = $items->sum('subtotal');
                                    $set('amount_due', $newTotal);
                                }),

                            Forms\Components\TextInput::make('amount_due')
                                ->label('Updated Total Amount')
                                ->numeric()
                                ->prefix('KES')
                                ->disabled()
                                ->dehydrated(false)
                                ->extraAttributes(['class' => 'font-bold text-xl']),
                        ]),

                    Forms\Components\Wizard\Step::make('Payment Methods')
                        ->description('Process payment with single or multiple methods')
                        ->schema([
                            Forms\Components\Radio::make('payment_type')
                                ->label('Payment Type')
                                ->options([
                                    'single' => 'Single Payment Method',
                                    'multiple' => 'Multiple Payment Methods (Split Payment)',
                                ])
                                ->default('single')
                                ->inline()
                                ->reactive()
                                ->required(),

                            // Single Payment Method
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('payment_method')
                                        ->label('Payment Method')
                                        ->options([
                                            'cash' => 'Cash',
                                            'mpesa' => 'M-PESA',
                                            'sha' => 'SHA',
                                            'ncpwd' => 'NCPWD',
                                            'insurance' => 'Private Insurance',
                                            'credit' => 'Credit Account',
                                            'bank_transfer' => 'Bank Transfer',
                                            'cheque' => 'Cheque',
                                        ])
                                        ->required(fn(callable $get) => $get('payment_type') === 'single')
                                        ->reactive()
                                        ->native(false),

                                    Forms\Components\TextInput::make('amount_paid')
                                        ->label('Amount Paid')
                                        ->numeric()
                                        ->prefix('KES')
                                        ->required(fn(callable $get) => $get('payment_type') === 'single')
                                        ->reactive()
                                        ->helperText(function (callable $get) {
                                            $due = (float) $get('amount_due') ?: 0;
                                            $paid = (float) $get('amount_paid') ?: 0;
                                            $balance = $due - $paid;
                                            
                                            if ($balance > 0) {
                                                return "Balance: KES " . number_format($balance, 2);
                                            } elseif ($balance < 0) {
                                                return "Overpayment: KES " . number_format(abs($balance), 2);
                                            }
                                            return "Exact amount";
                                        }),

                                    Forms\Components\TextInput::make('mpesa_receipt_number')
                                        ->label('M-PESA Receipt')
                                        ->visible(fn(callable $get) => $get('payment_method') === 'mpesa')
                                        ->required(fn(callable $get) => $get('payment_method') === 'mpesa'),

                                    Forms\Components\TextInput::make('transaction_id')
                                        ->label('Transaction/Reference ID')
                                        ->visible(fn(callable $get) => in_array($get('payment_method'), ['bank_transfer', 'cheque', 'mpesa'])),
                                ])
                                ->visible(fn(callable $get) => $get('payment_type') === 'single'),

                            // Multiple Payment Methods
                            Forms\Components\Repeater::make('payment_splits')
                                ->label('Payment Breakdown')
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\Select::make('method')
                                                ->label('Method')
                                                ->options([
                                                    'cash' => 'Cash',
                                                    'mpesa' => 'M-PESA',
                                                    'sha' => 'SHA',
                                                    'ncpwd' => 'NCPWD',
                                                    'insurance' => 'Insurance',
                                                    'credit' => 'Credit',
                                                    'bank_transfer' => 'Bank Transfer',
                                                ])
                                                ->required()
                                                ->native(false),

                                            Forms\Components\TextInput::make('amount')
                                                ->label('Amount')
                                                ->numeric()
                                                ->prefix('KES')
                                                ->required()
                                                ->reactive(),

                                            Forms\Components\TextInput::make('reference')
                                                ->label('Reference/Receipt #')
                                                ->placeholder('Transaction ID, receipt number, etc.'),
                                        ]),
                                ])
                                ->visible(fn(callable $get) => $get('payment_type') === 'multiple')
                                ->defaultItems(2)
                                ->addActionLabel('Add Payment Method')
                                ->live()
                                ->afterStateUpdated(function (callable $get, callable $set) {
                                    $splits = collect($get('payment_splits') ?? []);
                                    $totalPaid = $splits->sum('amount');
                                    $set('total_paid_splits', $totalPaid);
                                })
                                ->columnSpanFull(),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('total_paid_splits')
                                        ->label('Total Paid (All Methods)')
                                        ->numeric()
                                        ->prefix('KES')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->visible(fn(callable $get) => $get('payment_type') === 'multiple'),

                                    Forms\Components\Placeholder::make('payment_balance')
                                        ->label('Balance')
                                        ->content(function (callable $get) {
                                            $due = (float) $get('amount_due') ?: 0;
                                            $paid = (float) ($get('total_paid_splits') ?: 0);
                                            $balance = $due - $paid;
                                            
                                            $color = $balance > 0 ? 'text-danger-600' : ($balance < 0 ? 'text-warning-600' : 'text-success-600');
                                            
                                            return new \Illuminate\Support\HtmlString(
                                                '<span class="font-bold text-xl ' . $color . '">KES ' . number_format(abs($balance), 2) . '</span>'
                                            );
                                        })
                                        ->visible(fn(callable $get) => $get('payment_type') === 'multiple'),
                                ]),

                            Forms\Components\Textarea::make('notes')
                                ->label('Payment Notes')
                                ->rows(3)
                                ->placeholder('Any special notes about this payment...')
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull()
                ->persistStepInQueryString(),

                Forms\Components\Hidden::make('client_id'),
                Forms\Components\Hidden::make('processed_by')
                    ->default(auth()->id()),
                Forms\Components\Hidden::make('status')
                    ->default('completed'),
                Forms\Components\Hidden::make('payment_date')
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Payment #')
                    ->searchable()
                    ->weight('semibold')
                    ->icon('heroicon-o-credit-card')
                    ->color('success'),

                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('invoice.final_amount')
                    ->label('Invoice Amt')
                    ->money('KES'),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money('KES')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->colors([
                        'success' => 'cash',
                        'warning' => 'mpesa',
                        'primary' => 'sha',
                        'info' => 'ncpwd',
                        'gray' => ['credit', 'bank_transfer', 'multiple'],
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\IconColumn::make('receipt')
                    ->label('Receipt')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success'),

                Tables\Columns\TextColumn::make('payment_date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method'),
                Tables\Filters\SelectFilter::make('status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('complete')
                    ->label('Complete & Generate Receipt')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'completed' && !$record->receipt)
                    ->requiresConfirmation()
                    ->action(function (Payment $record) {
                        DB::transaction(function () use ($record) {
                            self::completePaymentWorkflow($record);
                        });
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Complete payment workflow
     */
    protected static function completePaymentWorkflow(Payment $payment): void
    {
        $invoice = $payment->invoice;
        $visit = $invoice->visit;

        // Update invoice items if modified
        if (isset($payment->invoice_items)) {
            foreach ($payment->invoice_items as $itemData) {
                if (isset($itemData['id'])) {
                    // Update existing
                    InvoiceItem::where('id', $itemData['id'])->update([
                        'unit_price' => $itemData['unit_price'],
                        'quantity' => $itemData['quantity'],
                        'discount' => $itemData['discount'] ?? 0,
                        'subtotal' => $itemData['subtotal'],
                    ]);
                } else {
                    // Create new
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'service_id' => $itemData['service_id'],
                        'service_name' => $itemData['service_name'],
                        'unit_price' => $itemData['unit_price'],
                        'quantity' => $itemData['quantity'],
                        'discount' => $itemData['discount'] ?? 0,
                        'subtotal' => $itemData['subtotal'],
                    ]);
                }
            }
        }

        // Recalculate invoice totals
        $invoice->calculateTotals();

        // Mark invoice as paid
        $invoice->markAsPaid();

        // Move visit to service stage
        $visit->moveToStage('service_point');

        // Create queue entries
        foreach ($invoice->items as $item) {
            if ($item->serviceBooking && $item->serviceBooking->isReadyForQueue()) {
                $item->serviceBooking->createQueueEntry();
            }
        }

        // Generate receipt
        Receipt::create([
            'payment_id' => $payment->id,
            'receipt_number' => 'RCT-' . date('Ymd') . '-' . str_pad(Receipt::count() + 1, 5, '0', STR_PAD_LEFT),
            'amount' => $payment->amount_paid,
            'issued_date' => now(),
            'issued_by' => auth()->id(),
        ]);

        Notification::make()
            ->success()
            ->title('Payment Completed')
            ->body("Receipt generated. Client sent to service queues.")
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Invoice::whereHas('visit', function ($query) {
            $query->where('current_stage', 'payment');
        })->count();
        
        return $count ?: null;
    }
}