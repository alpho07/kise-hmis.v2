<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingResource\Pages;
use App\Models\Invoice;
use App\Models\InsuranceClaim;
use App\Services\InsuranceClaimService;
use App\Services\PaymentRoutingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BillingResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Billing Admin';
    protected static ?string $modelLabel = 'Invoice';
    protected static ?string $pluralModelLabel = 'Billing Admin';
    protected static ?string $navigationGroup = 'Financial';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin', 'billing_officer']);
    }

    /**
     * Show invoices that need billing admin attention:
     * - Sponsor/insurance invoices (has_sponsor = true), OR
     * - Visits currently sitting in the billing stage (includes "interested in SHA/NCPWD" clients)
     * In both cases only show non-approved statuses.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $q) {
                $q->where('has_sponsor', true)
                  ->orWhereHas('visit', fn (Builder $vq) => $vq->where('current_stage', 'billing'));
            })
            ->whereIn('status', ['pending', 'verified', 'approved'])
            ->with(['client', 'visit', 'insuranceProvider', 'items.service']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Information')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Invoice Number')
                            ->disabled(),

                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'first_name')
                            ->disabled(),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->prefix('KES')
                            ->disabled(),

                        Forms\Components\TextInput::make('total_sponsor_amount')
                            ->label('Sponsor Amount')
                            ->numeric()
                            ->prefix('KES')
                            ->disabled(),

                        Forms\Components\TextInput::make('total_client_amount')
                            ->label('Client Amount')
                            ->numeric()
                            ->prefix('KES')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Verification Details')
                    ->schema([
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'sha'               => 'SHA',
                                'ncpwd'             => 'NCPWD',
                                'insurance_private' => 'Private Insurance',
                                'waiver'            => 'Waiver',
                                'ecitizen'          => 'eCitizen (M-PESA)',
                                'mixed'             => 'Hybrid',
                            ])
                            ->disabled(),

                        Forms\Components\Select::make('insurance_provider_id')
                            ->relationship('insuranceProvider', 'name')
                            ->disabled(),

                        Forms\Components\Textarea::make('payment_notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->columns(2),
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
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit.client.uci')
                    ->label('Client No #')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment Type')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'sha'               => 'info',
                        'ncpwd'             => 'success',
                        'insurance_private' => 'warning',
                        'waiver'            => 'danger',
                        'ecitizen'          => 'primary',
                        'mixed'             => 'gray',
                        default             => 'gray',
                    })
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'sha'               => 'SHA',
                        'ncpwd'             => 'NCPWD',
                        'insurance_private' => 'Insurance',
                        'waiver'            => 'Waiver',
                        'ecitizen'          => 'eCitizen',
                        'mixed'             => 'Hybrid',
                        null               => 'N/A',
                        default            => strtoupper($state),
                    }),

                Tables\Columns\TextColumn::make('insuranceProvider.name')
                    ->label('Provider')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('KES')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('KES')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('total_sponsor_amount')
                    ->label('Sponsor Amount')
                    ->money('KES')
                    ->color('info')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('KES')
                            ->label('Total Sponsor'),
                    ]),

                Tables\Columns\TextColumn::make('total_client_amount')
                    ->label('Client Amount')
                    ->money('KES')
                    ->color('success')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('KES')
                            ->label('Total Client'),
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'verified',
                        'success' => 'approved',
                        'primary' => 'paid',
                    ]),

                Tables\Columns\IconColumn::make('has_sponsor')
                    ->label('Sponsor')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('billing_approved')
                    ->label('Approved')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('requires_cashier')
                    ->label('→ Cashier')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Type')
                    ->options([
                        'sha'               => 'SHA',
                        'ncpwd'             => 'NCPWD',
                        'insurance_private' => 'Private Insurance',
                        'waiver'            => 'Waiver',
                        'mixed'             => 'Hybrid',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending Verification',
                        'verified' => 'Verified',
                        'approved' => 'Approved',
                    ]),

                Tables\Filters\Filter::make('has_sponsor')
                    ->label('Has Sponsor')
                    ->query(fn(Builder $query): Builder => $query->where('has_sponsor', true)),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                // View Payment Breakdown
                Tables\Actions\Action::make('view_breakdown')
                    ->label('View Split')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn(Invoice $record) => 'Payment Breakdown - ' . $record->invoice_number)
                    ->modalContent(fn(Invoice $record) => view('filament.components.payment-breakdown', [
                        'invoice' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                // Verify Invoice (First step)
                Tables\Actions\Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn(Invoice $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Verify Invoice')
                    ->modalDescription(
                        fn(Invoice $record) =>
                        "Verify eligibility and documentation for {$record->client->full_name}. " .
                            "This confirms the client is eligible for {$record->payment_method} coverage."
                    )
                    ->modalSubmitActionLabel('Verify Invoice')
                    ->form([
                        Forms\Components\Textarea::make('verification_notes')
                            ->label('Verification Notes')
                            ->placeholder('Document verification details: member card checked, eligibility confirmed, etc.')
                            ->rows(3),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        $record->update([
                            'status' => 'verified',
                            'verification_status' => 'verified',
                            'verification_notes' => $data['verification_notes'] ?? null,
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Invoice Verified')
                            ->body("Invoice {$record->invoice_number} has been verified and is ready for approval.")
                            ->send();

                        \Log::info('Invoice verified', [
                            'invoice_id' => $record->id,
                            'verified_by' => auth()->id(),
                        ]);
                    }),

                // Approve Invoice (Second step - creates claim and routes client)
                Tables\Actions\Action::make('approve')
                    ->label('Approve & Route')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn(Invoice $record) => $record->status === 'verified')
                    ->modalHeading('Approve Invoice & Route Client')
                    ->modalSubmitActionLabel('Approve & Route')
                    ->form(function (Invoice $record) {
                        $clientAmount = (float) ($record->total_client_amount ?? 0);
                        $sponsorAmount = (float) ($record->total_sponsor_amount ?? 0);

                        return [
                            Forms\Components\Placeholder::make('billing_summary')
                                ->label('Billing Summary')
                                ->content(
                                    "{$record->client->full_name} — "
                                    . "Total: KES " . number_format($record->total_amount ?? 0, 2)
                                    . ($sponsorAmount > 0 ? " | Sponsor: KES " . number_format($sponsorAmount, 2) : '')
                                    . " | Client: KES " . number_format($clientAmount, 2)
                                ),

                            Forms\Components\Toggle::make('send_to_cashier')
                                ->label('Send to Cashier?')
                                ->helperText('ON → client queues at cashier to pay their balance. OFF → billing admin has fully processed payment; client goes directly to service.')
                                ->default($clientAmount > 0)
                                ->live(),

                            Forms\Components\Textarea::make('approval_notes')
                                ->label('Approval Notes')
                                ->placeholder('Any special instructions or notes...')
                                ->rows(2),
                        ];
                    })
                    ->action(function (Invoice $record, array $data) {
                        \DB::transaction(function () use ($record, $data) {
                            $sendToCashier = (bool) ($data['send_to_cashier'] ?? false);

                            // Mark invoice approved
                            $record->update([
                                'status'              => 'approved',
                                'billing_approved'    => true,
                                'requires_cashier'    => $sendToCashier,
                                'approval_notes'      => $data['approval_notes'] ?? null,
                                'approved_by'         => auth()->id(),
                                'approved_at'         => now(),
                                'sponsor_claim_status' => 'approved',
                            ]);

                            // Create insurance claim if sponsor amount > 0
                            if (($record->total_sponsor_amount ?? 0) > 0) {
                                $claimService = new InsuranceClaimService();
                                $claim = $claimService->createClaimFromInvoice($record);

                                \Log::info('Insurance claim created', [
                                    'claim_id'     => $claim->id,
                                    'claim_number' => $claim->claim_number,
                                    'invoice_id'   => $record->id,
                                ]);
                            }

                            // Update service bookings to confirmed
                            $record->visit->serviceBookings()->update([
                                'status'         => 'confirmed',
                                'payment_status' => 'pending',
                            ]);

                            if ($sendToCashier) {
                                // Route to cashier — cashier will process remaining client payment
                                $record->visit->update([
                                    'current_stage'            => 'cashier',
                                    'current_stage_started_at' => now(),
                                ]);

                                $message = "Approved! Client routed to Cashier for KES "
                                    . number_format($record->total_client_amount ?? 0, 2);
                            } else {
                                // Billing admin fully processed — skip cashier, go to service
                                $record->update([
                                    'payment_status'       => 'paid',
                                    'client_payment_status' => 'waived',
                                    'payment_verified_at'  => now(),
                                ]);

                                $record->visit->update([
                                    'current_stage'            => 'service',
                                    'current_stage_started_at' => now(),
                                    'payment_verified_at'      => now(),
                                ]);

                                $routingService = new PaymentRoutingService();
                                $queueResult    = $routingService->createServiceQueues($record->visit);

                                if (! $queueResult['success']) {
                                    \Log::error('Queue creation failed after billing admin approval', [
                                        'invoice_id' => $record->id,
                                        'error'      => $queueResult['error'] ?? 'Unknown error',
                                    ]);
                                }

                                $message = "Approved! Client routed to Service Point"
                                    . ($queueResult['success']
                                        ? " ({$queueResult['queues_created']} queue(s) created)"
                                        : " (queue creation failed — add manually)");
                            }

                            Notification::make()
                                ->success()
                                ->title('Invoice Approved')
                                ->body($message)
                                ->duration(10000)
                                ->send();
                        });
                    }),

                // Reject Invoice
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Invoice $record) => in_array($record->status, ['pending', 'verified']))
                    ->requiresConfirmation()
                    ->modalHeading('Reject Invoice')
                    ->modalDescription('This will reject the invoice and return the client to intake for correction.')
                    ->modalSubmitActionLabel('Reject Invoice')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->placeholder('Explain why this invoice is being rejected...')
                            ->rows(3),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                            'rejected_by' => auth()->id(),
                            'rejected_at' => now(),
                        ]);

                        // Return to intake
                        $record->visit->update([
                            'current_stage' => 'intake',
                            'current_stage_started_at' => now(),
                        ]);

                        Notification::make()
                            ->danger()
                            ->title('Invoice Rejected')
                            ->body("Invoice {$record->invoice_number} rejected. Client returned to intake.")
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn(Invoice $record) => $record->status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Bulk Verify
                    Tables\Actions\BulkAction::make('bulk_verify')
                        ->label('Bulk Verify')
                        ->icon('heroicon-o-check-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Verify Selected Invoices')
                        ->modalDescription('Mark selected invoices as verified.')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'pending') {
                                    $record->update([
                                        'status' => 'verified',
                                        'verification_status' => 'verified',
                                        'verified_by' => auth()->id(),
                                        'verified_at' => now(),
                                    ]);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Bulk Verification Complete')
                                ->body("{$count} invoice(s) verified successfully.")
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Invoice Summary')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_amount')
                                    ->label('Total Amount')
                                    ->money('KES')
                                    ->size('lg')
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('total_sponsor_amount')
                                    ->label('Sponsor Pays')
                                    ->money('KES')
                                    ->size('lg')
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('total_client_amount')
                                    ->label('Client Pays')
                                    ->money('KES')
                                    ->size('lg')
                                    ->color('success'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Client Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('client.full_name')
                            ->label('Client Name'),
                        Infolists\Components\TextEntry::make('client.phone')
                            ->label('Phone'),
                        Infolists\Components\TextEntry::make('visit.visit_number')
                            ->label('Visit Number'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Payment Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->badge(),
                        Infolists\Components\TextEntry::make('insuranceProvider.name')
                            ->label('Insurance Provider'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Invoice Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('Services')
                            ->schema([
                                Infolists\Components\TextEntry::make('service.name')
                                    ->label('Service'),
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Qty'),
                                Infolists\Components\TextEntry::make('unit_price')
                                    ->money('KES')
                                    ->label('Unit Price'),
                                Infolists\Components\TextEntry::make('subtotal')
                                    ->money('KES')
                                    ->label('Subtotal'),
                                Infolists\Components\TextEntry::make('sponsor_amount')
                                    ->money('KES')
                                    ->label('Sponsor')
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('client_amount')
                                    ->money('KES')
                                    ->label('Client')
                                    ->color('success'),
                            ])
                            ->columns(6),
                    ]),

                Infolists\Components\Section::make('Verification & Approval')
                    ->schema([
                        Infolists\Components\TextEntry::make('verification_notes')
                            ->label('Verification Notes')
                            ->default('Not verified yet'),
                        Infolists\Components\TextEntry::make('approval_notes')
                            ->label('Approval Notes')
                            ->default('Not approved yet'),
                        Infolists\Components\TextEntry::make('verified_at')
                            ->label('Verified At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->visible(
                        fn(Invoice $record) =>
                        $record->verified_at || $record->approved_at
                    ),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillings::route('/'),
            'view' => Pages\ViewBilling::route('/{record}'),
            'edit' => Pages\EditBilling::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
