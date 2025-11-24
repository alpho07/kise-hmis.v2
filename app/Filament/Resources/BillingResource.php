<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingResource\Pages;
use App\Models\Invoice;
use App\Models\Visit;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\InsuranceProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BillingResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Billing';
    protected static ?string $navigationGroup = 'Financial';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $query->whereHas('visit', function ($q) {
                    $q->where('current_stage', 'billing');
                })->orWhere('status', 'draft');
            })
            ->latest();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice & Client Information')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->disabled()
                                    ->dehydrated(),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'pending' => 'Pending Payment',
                                        'paid' => 'Paid',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('draft')
                                    ->disabled(fn($context) => $context === 'create')
                                    ->native(false),

                                Forms\Components\Select::make('visit_id')
                                    ->label('Visit')
                                    ->relationship('visit', 'visit_number')
                                    ->searchable()
                                    ->required()
                                    ->disabled(fn($context) => $context === 'edit'),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('client.full_name')
                                    ->label('Client Name')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('client.uci')
                                    ->label('UCI')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('client.phone')
                                    ->label('Phone')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Service Items Management')
                    ->description('Add, modify, or remove service items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Grid::make(6)
                                    ->schema([
                                        Forms\Components\Select::make('service_id')
                                            ->label('Service')
                                            ->options(Service::query()->pluck('name', 'id'))
                                            ->required()
                                            ->searchable()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                if ($state) {
                                                    $service = Service::find($state);
                                                    if ($service) {
                                                        $set('service_name', $service->name);
                                                        $set('unit_price', $service->price);
                                                        
                                                        // Auto-calculate subtotal
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
                                                $set('subtotal', $state * $quantity);
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
                                                $set('subtotal', $state * $unitPrice);
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
                                                $subtotal = ($unitPrice * $quantity) - $state;
                                                $set('subtotal', max(0, $subtotal));
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

                                Forms\Components\Textarea::make('notes')
                                    ->label('Item Notes')
                                    ->rows(1)
                                    ->placeholder('Special instructions for this service...')
                                    ->columnSpanFull(),

                                Forms\Components\Hidden::make('service_name'),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Add Service Item')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['service_name'] ?? 'New Service')
                            ->live()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                self::updateTotals($get, $set);
                            })
                            ->deleteAction(
                                fn ($action) => $action->requiresConfirmation()
                            ),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Insurance & Payment Validation')
                    ->description('Verify insurance coverage and set payment methods')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('payment_method')
                                    ->label('Primary Payment Method')
                                    ->options([
                                        'sha' => 'SHA (Social Health Authority)',
                                        'ncpwd' => 'NCPWD',
                                        'insurance' => 'Private Insurance',
                                        'cash' => 'Cash',
                                        'mpesa' => 'M-PESA',
                                        'credit' => 'Credit Account',
                                        'bank_transfer' => 'Bank Transfer',
                                        'mixed' => 'Multiple Payment Methods',
                                    ])
                                    ->required()
                                    ->reactive()
                                    ->native(false),

                                Forms\Components\Select::make('insurance_provider_id')
                                    ->label('Insurance Provider')
                                    ->relationship('insuranceProvider', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(callable $get) => in_array($get('payment_method'), ['sha', 'ncpwd', 'insurance']))
                                    ->required(fn(callable $get) => in_array($get('payment_method'), ['sha', 'ncpwd', 'insurance'])),
                            ]),

                        Forms\Components\Repeater::make('payment_splits')
                            ->label('Payment Method Breakdown')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('method')
                                            ->label('Payment Method')
                                            ->options([
                                                'sha' => 'SHA',
                                                'ncpwd' => 'NCPWD',
                                                'insurance' => 'Insurance',
                                                'cash' => 'Cash',
                                                'mpesa' => 'M-PESA',
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

                                        Forms\Components\Select::make('insurance_provider_id')
                                            ->label('Provider')
                                            ->options(InsuranceProvider::pluck('name', 'id'))
                                            ->searchable()
                                            ->visible(fn(callable $get) => in_array($get('method'), ['sha', 'ncpwd', 'insurance'])),
                                    ]),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(1)
                                    ->placeholder('Reference numbers, approval codes, etc.')
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn(callable $get) => $get('payment_method') === 'mixed')
                            ->defaultItems(0)
                            ->addActionLabel('Add Payment Method')
                            ->collapsible()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('payment_notes')
                            ->label('Payment Validation Notes')
                            ->rows(3)
                            ->placeholder('Insurance verification details, approval codes, coverage limits...')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Financial Summary')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('total_amount')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0),

                                Forms\Components\TextInput::make('tax_amount')
                                    ->label('Tax (16% VAT)')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $get, callable $set) {
                                        self::updateTotals($get, $set);
                                    }),

                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('Overall Discount')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $get, callable $set) {
                                        self::updateTotals($get, $set);
                                    })
                                    ->helperText('Additional discount on entire invoice'),

                                Forms\Components\TextInput::make('final_amount')
                                    ->label('Final Amount')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->extraAttributes(['class' => 'font-bold text-xl']),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Internal Notes')
                            ->rows(2)
                            ->placeholder('Internal billing notes...')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Hidden::make('client_id'),
                Forms\Components\Hidden::make('payment_administrator_id')
                    ->default(auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-o-document-text')
                    ->copyable()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('visit.visit_number')
                    ->label('Visit')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('final_amount')
                    ->label('Amount')
                    ->money('KES')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->colors([
                        'primary' => 'sha',
                        'info' => 'ncpwd',
                        'secondary' => 'insurance',
                        'success' => 'cash',
                        'warning' => 'mpesa',
                        'gray' => ['credit', 'bank_transfer', 'mixed'],
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => in_array($record->status, ['draft', 'pending'])),

                Tables\Actions\Action::make('approve')
                    ->label('Approve & Send to Cashier')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Invoice')
                    ->modalDescription('This will approve the invoice and send it to cashier for payment.')
                    ->action(function (Invoice $record) {
                        DB::transaction(function () use ($record) {
                            $record->update([
                                'status' => 'pending',
                                'payment_administrator_id' => auth()->id(),
                            ]);
                            
                            $record->visit->moveToStage('payment');

                            Notification::make()
                                ->success()
                                ->title('Invoice Approved')
                                ->body("Invoice {$record->invoice_number} sent to cashier.")
                                ->send();
                        });
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function updateTotals(callable $get, callable $set): void
    {
        $items = collect($get('items') ?? []);
        $subtotal = $items->sum('subtotal');
        $tax = (float) $get('tax_amount') ?: 0;
        $discount = (float) $get('discount_amount') ?: 0;
        
        $finalAmount = $subtotal + $tax - $discount;

        $set('total_amount', number_format($subtotal, 2, '.', ''));
        $set('final_amount', number_format(max(0, $finalAmount), 2, '.', ''));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillings::route('/'),
            'create' => Pages\CreateBilling::route('/create'),
            'edit' => Pages\EditBilling::route('/{record}/edit'),
            'view' => Pages\ViewBilling::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'draft')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}