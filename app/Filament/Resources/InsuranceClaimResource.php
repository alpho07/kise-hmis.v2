<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsuranceClaimResource\Pages;
use App\Models\InsuranceClaim;
use App\Services\InsuranceClaimService;
use App\Enums\ClaimStatusEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class InsuranceClaimResource extends Resource
{
    protected static ?string $model = InsuranceClaim::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Insurance Claims';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Claim Information')
                    ->schema([
                        Forms\Components\TextInput::make('claim_number')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\Select::make('insurance_provider_id')
                            ->relationship('provider', 'name')
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'first_name')
                            ->disabled(),
                        
                        Forms\Components\Select::make('status')
                            ->options(ClaimStatusEnum::class)
                            ->required()
                            ->disabled(fn ($operation) => $operation === 'create'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Financial Details')
                    ->schema([
                        Forms\Components\TextInput::make('claim_amount')
                            ->label('Claimed Amount')
                            ->numeric()
                            ->prefix('KES')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('approved_amount')
                            ->label('Approved Amount')
                            ->numeric()
                            ->prefix('KES')
                            ->disabled(fn ($operation) => $operation === 'create'),
                        
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('Paid Amount')
                            ->numeric()
                            ->prefix('KES')
                            ->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                        
                        Forms\Components\Textarea::make('rejection_reason')
                            ->visible(fn (Forms\Get $get) => $get('status') === ClaimStatusEnum::REJECTED->value)
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('claim_number')
                    ->label('Claim #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-document-text'),

                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Insurance Provider')
                    ->searchable()
                    ->icon('heroicon-o-shield-check'),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('claim_amount')
                    ->label('Claimed')
                    ->money('KES')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('KES')),

                Tables\Columns\TextColumn::make('approved_amount')
                    ->label('Approved')
                    ->money('KES')
                    ->color('success')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('KES')),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('KES')
                    ->color('info')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('KES')),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state) => ClaimStatusEnum::from($state)->label())
                    ->color(fn (string $state) => ClaimStatusEnum::from($state)->color())
                    ->icon(fn (string $state) => ClaimStatusEnum::from($state)->icon()),

                Tables\Columns\TextColumn::make('service_date')
                    ->label('Service Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ClaimStatusEnum::class)
                    ->multiple(),
                
                Tables\Filters\SelectFilter::make('insurance_provider_id')
                    ->relationship('provider', 'name')
                    ->label('Provider')
                    ->multiple(),
                
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('service_date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('service_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('submit')
                    ->label('Submit Claim')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (InsuranceClaim $record) => $record->status === ClaimStatusEnum::PENDING->value)
                    ->requiresConfirmation()
                    ->action(function (InsuranceClaim $record, InsuranceClaimService $service) {
                        $service->submitClaim($record);
                        
                        Notification::make()
                            ->success()
                            ->title('Claim Submitted')
                            ->body("Claim {$record->claim_number} has been submitted")
                            ->send();
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (InsuranceClaim $record) => 
                        in_array($record->status, [ClaimStatusEnum::SUBMITTED->value, ClaimStatusEnum::UNDER_REVIEW->value])
                    )
                    ->form([
                        Forms\Components\TextInput::make('approved_amount')
                            ->label('Approved Amount')
                            ->numeric()
                            ->prefix('KES')
                            ->required()
                            ->default(fn (InsuranceClaim $record) => $record->claim_amount),
                        
                        Forms\Components\Textarea::make('approval_notes')
                            ->rows(3),
                    ])
                    ->action(function (InsuranceClaim $record, array $data, InsuranceClaimService $service) {
                        $service->approveClaim($record, $data['approved_amount']);
                        
                        if (!empty($data['approval_notes'])) {
                            $record->update(['notes' => $data['approval_notes']]);
                        }
                        
                        Notification::make()
                            ->success()
                            ->title('Claim Approved')
                            ->body("Approved amount: KES " . number_format($data['approved_amount'], 2))
                            ->send();
                    }),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Record Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (InsuranceClaim $record) => 
                        $record->status === ClaimStatusEnum::APPROVED->value ||
                        $record->status === ClaimStatusEnum::PARTIALLY_PAID->value
                    )
                    ->form([
                        Forms\Components\TextInput::make('payment_amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->prefix('KES')
                            ->required()
                            ->maxValue(fn (InsuranceClaim $record) => $record->approved_amount - $record->paid_amount),
                        
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference')
                            ->required(),
                        
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (InsuranceClaim $record, array $data, InsuranceClaimService $service) {
                        $service->markAsPaid($record, $data['payment_amount']);
                        
                        Notification::make()
                            ->success()
                            ->title('Payment Recorded')
                            ->body("Paid: KES {$data['payment_amount']}")
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_submit')
                        ->label('Submit Claims')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function ($records, InsuranceClaimService $service) {
                            foreach ($records as $claim) {
                                if ($claim->status === ClaimStatusEnum::PENDING->value) {
                                    $service->submitClaim($claim);
                                }
                            }
                            
                            Notification::make()
                                ->success()
                                ->title('Claims Submitted')
                                ->body(count($records) . ' claims submitted successfully')
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInsuranceClaims::route('/'),
            'create' => Pages\CreateInsuranceClaim::route('/create'),
            'view' => Pages\ViewInsuranceClaim::route('/{record}'),
            'edit' => Pages\EditInsuranceClaim::route('/{record}/edit'),
        ];
    }
}