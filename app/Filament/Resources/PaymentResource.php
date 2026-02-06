<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Colors\Color;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Payments';
    protected static ?string $navigationGroup = 'Financial';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['invoice', 'client', 'processedBy'])
            ->latest('payment_date');
    }

    public static function getNavigationBadge(): ?string
    {
        $todayCount = static::getModel()::whereDate('payment_date', today())->count();
        return $todayCount > 0 ? (string) $todayCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Receipt #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->color(Color::Blue)
                    ->icon('heroicon-o-receipt-percent'),

                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->copyable()
                    ->placeholder('N/A')
                    ->url(function ($record) {
                        if ($record->invoice && class_exists('\App\Filament\Resources\BillingResource')) {
                            return \App\Filament\Resources\BillingResource::getUrl('view', ['record' => $record->invoice_id]);
                        }
                        return null;
                    })
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight('semibold')
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->searchable()
                    ->copyable()
                    ->placeholder('N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->money('KES')
                    ->sortable()
                    ->weight('bold')
                    ->color(Color::Green),

                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Method')
                    ->formatStateUsing(fn($state) => match($state) {
                        'cash' => 'Cash',
                        'mpesa' => 'M-PESA',
                        'card' => 'Card',
                        'bank_transfer' => 'Bank Transfer',
                        default => strtoupper($state ?? 'N/A'),
                    })
                    ->colors([
                        'success' => 'cash',
                        'primary' => 'mpesa',
                        'warning' => 'card',
                        'info' => 'bank_transfer',
                        'gray' => fn($state) => !$state,
                    ])
                    ->icon(fn($state) => match($state) {
                        'cash' => 'heroicon-o-banknotes',
                        'mpesa' => 'heroicon-o-device-phone-mobile',
                        'card' => 'heroicon-o-credit-card',
                        'bank_transfer' => 'heroicon-o-building-library',
                        default => 'heroicon-o-currency-dollar',
                    }),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->copyable()
                    ->placeholder('N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('mpesa_receipt_number')
                    ->label('M-PESA Receipt')
                    ->searchable()
                    ->copyable()
                    ->placeholder('N/A')
                    ->toggleable()
                    ->visible(fn($record) => ($record->payment_method ?? '') === 'mpesa'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'pending',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(fn($state) => ucfirst($state ?? 'completed')),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Payment Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('processedBy.name')
                    ->label('Processed By')
                    ->placeholder('System')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'mpesa' => 'M-PESA',
                        'card' => 'Card',
                        'bank_transfer' => 'Bank Transfer',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                    ])
                    ->default('completed'),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn(Builder $query) => $query->whereDate('payment_date', today()))
                    ->default(),

                Tables\Filters\Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn(Builder $query) => $query->whereBetween('payment_date', [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ])),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn(Builder $query) => $query->whereMonth('payment_date', now()->month)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('payment_date', 'desc')
            ->emptyStateHeading('No payments recorded')
            ->emptyStateDescription('Completed payments will appear here')
            ->emptyStateIcon('heroicon-o-credit-card');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}