<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsuranceBatchInvoiceResource\Pages;
use App\Models\InsuranceBatchInvoice;
use App\Models\InsuranceProvider;
use App\Services\BatchInvoiceService;
use App\Enums\BatchInvoiceStatusEnum;
use App\Exports\BatchInvoiceExport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;

class InsuranceBatchInvoiceResource extends Resource
{
    protected static ?string $model = InsuranceBatchInvoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Batch Invoices';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Batch Details')
                    ->schema([
                        Forms\Components\TextInput::make('batch_number')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\Select::make('insurance_provider_id')
                            ->relationship('provider', 'name')
                            ->required()
                            ->disabled(fn ($operation) => $operation === 'edit'),
                        
                        Forms\Components\Select::make('status')
                            ->options(BatchInvoiceStatusEnum::class)
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Billing Period')
                    ->schema([
                        Forms\Components\DatePicker::make('billing_period_start')
                            ->required()
                            ->disabled(fn ($operation) => $operation === 'edit'),
                        
                        Forms\Components\DatePicker::make('billing_period_end')
                            ->required()
                            ->disabled(fn ($operation) => $operation === 'edit'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Financial Summary')
                    ->schema([
                        Forms\Components\TextInput::make('total_claims')
                            ->numeric()
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('KES')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('paid_amount')
                            ->numeric()
                            ->prefix('KES')
                            ->default(0),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Batch #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-document-duplicate'),

                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Insurance Provider')
                    ->searchable()
                    ->icon('heroicon-o-shield-check'),

                Tables\Columns\TextColumn::make('billing_period')
                    ->label('Period')
                    ->formatStateUsing(fn (InsuranceBatchInvoice $record) => 
                        $record->billing_period_start->format('M Y')
                    )
                    ->sortable(['billing_period_start']),

                Tables\Columns\TextColumn::make('total_claims')
                    ->label('Claims')
                    ->badge()
                    ->color('info')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('KES')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('KES')),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('KES')
                    ->color('success')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('KES')),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance')
                    ->money('KES')
                    ->color('danger')
                    ->state(fn (InsuranceBatchInvoice $record) => 
                        $record->total_amount - $record->paid_amount
                    ),

                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn (string $state) => BatchInvoiceStatusEnum::from($state)->label())
                    ->color(fn (string $state) => BatchInvoiceStatusEnum::from($state)->color()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Generated')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(BatchInvoiceStatusEnum::class)
                    ->multiple(),
                
                Tables\Filters\SelectFilter::make('insurance_provider_id')
                    ->relationship('provider', 'name')
                    ->label('Provider')
                    ->multiple(),
                
                Tables\Filters\Filter::make('billing_period')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Period From'),
                        Forms\Components\DatePicker::make('to')
                            ->label('Period To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => 
                                $q->whereDate('billing_period_start', '>=', $date)
                            )
                            ->when($data['to'], fn ($q, $date) => 
                                $q->whereDate('billing_period_end', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(function (InsuranceBatchInvoice $record, BatchInvoiceService $service) {
                        $path = $service->generatePDF($record);
                        
                        return response()->download($path);
                    }),

                Tables\Actions\Action::make('download_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->action(function (InsuranceBatchInvoice $record) {
                        return Excel::download(
                            new BatchInvoiceExport($record),
                            "batch-invoice-{$record->batch_number}.xlsx"
                        );
                    }),

                Tables\Actions\Action::make('mark_sent')
                    ->label('Mark as Sent')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (InsuranceBatchInvoice $record) => 
                        $record->status === BatchInvoiceStatusEnum::PENDING->value
                    )
                    ->requiresConfirmation()
                    ->action(function (InsuranceBatchInvoice $record) {
                        $record->update([
                            'status' => BatchInvoiceStatusEnum::SENT->value,
                            'sent_at' => now(),
                        ]);
                        
                        Notification::make()
                            ->success()
                            ->title('Batch Invoice Sent')
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generate_batch')
                    ->label('Generate New Batch')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('insurance_provider_id')
                            ->label('Insurance Provider')
                            ->options(InsuranceProvider::pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        
                        Forms\Components\DatePicker::make('month')
                            ->label('Billing Month')
                            ->displayFormat('F Y')
                            ->format('Y-m-d')
                            ->default(now()->subMonth()->startOfMonth())
                            ->required(),
                    ])
                    ->action(function (array $data, BatchInvoiceService $service) {
                        $provider = InsuranceProvider::find($data['insurance_provider_id']);
                        $date = \Carbon\Carbon::parse($data['month']);
                        
                        try {
                            $batch = $service->generateMonthlyBatch(
                                $provider,
                                $date->year,
                                $date->month
                            );
                            
                            Notification::make()
                                ->success()
                                ->title('Batch Invoice Generated')
                                ->body("{$batch->total_claims} claims totaling KES " . 
                                      number_format($batch->total_amount, 2))
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Generation Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInsuranceBatchInvoices::route('/'),
            'view' => Pages\ViewInsuranceBatchInvoice::route('/{record}'),
            'edit' => Pages\EditInsuranceBatchInvoice::route('/{record}/edit'),
        ];
    }
}