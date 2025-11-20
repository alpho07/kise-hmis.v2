<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    protected static ?string $title = 'Visit History';

    protected static ?string $icon = 'heroicon-o-calendar-days';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('visit_details')
                    ->label('Visit Details')
                    ->content(fn ($record) => 'This visit is managed through the Visit Resource. Use the "View Visit" button to see full details.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('visit_number')
            ->columns([
                Tables\Columns\TextColumn::make('visit_number')
                    ->label('Visit #')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-o-hashtag')
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('visit_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'initial',
                        'success' => 'follow_up',
                        'warning' => 'emergency',
                        'info' => 'review',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),

                Tables\Columns\TextColumn::make('check_in_time')
                    ->label('Check-In')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->icon('heroicon-o-arrow-right-on-rectangle'),

                Tables\Columns\TextColumn::make('check_out_time')
                    ->label('Check-Out')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('In progress')
                    ->icon('heroicon-o-arrow-left-on-rectangle'),

                Tables\Columns\BadgeColumn::make('current_stage')
                    ->label('Current Stage')
                    ->colors([
                        'gray' => 'reception',
                        'info' => 'triage',
                        'primary' => 'intake',
                        'warning' => 'billing',
                        'success' => 'service',
                        'danger' => 'completed',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->icon('heroicon-o-building-office')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('visit_purpose')
                    ->label('Purpose')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->visit_purpose)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serviceBookings_count')
                    ->label('Services')
                    ->counts('serviceBookings')
                    ->icon('heroicon-o-briefcase')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('visit_type')
                    ->options([
                        'initial' => 'Initial Visit',
                        'follow_up' => 'Follow-up',
                        'emergency' => 'Emergency',
                        'review' => 'Review',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('current_stage')
                    ->options([
                        'reception' => 'Reception',
                        'triage' => 'Triage',
                        'intake' => 'Intake',
                        'billing' => 'Billing',
                        'service' => 'Service',
                        'completed' => 'Completed',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->label('Today\'s Visits')
                    ->query(fn ($query) => $query->whereDate('check_in_time', today())),

                Tables\Filters\Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn ($query) => $query->whereBetween('check_in_time', [now()->startOfWeek(), now()->endOfWeek()])),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn ($query) => $query->whereMonth('check_in_time', now()->month)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('new_visit')
                    ->label('New Visit')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->url(fn ($livewire) => route('filament.admin.resources.visits.create', [
                        'client' => $livewire->ownerRecord->id
                    ])),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View Visit')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn ($record) => route('filament.admin.resources.visits.view', $record)),

                Tables\Actions\Action::make('continue')
                    ->label('Continue')
                    ->icon('heroicon-o-arrow-right')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'in_progress')
                    ->url(fn ($record) => route('filament.admin.resources.visits.edit', $record)),
            ])
            ->bulkActions([
                // No bulk actions for visits - too sensitive
            ])
            ->emptyStateActions([
                Tables\Actions\Action::make('create_first_visit')
                    ->label('Create First Visit')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn ($livewire) => route('filament.admin.resources.visits.create', [
                        'client' => $livewire->ownerRecord->id
                    ])),
            ])
            ->emptyStateHeading('No visits yet')
            ->emptyStateDescription('Create the first visit for this client.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->defaultSort('check_in_time', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}