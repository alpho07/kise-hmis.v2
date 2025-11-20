<?php

namespace App\Filament\Resources\ReceptionResource\Widgets;

use App\Models\Visit;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ReceptionQueueStatus extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Visit::query()
                    ->with(['client', 'checkedInBy'])
                    ->where('current_stage', 'reception')
                    ->whereDate('check_in_time', today())
                    ->orderByDesc('is_emergency')
                    ->orderBy('check_in_time')
            )
            ->columns([
                Tables\Columns\TextColumn::make('position')
                    ->label('#')
                    ->getStateUsing(fn ($record, $rowLoop) => $rowLoop->iteration)
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state <= 3 => 'success',
                        $state <= 6 => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('visit_number')
                    ->label('Visit #')
                    ->weight('semibold')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn ($record) => $record->client->uci),

                Tables\Columns\TextColumn::make('visit_purpose')
                    ->label('Purpose')
                    ->badge()
                    ->color('info'),

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
                    ->getStateUsing(fn ($record) => $record->check_in_time->diffForHumans())
                    ->badge()
                    ->color(function ($record) {
                        $minutes = $record->check_in_time->diffInMinutes(now());
                        if ($minutes > 45) return 'danger';
                        if ($minutes > 30) return 'warning';
                        return 'success';
                    }),

                Tables\Columns\BadgeColumn::make('service_available')
                    ->label('Status')
                    ->colors([
                        'success' => 'yes',
                        'danger' => 'no',
                    ])
                    ->formatStateUsing(fn ($state) => $state === 'yes' ? 'Ready' : 'Deferred')
                    ->icon(fn ($state) => $state === 'yes' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('checkedInBy.name')
                    ->label('By')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('send_to_triage')
                    ->label('Send to Triage')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->service_available === 'yes')
                    ->action(function ($record) {
                        $record->completeStage();
                        $record->moveToStage('triage');
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Client sent to triage')
                            ->send();
                    })
                    ->requiresConfirmation(),

                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.admin.resources.receptions.view', $record)),
            ])
            ->poll('10s')
            ->heading('Current Reception Queue')
            ->description('Live queue - auto-refreshes every 10 seconds')
            ->emptyStateHeading('No clients at reception')
            ->emptyStateDescription('All clear! Clients will appear here when they check in.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}