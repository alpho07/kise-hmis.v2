<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashierQueueResource\Pages;
use App\Models\Visit;
use App\Models\Invoice;
use App\Services\HybridPaymentService;
use App\Services\PaymentRoutingService;
use App\Enums\PaymentMethodEnum;
use App\Models\ServiceRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CashierQueueResource extends Resource
{
    protected static ?string $model = Visit::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Cashier Queue';
    protected static ?string $navigationGroup = 'Financial';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Payment Queue';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole(['super_admin','admin','billing_officer','cashier']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('current_stage', 'cashier')
            ->whereHas('invoice')
            ->with(['client', 'invoice']);
    }

      public static function getServiceRequestsTable(): Tables\Table
    {
        return Tables\make()
            ->query(
                ServiceRequest::query()
                    ->with(['client', 'service', 'serviceDepartment', 'requestedBy'])
                    ->where('status', 'pending_payment')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable(['first_name', 'last_name', 'uci'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->searchable(),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Requested Service')
                    ->description(fn ($record) => $record->serviceDepartment->name)
                    ->searchable(),

                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->description(fn ($record) => $record->requestingDepartment->name)
                    ->searchable(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'routine' => 'gray',
                        'urgent' => 'warning',
                        'stat' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('cost')
                    ->label('Amount')
                    ->money('KES')
                    ->sortable(),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Time')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('clinical_notes')
                    ->label('Notes')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('service_department_id')
                    ->label('Department')
                    ->relationship('serviceDepartment', 'name'),

                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'routine' => 'Routine',
                        'urgent' => 'Urgent',
                        'stat' => 'STAT',
                    ]),
            ])
            ->actions([
                // VIEW DETAILS ACTION
                Tables\Actions\ViewAction::make()
                    ->modalContent(fn ($record) => view('filament.resources.service-request.view-modal', [
                        'record' => $record,
                    ])),

                // PROCESS PAYMENT ACTION - CRITICAL INTEGRATION POINT
                Tables\Actions\Action::make('process_payment')
                    ->label('Process Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        Forms\Components\Section::make('Payment Details')
                            ->schema([
                                Forms\Components\Placeholder::make('client_info')
                                    ->label('Client')
                                    ->content(fn ($record) => "{$record->client->full_name} ({$record->client->uci})"),

                                Forms\Components\Placeholder::make('service_info')
                                    ->label('Service')
                                    ->content(fn ($record) => "{$record->service->name} - {$record->serviceDepartment->name}"),

                                Forms\Components\Placeholder::make('amount_info')
                                    ->label('Total Amount')
                                    ->content(fn ($record) => 'KES ' . number_format($record->cost, 2)),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cash' => 'Cash',
                                        'mpesa' => 'M-PESA',
                                        'bank_transfer' => 'Bank Transfer',
                                        'credit_card' => 'Credit Card',
                                        'insurance' => 'Insurance',
                                        'waiver' => 'Waiver',
                                    ])
                                    ->required()
                                    ->native(false),

                                Forms\Components\TextInput::make('reference_number')
                                    ->label('Reference/Transaction Number')
                                    ->maxLength(100),

                                Forms\Components\Textarea::make('payment_notes')
                                    ->label('Payment Notes')
                                    ->rows(2),
                            ]),
                    ])
                    ->action(function ($record, array $data) {
                        \DB::transaction(function () use ($record, $data) {
                            // 1. Mark service request as paid
                            $record->update([
                                'payment_method' => $data['payment_method'],
                            ]);
                            $record->markPaid();

                            // 2. Create ServiceBooking + QueueEntry
                            // This uses the existing ServiceBooking and QueueEntry tables
                            $result = $record->createService();

                            // 3. Send notification
                            Notification::make()
                                ->title('Payment Successful')
                                ->body("Service booking created. Client added to {$record->serviceDepartment->name} queue.")
                                ->success()
                                ->send();

                            // 4. Optional: Create invoice/receipt
                            // Add your invoice creation logic here
                        });
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Service Created')
                            ->body('Client has been added to the service queue.')
                    ),

                // CANCEL REQUEST ACTION
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Reason for Cancellation')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->cancel($data['cancellation_reason']);
                        
                        Notification::make()
                            ->title('Request Cancelled')
                            ->body('Service request has been cancelled.')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'asc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('visit_number')
                    ->label('Queue #')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('Client No #')
                    ->badge()

                    ->sortable()
                    ->weight('bold'),


                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable(['first_name', 'last_name'])
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('invoice.total_client_amount')
                    ->label('Amount Due')
                    ->money('KES')
                    ->color('warning')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('invoice.amount_paid')
                    ->label('Paid')
                    ->money('KES')
                    ->color('success')
                    ->default(0),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance')
                    ->money('KES')
                    ->color('danger')
                    ->state(function (Visit $record) {
                        return max(0, $record->invoice->total_client_amount - ($record->invoice->amount_paid ?? 0));
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waiting Since')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->poll('10s')
            ->actions([
                Tables\Actions\Action::make('process_payment')
                    ->label('Process Payment')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->modalHeading('Hybrid Payment Processing')
                    ->modalWidth('4xl')
                    ->form(function (Visit $record) {
                        $balance = $record->invoice->total_client_amount - ($record->invoice->amount_paid ?? 0);

                        return [
                            Forms\Components\Section::make('Payment Summary')
                                ->schema([
                                    Forms\Components\Placeholder::make('total_due')
                                        ->label('Total Amount Due')
                                        ->content(fn() => 'KES ' . number_format($balance, 2)),

                                    Forms\Components\Placeholder::make('already_paid')
                                        ->label('Already Paid')
                                        ->content(fn() => 'KES ' . number_format($record->invoice->amount_paid ?? 0, 2))
                                        ->visible(fn() => $record->invoice->amount_paid > 0),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make('Payment Methods')
                                ->description('Accept multiple payment methods simultaneously')
                                ->schema([
                                    // Cash
                                    Forms\Components\Grid::make()
                                        ->schema([
                                            Forms\Components\Toggle::make('use_cash')
                                                ->label('Accept Cash')
                                                ->live()
                                                ->afterStateUpdated(
                                                    fn(Forms\Set $set, $state) =>
                                                    !$state ? $set('cash_amount', 0) : null
                                                ),

                                            Forms\Components\TextInput::make('cash_amount')
                                                ->label('Cash Amount')
                                                ->numeric()
                                                ->prefix('KES')
                                                ->default(0)
                                                ->live(debounce: 500)
                                                ->visible(fn(Forms\Get $get) => $get('use_cash'))
                                                ->required(fn(Forms\Get $get) => $get('use_cash')),
                                        ])
                                        ->columns(2),

                                    // M-PESA
                                    Forms\Components\Grid::make()
                                        ->schema([
                                            Forms\Components\Toggle::make('use_mpesa')
                                                ->label('Accept M-PESA')
                                                ->live()
                                                ->afterStateUpdated(
                                                    fn(Forms\Set $set, $state) =>
                                                    !$state ? $set('mpesa_amount', 0) : null
                                                ),

                                            Forms\Components\TextInput::make('mpesa_amount')
                                                ->label('M-PESA Amount')
                                                ->numeric()
                                                ->prefix('KES')
                                                ->default(0)
                                                ->live(debounce: 500)
                                                ->visible(fn(Forms\Get $get) => $get('use_mpesa'))
                                                ->required(fn(Forms\Get $get) => $get('use_mpesa')),

                                            Forms\Components\TextInput::make('mpesa_code')
                                                ->label('M-PESA Code')
                                                ->visible(fn(Forms\Get $get) => $get('use_mpesa'))
                                                ->required(fn(Forms\Get $get) => $get('use_mpesa')),
                                        ])
                                        ->columns(3),

                                    // Bank Transfer
                                    Forms\Components\Grid::make()
                                        ->schema([
                                            Forms\Components\Toggle::make('use_bank')
                                                ->label('Accept Bank Transfer')
                                                ->live()
                                                ->afterStateUpdated(
                                                    fn(Forms\Set $set, $state) =>
                                                    !$state ? $set('bank_amount', 0) : null
                                                ),

                                            Forms\Components\TextInput::make('bank_amount')
                                                ->label('Bank Transfer Amount')
                                                ->numeric()
                                                ->prefix('KES')
                                                ->default(0)
                                                ->live(debounce: 500)
                                                ->visible(fn(Forms\Get $get) => $get('use_bank'))
                                                ->required(fn(Forms\Get $get) => $get('use_bank')),

                                            Forms\Components\TextInput::make('bank_reference')
                                                ->label('Bank Reference')
                                                ->visible(fn(Forms\Get $get) => $get('use_bank'))
                                                ->required(fn(Forms\Get $get) => $get('use_bank')),
                                        ])
                                        ->columns(3),

                                    // Credit Account
                                    Forms\Components\Grid::make()
                                        ->schema([
                                            Forms\Components\Toggle::make('use_credit')
                                                ->label('Use Credit Account')
                                                ->live()
                                                ->disabled(fn() => !$record->client->creditAccount ||
                                                    $record->client->creditAccount->available_credit <= 0)
                                                ->afterStateUpdated(
                                                    fn(Forms\Set $set, $state) =>
                                                    !$state ? $set('credit_amount', 0) : null
                                                ),

                                            Forms\Components\TextInput::make('credit_amount')
                                                ->label('Credit Amount')
                                                ->numeric()
                                                ->prefix('KES')
                                                ->default(0)
                                                ->live(debounce: 500)
                                                ->visible(fn(Forms\Get $get) => $get('use_credit'))
                                                ->required(fn(Forms\Get $get) => $get('use_credit'))
                                                ->maxValue(fn() => $record->client->creditAccount?->available_credit ?? 0)
                                                ->helperText(
                                                    fn() => $record->client->creditAccount
                                                        ? 'Available: KES ' . number_format($record->client->creditAccount->available_credit, 2)
                                                        : 'No credit account available'
                                                ),
                                        ])
                                        ->columns(2),
                                ]),

                            Forms\Components\Section::make('Payment Calculation')
                                ->schema([
                                    Forms\Components\Placeholder::make('total_payment')
                                        ->label('Total Payment')
                                        ->content(function (Forms\Get $get) {
                                            $total = ($get('cash_amount') ?? 0) +
                                                ($get('mpesa_amount') ?? 0) +
                                                ($get('bank_amount') ?? 0) +
                                                ($get('credit_amount') ?? 0);
                                            return 'KES ' . number_format($total, 2);
                                        }),

                                    Forms\Components\Placeholder::make('remaining_balance')
                                        ->label('Remaining Balance')
                                        ->content(function (Forms\Get $get) use ($balance) {
                                            $totalPayment = ($get('cash_amount') ?? 0) +
                                                ($get('mpesa_amount') ?? 0) +
                                                ($get('bank_amount') ?? 0) +
                                                ($get('credit_amount') ?? 0);
                                            $remaining = max(0, $balance - $totalPayment);
                                            return 'KES ' . number_format($remaining, 2);
                                        })
                                        ->extraAttributes(['class' => $balance > 0 ? 'text-danger-600' : 'text-success-600']),
                                ])
                                ->columns(2),
                        ];
                    })
                    ->action(function (Visit $record, array $data, HybridPaymentService $paymentService) {
                        DB::transaction(function () use ($record, $data, $paymentService) {
                            try {
                                // Process hybrid payment
                                $result = $paymentService->processHybridPayment($record->invoice, $data);

                                if ($result['balance'] <= 0) {
                                    // ✅ PAYMENT COMPLETE - CREATE SERVICE QUEUES

                                    // Step 1: Update service bookings to confirmed
                                    $record->serviceBookings()->update([
                                        'status' => 'confirmed',
                                        'payment_status' => 'paid',
                                    ]);

                                    // Step 2: Create service queue entries
                                    $routingService = new PaymentRoutingService();
                                    $queueResult = $routingService->createServiceQueues($record);

                                    if ($queueResult['success']) {
                                        // Success - queues created
                                        $record->update([
                                            'payment_verified_at' => now(),
                                        ]);

                                        Notification::make()
                                            ->success()
                                            ->title('Payment Complete')
                                            ->body("Total paid: KES " . number_format($result['total_paid'], 2) . ". Client added to {$queueResult['queues_created']} service queue(s).")
                                            ->duration(8000)
                                            ->send();

                                        \Log::info('Payment complete and queues created', [
                                            'visit_id' => $record->id,
                                            'invoice_id' => $record->invoice->id,
                                            'total_paid' => $result['total_paid'],
                                            'queues_created' => $queueResult['queues_created'],
                                        ]);
                                    } else {
                                        // Payment succeeded but queue creation failed
                                        \Log::error('Queue creation failed after payment', [
                                            'visit_id' => $record->id,
                                            'error' => $queueResult['error'] ?? 'Unknown error',
                                        ]);

                                        Notification::make()
                                            ->warning()
                                            ->title('Payment Complete - Queue Issue')
                                            ->body("Payment processed (KES " . number_format($result['total_paid'], 2) . ") but queue creation failed. Please add client to service queue manually.")
                                            ->persistent()
                                            ->send();
                                    }
                                } else {
                                    // Partial payment
                                    Notification::make()
                                        ->warning()
                                        ->title('Partial Payment Received')
                                        ->body("Paid: KES " . number_format($result['total_paid'], 2) . ". Balance: KES " . number_format($result['balance'], 2))
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                \Log::error('Payment processing failed', [
                                    'visit_id' => $record->id,
                                    'invoice_id' => $record->invoice->id,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);

                                // Rethrow to trigger rollback
                                throw $e;
                            }
                        });
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashierQueues::route('/'),
            'service-requests' => Pages\ManageServiceRequests::route('/service-requests'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return Visit::where('current_stage', 'cashier')
            ->whereHas('invoice')
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
