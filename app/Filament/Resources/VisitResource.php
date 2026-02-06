<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitResource\Pages;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'All Visits';

    protected static ?string $navigationGroup = 'Client Management';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Basic visit form schema - can be expanded
                Forms\Components\Section::make('Visit Information')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'first_name')
                            ->searchable()
                            ->required(),
                        
                        Forms\Components\TextInput::make('visit_number')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\Select::make('visit_type')
                            ->options([
                                'initial' => 'Initial',
                                'follow_up' => 'Follow-up',
                                'emergency' => 'Emergency',
                                'review' => 'Review',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('visit_number')
                    ->label('Visit #')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-o-hashtag')
                    ->copyable(),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-identification'),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-o-user'),

                Tables\Columns\BadgeColumn::make('visit_type')
                    ->colors([
                        'primary' => 'initial',
                        'success' => 'follow_up',
                        'warning' => 'emergency',
                        'info' => 'review',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),

                Tables\Columns\BadgeColumn::make('current_stage')
                    ->label('Current Stage')
                    ->colors([
                        'gray' => 'reception',
                        'info' => 'triage',
                        'primary' => 'intake',
                        'warning' => 'billing',
                        'orange' => 'payment',
                        'success' => 'service',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->icon(fn (string $state): string => match($state) {
                        'reception' => 'heroicon-o-user-group',
                        'triage' => 'heroicon-o-heart',
                        'intake' => 'heroicon-o-clipboard-document-check',
                        'billing' => 'heroicon-o-currency-dollar',
                        'payment' => 'heroicon-o-credit-card',
                        'service' => 'heroicon-o-wrench-screwdriver',
                        default => 'heroicon-o-clock',
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),

                Tables\Columns\TextColumn::make('check_in_time')
                    ->label('Check-In')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_out_time')
                    ->label('Check-Out')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('In progress')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('current_stage')
                    ->options([
                        'reception' => 'Reception',
                        'triage' => 'Triage',
                        'intake' => 'Intake',
                        'billing' => 'Billing',
                        'payment' => 'Payment',
                        'service' => 'Service',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query) => $query->whereDate('check_in_time', today()))
                    ->default(),
            ])
            ->actions([
                // View Client Profile
                Tables\Actions\Action::make('view_client')
                    ->label('View Client')
                    ->icon('heroicon-o-user')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.clients.view', $record->client_id))
                    ->openUrlInNewTab(),

                // STAGE-SPECIFIC ACTIONS
                
                // Reception Stage
                Tables\Actions\Action::make('send_to_triage')
                    ->label('Send to Triage')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->current_stage === 'reception' && $record->service_available === 'yes')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->completeStage();
                        $record->moveToStage('triage');
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Sent to Triage')
                            ->body("Visit {$record->visit_number} moved to triage queue")
                            ->send();
                    }),

                // Triage Stage
                Tables\Actions\Action::make('start_triage')
                    ->label('Start Triage')
                    ->icon('heroicon-o-heart')
                    ->color('info')
                    ->visible(fn ($record) => $record->current_stage === 'triage' && !$record->triage()->exists())
                    ->url(fn ($record) => route('filament.admin.resources.triages.create', ['visit' => $record->id])),

                Tables\Actions\Action::make('view_triage')
                    ->label('View Triage')
                    ->icon('heroicon-o-heart')
                    ->color('gray')
                    ->visible(fn ($record) => $record->triage()->exists())
                    ->url(fn ($record) => route('filament.admin.resources.triages.view', $record->triage->id))
                    ->openUrlInNewTab(),

                // Intake Stage - THE KEY ACTION!
                Tables\Actions\Action::make('complete_intake')
                    ->label('Complete Intake')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->visible(fn ($record) => $record->current_stage === 'intake' && !$record->intakeAssessment()->exists())
                    ->url(fn ($record) => route('filament.admin.resources.intake-assessments.create', ['visit' => $record->id]))
                    ->button(),

                Tables\Actions\Action::make('view_intake')
                    ->label('View Intake')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('gray')
                    ->visible(fn ($record) => $record->intakeAssessment()->exists())
                    ->url(fn ($record) => route('filament.admin.resources.intake-assessments.view', $record->intakeAssessment->id))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('edit_intake')
                    ->label('Edit Intake')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn ($record) => $record->current_stage === 'intake' && $record->intakeAssessment()->exists())
                    ->url(fn ($record) => route('filament.admin.resources.intake-assessments.edit', $record->intakeAssessment->id)),

                // Billing Stage
                Tables\Actions\Action::make('create_invoice')
                    ->label('Create Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->visible(fn ($record) => $record->current_stage === 'billing' && !$record->invoices()->exists())
                    //->url(fn ($record) => route('filament.admin.resources.invoices.create', ['visit' => $record->id]))
                    ->button(),

                Tables\Actions\Action::make('view_invoice')
                    ->label('View Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn ($record) => $record->invoices()->exists())
                    ->url(fn ($record) => route('filament.admin.resources.invoices.view', $record->invoices()->latest()->first()->id))
                    ->openUrlInNewTab(),

                // Payment Stage
                Tables\Actions\Action::make('process_payment')
                    ->label('Process Payment')
                    ->icon('heroicon-o-credit-card')
                    ->color('orange')
                    ->visible(fn ($record) => $record->current_stage === 'payment')
                    ->url(fn ($record) => route('filament.admin.resources.payments.create', ['visit' => $record->id]))
                    ->button(),

                // Service Stage
                Tables\Actions\Action::make('view_queues')
                    ->label('View Queues')
                    ->icon('heroicon-o-queue-list')
                    ->color('success')
                    ->visible(fn ($record) => $record->current_stage === 'service')
                    ->url(fn ($record) => route('filament.admin.resources.service-queues.index', ['visit' => $record->id])),

                // General View Action
                Tables\Actions\ViewAction::make()
                    ->label('Details'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Bulk actions if needed
                ]),
            ])
            ->defaultSort('check_in_time', 'desc')
            ->poll('30s');
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
            'index' => Pages\ListVisits::route('/'),
            'create' => Pages\CreateVisit::route('/create'),
            'view' => Pages\ViewVisit::route('/{record}'),
            'edit' => Pages\EditVisit::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'in_progress')
            ->whereDate('check_in_time', today())
            ->count();
    }
}