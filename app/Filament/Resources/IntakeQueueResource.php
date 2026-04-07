<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntakeQueueResource\Pages;
use App\Models\Visit;
use App\Models\IntakeAssessment;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;

class IntakeQueueResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Intake Queue';
    protected static ?string $modelLabel = 'Intake';
    protected static ?string $pluralModelLabel = 'Intake Queue';
    protected static ?string $navigationGroup = 'Clinical Workflow';
    protected static ?int $navigationSort = 3;

           public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole(['super_admin','admin','intake_officer']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('current_stage', 'intake')
            ->with(['client', 'triage'])
            ->orderBy('check_in_time', 'asc'); // FIFO: first checked in → first seen at intake
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('queue_position')
                    ->label('#')
                    ->badge()
                    ->getStateUsing(fn ($record, $rowLoop) => $rowLoop->iteration)
                    ->color(Color::Blue),

                Tables\Columns\TextColumn::make('visit_number')
                    ->label('Visit #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->color(Color::Blue)
                    ->icon('heroicon-o-ticket'),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client Name')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('semibold')
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('client_age_sex')
                    ->label('Age / Sex')
                    ->getStateUsing(function ($record) {
                        $age    = $record->client->age ?? 'N/A';
                        $gender = $record->client->gender ? strtoupper(substr($record->client->gender, 0, 1)) : 'N/A';
                        return "{$age} yrs / {$gender}";
                    }),

                Tables\Columns\TextColumn::make('visit_type')
                    ->label('Visit Type')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'emergency' => 'danger',
                        'urgent'    => 'warning',
                        'new'       => 'success',
                        'follow_up' => 'primary',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state ?? 'new'))),

                Tables\Columns\TextColumn::make('waiting_time')
                    ->label('Waiting')
                    ->getStateUsing(function ($record) {
                        $startTime = $record->current_stage_started_at ?? $record->check_in_time;
                        return $startTime->diffForHumans(null, true);
                    })
                    ->color(function ($record) {
                        $startTime = $record->current_stage_started_at ?? $record->check_in_time;
                        $minutes = $startTime->diffInMinutes(now());
                        
                        if ($minutes > 30) return Color::Red;
                        if ($minutes > 15) return Color::Orange;
                        return Color::Green;
                    })
                    ->icon('heroicon-o-clock')
                    ->sortable(),

                Tables\Columns\TextColumn::make('intake_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn($record) => IntakeAssessment::where('visit_id', $record->id)->exists()
                        ? 'in_progress' : 'pending'
                    )
                    ->color(fn($state) => match($state) {
                        'in_progress' => 'warning',
                        'pending'     => 'gray',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->icon(fn($state) => match($state) {
                        'in_progress' => 'heroicon-o-arrow-path',
                        default       => 'heroicon-o-clock',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('visit_type')
                    ->options([
                        'emergency' => 'Emergency',
                        'urgent' => 'Urgent',
                        'new' => 'New',
                        'follow_up' => 'Follow-up',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('start_intake')
                    ->label('Start Intake')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->button()
                    ->visible(fn($record) => !IntakeAssessment::where('visit_id', $record->id)->exists())
                    ->action(function ($record) {
                        $intake = IntakeAssessment::firstOrCreate(
                            ['visit_id' => $record->id],
                            [
                                'client_id'      => $record->client_id,
                                'branch_id'      => $record->branch_id ?? auth()->user()?->branch_id,
                                'assessed_by'    => auth()->id(),
                                'section_status' => [],
                            ]
                        );
                        return redirect(route('filament.admin.pages.intake-assessment-editor', ['intakeId' => $intake->id]));
                    }),

                Tables\Actions\Action::make('continue_intake')
                    ->label('Continue')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->button()
                    ->visible(function ($record) {
                        return \App\Models\IntakeAssessment::where('visit_id', $record->id)->exists();
                    })
                    ->url(function ($record) {
                        $intake = \App\Models\IntakeAssessment::where('visit_id', $record->id)->first();
                        return $intake
                            ? route('filament.admin.pages.intake-assessment-editor', ['intakeId' => $intake->id])
                            : '#';
                    })
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('check_in_time', 'asc')
            ->poll('30s')
            ->emptyStateHeading('No clients in intake queue')
            ->emptyStateDescription('Clients will appear here after completing triage and being moved to intake stage.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntakeQueues::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}