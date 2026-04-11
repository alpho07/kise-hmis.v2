<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TriageQueueResource\Pages;
use App\Models\Visit;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TriageQueueResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Triage Queue';

    protected static ?string $modelLabel = 'Triage';

    protected static ?string $pluralModelLabel = 'Triage Queue';

    protected static ?string $navigationGroup = 'Clinical Workflow';

    protected static ?int $navigationSort = 2;

      public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole(['super_admin','admin','triage_nurse']);
    }

    public static function form(Form $form): Form
    {
        // Form handled by TriageResource
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('queue_position')
                    ->label('#')
                    ->getStateUsing(fn ($record, $rowLoop) => $rowLoop->iteration)
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state <= 3 => 'danger',
                        $state <= 6 => 'warning',
                        default => 'gray',
                    })
                    ->size('lg')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('visit_number')
                    ->label('Visit #')
                    ->searchable()
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
                    ->weight('semibold')
                    ->icon('heroicon-o-user')
                    ->description(fn ($record) => implode(' • ', array_filter([
                        $record->client->age ? "{$record->client->age}y" : null,
                        ucfirst($record->client->gender),
                        $record->client->phone_primary,
                    ]))),

                Tables\Columns\BadgeColumn::make('visit_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'initial',
                        'success' => 'follow_up',
                        'warning' => 'emergency',
                        'info' => 'review',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),

                Tables\Columns\TextColumn::make('visit_purpose')
                    ->label('Purpose')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->description(fn ($record) => $record->purpose_notes ? \Str::limit($record->purpose_notes, 40) : null),

                Tables\Columns\IconColumn::make('is_emergency')
                    ->label('Priority')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->size('lg'),

                Tables\Columns\TextColumn::make('wait_time')
                    ->label('Waiting')
                    ->getStateUsing(function ($record) {
                        $triageStage = $record->stages()
                            ->where('stage', 'triage')
                            ->whereNull('completed_at')
                            ->latest()
                            ->first();
                        
                        if ($triageStage) {
                            $minutes = round($triageStage->started_at->diffInSeconds(now()) / 60, 2);
                            if ($minutes < 60) {
                                return number_format($minutes, 2) . ' min';
                            }
                            return $triageStage->started_at->diffForHumans();
                        }

                        return $record->check_in_time->diffForHumans();
                    })
                    ->badge()
                    ->color(function ($record) {
                        $triageStage = $record->stages()
                            ->where('stage', 'triage')
                            ->whereNull('completed_at')
                            ->latest()
                            ->first();
                        
                        if ($triageStage) {
                            $minutes = $triageStage->started_at->diffInSeconds(now()) / 60;
                            if ($minutes > 45) return 'danger';
                            if ($minutes > 30) return 'warning';
                        }
                        
                        return 'success';
                    })
                    ->icon('heroicon-o-clock'),

                Tables\Columns\TextColumn::make('check_in_time')
                    ->label('Check-In')
                    ->dateTime('H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('referral_source')
                    ->label('Referred By')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reception_notes')
                    ->label('Reception Notes')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->reception_notes)
                    ->toggleable()
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_emergency')
                    ->label('Emergency Cases')
                    ->placeholder('All visits')
                    ->trueLabel('Emergency only')
                    ->falseLabel('Regular visits'),

                Tables\Filters\SelectFilter::make('visit_type')
                    ->options([
                        'initial' => 'Initial',
                        'follow_up' => 'Follow-up',
                        'emergency' => 'Emergency',
                        'review' => 'Review',
                    ]),

                Tables\Filters\Filter::make('waiting_long')
                    ->label('Waiting > 30 min')
                    ->query(function (Builder $query) {
                        return $query->whereHas('stages', function ($q) {
                            $q->where('stage', 'triage')
                              ->whereNull('completed_at')
                              ->where('started_at', '<=', now()->subMinutes(30));
                        });
                    }),

                Tables\Filters\Filter::make('today')
                    ->label('Today\'s Queue')
                    ->query(fn (Builder $query) => $query->whereDate('check_in_time', today()))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('start_triage')
                    ->label('Start Triage')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->url(fn ($record) => route('filament.admin.resources.triages.create', [
                        'visit' => $record->id,
                    ]))
                    ->button(),

                Tables\Actions\Action::make('view_client')
                    ->label('View Client')
                    ->icon('heroicon-o-user')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.clients.view', $record->client_id))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('call_client')
                    ->label('Call Client')
                    ->icon('heroicon-o-megaphone')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Call Client for Triage')
                    ->modalDescription(fn ($record) => "Calling {$record->client->full_name} (Visit #{$record->visit_number})")
                    ->action(function ($record) {
                        // Log the call
                        activity()
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->log('Client called for triage');
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Client Called')
                            ->body("Called {$record->client->full_name} for triage")
                            ->send();
                    }),
            ])
            ->bulkActions([
                // No bulk actions - triage is individual
            ])
            ->emptyStateHeading('No clients in triage queue')
            ->emptyStateDescription('Clients will appear here when sent from reception')
            ->emptyStateIcon('heroicon-o-heart')
            ->defaultSort(fn (Builder $query) => $query
                ->orderByDesc('is_emergency') // Emergency first
                ->orderBy('check_in_time') // Then FIFO
            )
            ->poll('10s') // Auto-refresh every 10 seconds
            ->striped()
            ->persistFiltersInSession();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTriageQueues::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['client', 'stages', 'checkedInBy'])
            ->where('current_stage', 'triage')
            ->whereDate('check_in_time', today())
            ->whereNull('check_out_time');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('current_stage', 'triage')
            ->whereDate('check_in_time', today())
            ->whereNull('check_out_time')
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = (int) static::getNavigationBadge();
        
        // Check for long waits
        $longWaits = static::getModel()::where('current_stage', 'triage')
            ->whereDate('check_in_time', today())
            ->whereHas('stages', function ($q) {
                $q->where('stage', 'triage')
                  ->whereNull('completed_at')
                  ->where('started_at', '<=', now()->subMinutes(45));
            })
            ->count();
        
        if ($longWaits > 0 || $count > 15) {
            return 'danger';
        } elseif ($count > 8) {
            return 'warning';
        }
        
        return 'success';
    }

    public static function canCreate(): bool
    {
        return false; // Triage entries come from reception
    }

    public static function canViewAny(): bool
    {
        // Only nurses and admins can see triage queue
        return auth()->user()->hasRole(['nurse', 'triage_nurse', 'admin', 'super_admin']);
    }
}