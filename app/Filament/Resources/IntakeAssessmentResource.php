<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntakeAssessmentResource\Pages;
use App\Models\IntakeAssessment;
use App\Models\Visit;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class IntakeAssessmentResource extends Resource
{
    protected static ?string $model = IntakeAssessment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Intake Assessment';

    protected static ?string $navigationGroup = 'Clinical Workflow';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Client Intake Assessment')
                ->icon('heroicon-o-identification')
                ->description('Confirm client details and begin intake assessment')
                ->extraAttributes(['class' => 'bg-gradient-to-br from-primary-50 via-white to-primary-100/60 rounded-xl border border-primary-100 shadow-sm p-6'])
                ->schema([
                    Forms\Components\TextInput::make('service_required')
                        ->label('Service Required')
                        ->prefixIcon('heroicon-o-briefcase')
                        ->placeholder('e.g. Therapy, Counseling, Evaluation')
                        ->required(),

                    Forms\Components\Textarea::make('presenting_complaint')
                        ->label('Presenting Complaint')
                        ->placeholder('Describe the main problem or reason for referral')
                        ->rows(3)
                        ->maxLength(500),

                    Forms\Components\Select::make('referral_source')
                        ->label('Referral Source')
                        ->prefixIcon('heroicon-o-arrow-right-circle')
                        ->options([
                            'self' => 'Self Referral',
                            'facility' => 'Health Facility',
                            'school' => 'School',
                            'organization' => 'Organization',
                            'court' => 'Court / Legal',
                            'other' => 'Other',
                        ])
                        ->placeholder('Select source')
                        ->searchable(),

                    Forms\Components\Textarea::make('intake_notes')
                        ->label('Additional Notes')
                        ->rows(3)
                        ->placeholder('Add extra details or observations during intake'),
                ]),

                Forms\Components\Section::make('Service Selection & Billing')
                    ->description('Select services and preview charges')
                    ->schema([
                        Forms\Components\CheckboxList::make('selected_services')
                            ->label('Select Services Required')
                            ->options(Service::query()->pluck('name', 'id'))
                            ->columns(3)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                self::calculateQuote($state, $set, $get);
                            })
                            ->required()
                            ->helperText('Select all services the client needs'),

                        Forms\Components\Placeholder::make('service_breakdown')
                            ->label('')
                            ->content(function (callable $get) {
                                return self::renderServiceBreakdown($get('selected_services'), $get);
                            })
                            ->visible(fn(callable $get) => !empty($get('selected_services'))),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(fn(callable $get) => 'KES ' . number_format($get('quote_subtotal') ?? 0, 2)),

                                Forms\Components\Placeholder::make('tax_display')
                                    ->label('Tax (16%)')
                                    ->content(fn(callable $get) => 'KES ' . number_format($get('quote_tax') ?? 0, 2)),

                                Forms\Components\Placeholder::make('total_display')
                                    ->label('Total Amount')
                                    ->content(fn(callable $get) => 'KES ' . number_format($get('quote_total') ?? 0, 2))
                                    ->extraAttributes(['class' => 'text-xl font-bold text-success-600']),
                            ])
                            ->visible(fn(callable $get) => !empty($get('selected_services'))),

                        Forms\Components\Hidden::make('quote_subtotal'),
                        Forms\Components\Hidden::make('quote_tax'),
                        Forms\Components\Hidden::make('quote_total'),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Billing Route Selection')
                    ->description('Choose how to process billing for this client')
                    ->schema([
                        Forms\Components\Radio::make('billing_route')
                            ->label('Send Client To')
                            ->options([
                                'billing' => 'Billing Department (for validation & insurance verification)',
                                'cashier' => 'Cashier (direct payment - skip billing)',
                            ])
                            ->descriptions([
                                'billing' => 'Billing officer will verify insurance, modify items if needed, and approve before payment',
                                'cashier' => 'Client proceeds directly to cashier for immediate payment',
                            ])
                            ->required()
                            ->default('billing')
                            ->inline(false)
                            ->reactive(),

                        Forms\Components\Select::make('primary_payment_method')
                            ->label('Expected Payment Method')
                            ->options([
                                'sha' => 'SHA (Social Health Authority)',
                                'ncpwd' => 'NCPWD',
                                'insurance' => 'Private Insurance',
                                'cash' => 'Cash',
                                'mpesa' => 'M-PESA',
                                'credit' => 'Credit Account',
                                'mixed' => 'Multiple Methods',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Primary payment method (can be modified at billing/cashier)'),

                        Forms\Components\Textarea::make('billing_notes')
                            ->label('Notes for Billing/Cashier')
                            ->rows(2)
                            ->placeholder('Any special instructions for billing or payment...')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(callable $get) => !empty($get('selected_services')))
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Position / Queue #
                Tables\Columns\TextColumn::make('queue_position')
                    ->label('#')
                    ->getStateUsing(fn($record, $rowLoop) => $rowLoop->iteration)
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state <= 3 => 'success',
                        $state <= 6 => 'warning',
                        default => 'gray',
                    })
                    ->size('lg')
                    ->weight('bold'),

                // Visit Number
                Tables\Columns\TextColumn::make('visit_number')
                    ->label('Visit #')
                    ->icon('heroicon-o-hashtag')
                    ->searchable()
                    ->copyable()
                    ->color('primary')
                    ->weight('semibold'),

                // Avatar + Client
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\ViewColumn::make('client_avatar')
                            ->view('filament.tables.columns.client-avatar'),
                    ]),
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('client.full_name')
                            ->label('Client Name')
                            ->icon('heroicon-o-user')
                            ->weight('semibold')
                            ->searchable(['first_name', 'last_name'])
                            ->description(fn($record) => implode(' • ', array_filter([
                                $record->client->age ? "{$record->client->age}y" : null,
                                ucfirst($record->client->gender),
                                $record->client->phone_primary,
                            ]))),
                    ]),
                ])->extraAttributes(['class' => 'rounded-lg bg-white/70 backdrop-blur-sm p-2 shadow-sm border border-gray-100']),

                // Type Badge
                Tables\Columns\BadgeColumn::make('visit_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'initial',
                        'success' => 'follow_up',
                        'warning' => 'emergency',
                        'info' => 'review',
                    ])
                    ->formatStateUsing(fn(string $state) => ucwords(str_replace('_', ' ', $state))),

                // Purpose
                Tables\Columns\TextColumn::make('visit_purpose')
                    ->label('Purpose')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(string $state) => ucfirst($state)),

                // Risk Level
                Tables\Columns\BadgeColumn::make('triage.risk_level')
                    ->label('Triage Risk')
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger' => 'high',
                    ])
                    ->formatStateUsing(fn($state) => ucfirst($state ?? 'N/A')),

                // Wait Time
                Tables\Columns\TextColumn::make('wait_time')
                    ->label('Waiting')
                    ->getStateUsing(function ($record) {
                        $intakeStage = $record->stages()
                            ->where('stage', 'intake')
                            ->whereNull('completed_at')
                            ->latest()
                            ->first();
                        if ($intakeStage) {
                            $minutes = $intakeStage->started_at->diffInMinutes(now());
                            if ($minutes < 60) {
                                return "{$minutes} min";
                            }
                            return $intakeStage->started_at->diffForHumans();
                        }
                        return $record->check_in_time->diffForHumans();
                    })
                    ->badge()
                    ->icon('heroicon-o-clock')
                    ->color(function ($record) {
                        $intakeStage = $record->stages()
                            ->where('stage', 'intake')
                            ->whereNull('completed_at')
                            ->latest()
                            ->first();
                        if ($intakeStage) {
                            $minutes = $intakeStage->started_at->diffInMinutes(now());
                            if ($minutes > 60) return 'danger';
                            if ($minutes > 30) return 'warning';
                        }
                        return 'success';
                    }),

                // Intake Status
                Tables\Columns\IconColumn::make('intakeAssessment')
                    ->label('Status')
                    ->getStateUsing(fn($record) => $record->intakeAssessment()->exists())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn($record) => $record->intakeAssessment()->exists() ? 'Completed' : 'Pending'),

                Tables\Columns\TextColumn::make('check_in_time')
                    ->label('Check-In')
                    ->dateTime('H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('pending_only')
                    ->label('Pending Intake Only')
                    ->query(fn(Builder $query) => $query->doesntHave('intakeAssessment'))
                    ->default(),

                Tables\Filters\Filter::make('completed')
                    ->label('Completed Intake')
                    ->query(fn(Builder $query) => $query->has('intakeAssessment')),

                Tables\Filters\SelectFilter::make('visit_type')
                    ->options([
                        'initial' => 'Initial',
                        'follow_up' => 'Follow-up',
                        'emergency' => 'Emergency',
                        'review' => 'Review',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('start_intake')
                    ->label('Start Intake')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('success')
                    ->visible(fn($record) => !$record->intakeAssessment()->exists())
                    ->url(fn($record) => route('filament.admin.resources.intake-assessments.create', ['visit' => $record->id]))
                    ->button()
                    ->tooltip('Begin Intake Assessment'),

                Tables\Actions\Action::make('view_intake')
                    ->label('View Intake')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->visible(fn($record) => $record->intakeAssessment()->exists())
                    ->url(fn($record) => route('filament.admin.resources.intake-assessments.view', ['record' => $record->intakeAssessment->id])),

                Tables\Actions\Action::make('view_client')
                    ->label('Client Profile')
                    ->icon('heroicon-o-user')
                    ->color('info')
                    ->url(fn($record) => route('filament.admin.resources.clients.view', $record->client_id))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('view_triage')
                    ->label('View Triage')
                    ->icon('heroicon-o-heart')
                    ->color('gray')
                    ->visible(fn($record) => $record->triage()->exists())
                    ->url(fn($record) => route('filament.admin.resources.triages.view', $record->triage->id))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('🎯 No Clients in Intake Queue')
            ->emptyStateDescription('Clients appear here automatically after triage clearance.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->defaultSort('check_in_time', 'asc')
            ->poll('15s')
            ->striped()
            ->persistFiltersInSession()
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ]);
    }

    /**
     * Calculate quote from selected services
     */
    protected static function calculateQuote($serviceIds, callable $set, callable $get): void
    {
        if (empty($serviceIds)) {
            $set('quote_subtotal', 0);
            $set('quote_tax', 0);
            $set('quote_total', 0);
            return;
        }

        $services = Service::whereIn('id', $serviceIds)->get();
        $subtotal = $services->sum('price');
        $tax = $subtotal * 0.16; // 16% VAT
        $total = $subtotal + $tax;

        $set('quote_subtotal', $subtotal);
        $set('quote_tax', $tax);
        $set('quote_total', $total);
    }

    /**
     * Render service breakdown view
     */
    protected static function renderServiceBreakdown($serviceIds, callable $get): string
    {
        if (empty($serviceIds)) {
            return '';
        }

        $services = Service::whereIn('id', $serviceIds)->get();
        
        $html = '<div class="space-y-2 p-4 bg-gray-50 rounded-lg">';
        $html .= '<h4 class="font-semibold text-sm text-gray-700 mb-2">Selected Services:</h4>';
        
        foreach ($services as $service) {
            $html .= '<div class="flex justify-between items-center text-sm">';
            $html .= '<span class="text-gray-600">' . $service->name . '</span>';
            $html .= '<span class="font-medium text-gray-900">KES ' . number_format($service->price, 2) . '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return new \Illuminate\Support\HtmlString($html);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntakeAssessments::route('/'),
            'create' => Pages\CreateIntakeAssessment::route('/create'),
            'view' => Pages\ViewIntakeAssessment::route('/{record}'),
            'edit' => Pages\EditIntakeAssessment::route('/{record}/edit'),
        ];
    }
}