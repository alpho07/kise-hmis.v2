<?php

namespace App\Filament\Resources;

use App\Models\AssessmentFormResponse;
use App\Models\AssessmentFormSchema;
use App\Services\DynamicFormBuilder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\DynamicAssessmentResource\Pages;

class DynamicAssessmentResource extends Resource
{
    protected static ?string $model = AssessmentFormResponse::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Assessments';

    protected static ?string $navigationGroup = 'Clinical';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        // Get form slug from URL parameter or default to intake-assessment
        $formSlug = request()->query('form_slug', 'intake-assessment');
        
        // Get the form schema
        $formSchema = AssessmentFormSchema::where('slug', $formSlug)
            ->where('is_active', true)
            ->first();

        if (!$formSchema) {
            return $form->schema([
                Forms\Components\Placeholder::make('error')
                    ->content('Form schema not found. Please check the form_slug parameter.')
                    ->columnSpanFull(),
            ]);
        }

        // Build form dynamically using DynamicFormBuilder
        $components = DynamicFormBuilder::buildForm($formSchema);

        return $form
            ->schema([
                // Hidden fields for context
                Forms\Components\Hidden::make('form_schema_id')
                    ->default($formSchema->id),

                Forms\Components\Hidden::make('visit_id')
                    ->default(fn () => request()->query('visit')),

                Forms\Components\Hidden::make('client_id')
                    ->default(function () {
                        $visitId = request()->query('visit');
                        if ($visitId) {
                            $visit = \App\Models\Visit::find($visitId);
                            return $visit?->client_id;
                        }
                        return null;
                    }),

                Forms\Components\Hidden::make('branch_id')
                    ->default(fn () => auth()->user()->branch_id ?? 1),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),

                // Dynamic form sections
                ...$components,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('schema.name')
                    ->label('Form Type')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->client->uci ?? 'No UCI'),

                Tables\Columns\TextColumn::make('visit.visit_number')
                    ->label('Visit')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'primary' => 'submitted',
                        'danger' => 'archived',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('completion_percentage')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->color(fn ($state) => match(true) {
                        $state >= 100 => 'success',
                        $state >= 75 => 'primary',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('form_schema_id')
                    ->label('Form Type')
                    ->options(fn () => AssessmentFormSchema::active()->pluck('name', 'id'))
                    ->multiple(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'submitted' => 'Submitted',
                        'archived' => 'Archived',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('completed_today')
                    ->label('Completed Today')
                    ->query(fn (Builder $query) => $query->whereDate('completed_at', today())),

                Tables\Filters\Filter::make('my_assessments')
                    ->label('My Assessments')
                    ->query(fn (Builder $query) => $query->where('created_by', auth()->id())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('complete')
                    ->label('Mark Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'completed')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'completion_percentage' => 100,
                        ]);
                    }),

                Tables\Actions\Action::make('view_responses')
                    ->label('View Responses')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn ($record) => 'Responses: ' . $record->schema->name)
                    ->modalContent(fn ($record) => view('filament.modals.view-assessment-responses', [
                        'responses' => $record->response_data,
                        'schema' => $record->schema->schema,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update([
                                'status' => 'completed',
                                'completed_at' => now(),
                                'completion_percentage' => 100,
                            ]);
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Add relations if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDynamicAssessments::route('/'),
            'create' => Pages\CreateDynamicAssessment::route('/create'),
            'view' => Pages\ViewDynamicAssessment::route('/{record}'),
            'edit' => Pages\EditDynamicAssessment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'in_progress')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}