<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntakeAssessmentResource\Pages;
use App\Models\Visit;
use App\Models\IntakeAssessment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class IntakeAssessmentResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Intake Queue';
    protected static ?string $modelLabel = 'Intake Assessment';
    protected static ?string $pluralModelLabel = 'Intake Queue';
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
                            ->maxLength(500)
                            ->prefixIcon('heroicon-o-chat-bubble-bottom-center-text'),

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
                            ->placeholder('Add extra details or observations during intake')
                            ->prefixIcon('heroicon-o-pencil-square'),
                    ]),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntakeAssessments::route('/'),
            'create' => Pages\CreateIntakeAssessment::route('/create'),
            'view' => Pages\ViewIntakeAssessment::route('/{record}'),
            'edit' => Pages\EditIntakeAssessment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['client', 'stages', 'triage', 'intakeAssessment'])
            ->where('current_stage', 'intake')
            ->whereDate('check_in_time', today())
            ->whereNull('check_out_time');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('current_stage', 'intake')
            ->whereDate('check_in_time', today())
            ->whereNull('check_out_time')
            ->doesntHave('intakeAssessment')
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = (int) static::getNavigationBadge();
        return $count > 10 ? 'danger' : ($count > 5 ? 'warning' : 'success');
    }

    public static function canCreate(): bool
    {
        return false; // Intake entries originate from triage
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['intake_officer', 'assessment_coordinator', 'admin', 'super_admin']);
    }
}
