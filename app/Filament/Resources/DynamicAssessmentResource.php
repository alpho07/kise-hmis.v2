<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicAssessmentResource\Pages;
use App\Models\AssessmentFormResponse;
use App\Models\AssessmentFormSchema;
use App\Models\Visit;
use App\Models\Service;
use App\Services\DynamicFormBuilder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Dynamic Assessment Resource
 * 
 * Handles ALL assessment forms dynamically using form slugs
 * Works with: Intake, Vision, Therapy, Audiology, Psychology, etc.
 * 
 * CRITICAL: Includes service selection & billing for intake assessments
 */
class DynamicAssessmentResource extends Resource
{
    protected static ?string $model = AssessmentFormResponse::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    
    protected static ?string $navigationLabel = 'Service Assessments';
    
    protected static ?string $modelLabel = 'Assessment';
    
    protected static ?string $pluralModelLabel = 'Service Assessments';
    
    protected static ?string $navigationGroup = 'Service Delivery';
    
    protected static ?int $navigationSort = 2;

     public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public  function getTitle(): string{
        return request()->query('form_slug', 'intake-assessment') ??'';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(function (?Model $record) {
                // Get form_slug and visit_id from URL or record
                $formSlug = request()->query('form_slug', 'intake-assessment');
                $visitId = request()->query('visit_id');
                
                // Load the form schema
                $schema = AssessmentFormSchema::where('slug', $formSlug)
                    ->where('is_active', true)
                    ->orWhere('id', $record?->form_schema_id)
                    ->first();

                if (!$schema) {
                    return [
                        Forms\Components\Placeholder::make('error')
                            ->content(new HtmlString(
                                '<div class="text-center py-8">
                                    <p class="text-red-600 font-semibold">Form not found!</p>
                                    <p class="text-sm text-gray-600 mt-2">Please check the form_slug parameter.</p>
                                </div>'
                            ))
                    ];
                }

                // Build dynamic form with visit context (pass record for edit/view mode)
                $dynamicFields = DynamicFormBuilder::buildForm($schema, $visitId, $record);

                // Add service selection and billing section if it's the intake form
                if ($formSlug === 'intake-assessment' || $schema->slug === 'intake-assessment') {
                    $dynamicFields[] = self::buildServiceBillingSection();
                }

                return $dynamicFields;
            });
    }

    /**
     * Build service selection and billing preview section
     * CRITICAL for intake assessment workflow
     */
    protected static function buildServiceBillingSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Service Selection & Billing')
            ->icon('heroicon-o-currency-dollar')
            ->description('Select services and preview costs')
            ->collapsible()
            ->schema([
                // Services Repeater
                Forms\Components\Repeater::make('response_data.selected_services')
                    ->label('Services Required')
                    ->schema([
                        Forms\Components\Select::make('service_id')
                            ->label('Service')
                            ->options(Service::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if ($state) {
                                    $service = Service::find($state);
                                    if ($service) {
                                        $set('department', $service->department->name ?? 'N/A');
                                        $set('unit_cost', $service->base_price);
                                        
                                        // Get payment method to determine cost
                                        $paymentMethod = $get('../../response_data.payment_method');
                                        $finalCost = self::calculateServiceCost($service, $paymentMethod);
                                        $set('final_cost', $finalCost);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('department')
                            ->label('Department')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Base Cost (KES)')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric()
                            ->prefix('KES'),

                        Forms\Components\TextInput::make('final_cost')
                            ->label('Final Cost (KES)')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric()
                            ->prefix('KES')
                            ->helperText('Based on payment method'),

                        Forms\Components\Select::make('priority')
                            ->label('Priority')
                            ->options([
                                'routine' => 'Routine',
                                'high' => 'High Priority',
                                'urgent' => 'Urgent',
                            ])
                            ->default('routine'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Service Notes')
                            ->rows(2)
                            ->placeholder('Any special instructions or notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => 
                        Service::find($state['service_id'] ?? null)?->name
                    )
                    ->defaultItems(1)
                    ->live()
                    ->columnSpanFull(),

                // Payment Method
                Forms\Components\Select::make('response_data.payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'mpesa' => 'M-PESA',
                        'sha' => 'SHA (Social Health Authority)',
                        'ncpwd' => 'NCPWD (Persons with Disabilities)',
                        'waiver' => 'Waiver / Pro Bono',
                    ])
                    ->default('cash')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        // Recalculate all service costs when payment method changes
                        $services = $get('response_data.selected_services') ?? [];
                        
                        foreach ($services as $index => $serviceData) {
                            if (isset($serviceData['service_id'])) {
                                $service = Service::find($serviceData['service_id']);
                                if ($service) {
                                    $finalCost = self::calculateServiceCost($service, $state);
                                    $set("response_data.selected_services.{$index}.final_cost", $finalCost);
                                }
                            }
                        }
                    })
                    ->columnSpan(1),

                // Conditional insurance number field
                Forms\Components\TextInput::make('response_data.insurance_number')
                    ->label('Insurance/Member Number')
                    ->visible(fn (Get $get) => in_array($get('response_data.payment_method'), ['sha', 'ncpwd']))
                    ->required(fn (Get $get) => in_array($get('response_data.payment_method'), ['sha', 'ncpwd']))
                    ->columnSpan(1),

                Forms\Components\Textarea::make('response_data.payment_notes')
                    ->label('Payment Notes')
                    ->placeholder('Any special billing instructions')
                    ->rows(2)
                    ->columnSpanFull(),

                // Billing Summary Preview
                Forms\Components\Placeholder::make('billing_summary')
                    ->label('Billing Summary')
                    ->content(function (Get $get) {
                        $services = $get('response_data.selected_services') ?? [];
                        $paymentMethod = $get('response_data.payment_method') ?? 'cash';
                        
                        if (empty($services)) {
                            return new HtmlString('<div class="text-gray-500 italic">No services selected</div>');
                        }

                        $totalBaseCost = 0;
                        $totalFinalCost = 0;
                        $servicesList = '';

                        foreach ($services as $serviceData) {
                            if (isset($serviceData['service_id'])) {
                                $service = Service::find($serviceData['service_id']);
                                if ($service) {
                                    $baseCost = $service->base_price;
                                    $finalCost = self::calculateServiceCost($service, $paymentMethod);
                                    
                                    $totalBaseCost += $baseCost;
                                    $totalFinalCost += $finalCost;

                                    $servicesList .= '<div class="flex justify-between items-center py-2 border-b border-gray-200">
                                        <span class="text-gray-700">' . e($service->name) . '</span>
                                        <div class="text-right">
                                            <div class="text-sm text-gray-500 line-through">KES ' . number_format($baseCost, 2) . '</div>
                                            <div class="font-semibold text-gray-900">KES ' . number_format($finalCost, 2) . '</div>
                                        </div>
                                    </div>';
                                }
                            }
                        }

                        $paymentMethodLabel = match($paymentMethod) {
                            'sha' => 'SHA (80% covered)',
                            'ncpwd' => 'NCPWD (90% covered)',
                            'waiver' => 'Waiver (100% covered)',
                            'cash' => 'Cash',
                            'mpesa' => 'M-PESA',
                            default => ucfirst($paymentMethod),
                        };

                        $savings = $totalBaseCost - $totalFinalCost;
                        $savingsText = $savings > 0 
                            ? '<div class="mt-2 text-sm text-green-600">You save: KES ' . number_format($savings, 2) . '</div>'
                            : '';

                        return new HtmlString('
                            <div class="bg-gradient-to-br from-primary-50 to-white border border-primary-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h3 class="font-semibold text-gray-900">Selected Services</h3>
                                    <span class="px-2 py-1 bg-primary-100 text-primary-700 text-xs font-medium rounded">
                                        ' . e($paymentMethodLabel) . '
                                    </span>
                                </div>
                                
                                ' . $servicesList . '
                                
                                <div class="flex justify-between items-center pt-3 mt-3 border-t-2 border-gray-300">
                                    <span class="text-lg font-bold text-gray-900">Total Amount</span>
                                    <span class="text-2xl font-bold text-primary-600">
                                        KES ' . number_format($totalFinalCost, 2) . '
                                    </span>
                                </div>
                                
                                ' . $savingsText . '
                                
                                <div class="mt-4 p-3 bg-blue-50 rounded text-sm text-blue-800">
                                    <strong>Note:</strong> This is an estimate. Final billing will be processed at the payment desk.
                                </div>
                            </div>
                        ');
                    })
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Calculate service cost based on payment method
     * Uses Service model's built-in pricing logic
     */
    protected static function calculateServiceCost(?Service $service, ?string $paymentMethod): float
    {
        if (!$service) {
            return 0.0;
        }

        $baseCost = $service->base_price ?? 0.0;

        return match($paymentMethod) {
            'sha' => $service->sha_covered 
                ? ($service->sha_price ?? ($baseCost * 0.2))
                : $baseCost * 0.2,
            
            'ncpwd' => $service->ncpwd_covered
                ? ($service->ncpwd_price ?? ($baseCost * 0.1))
                : $baseCost * 0.1,
            
            'waiver' => 0.0,
            
            default => $baseCost,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('schema.name')
                    ->label('Assessment Type')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn($record) => match($record->schema->category ?? 'general') {
                        'vision' => 'info',
                        'therapy' => 'success',
                        'audiology' => 'warning',
                        'psychology' => 'purple',
                        'intake' => 'primary',
                        default => 'gray',
                    })
                    ->icon('heroicon-o-document-text')
                    ->wrap(),
                
                Tables\Columns\TextColumn::make('schema.category')
                    ->label('Category')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client Name')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('semibold')
                    ->icon('heroicon-o-user')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->copyable()
                    ->color('primary')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('visit.visit_number')
                    ->label('Visit #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-hashtag'),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'primary' => 'submitted',
                        'danger' => 'archived',
                    ])
                    ->icons([
                        'heroicon-o-document' => 'draft',
                        'heroicon-o-clock' => 'in_progress',
                        'heroicon-o-check-circle' => 'completed',
                        'heroicon-o-paper-airplane' => 'submitted',
                    ])
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('completion_percentage')
                    ->label('Progress')
                    ->suffix('%')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Not submitted')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('schema')
                    ->relationship('schema', 'name')
                    ->label('Assessment Type')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'intake' => 'Intake',
                        'vision' => 'Vision',
                        'therapy' => 'Therapy',
                        'audiology' => 'Audiology',
                        'psychology' => 'Psychology',
                        'general' => 'General',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['value'])) {
                            $query->whereHas('schema', fn($q) => $q->where('category', $data['value']));
                        }
                    }),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'submitted' => 'Submitted',
                    ]),
                
                Tables\Filters\Filter::make('submitted_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('submitted_from')
                            ->label('Submitted From'),
                        \Filament\Forms\Components\DatePicker::make('submitted_until')
                            ->label('Submitted Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['submitted_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                $data['submitted_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                // Custom View Action with explicit URL
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => "/admin/dynamic-assessments/{$record->id}?visit_id={$record->visit_id}")
                    ->visible(fn ($record) => $record->visit_id !== null),
                
                // Custom Edit Action with explicit URL
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->url(fn ($record) => "/admin/dynamic-assessments/{$record->id}/edit?visit_id={$record->visit_id}")
                    ->visible(fn ($record) => $record->visit_id !== null && $record->status !== 'completed'),
                
                Tables\Actions\Action::make('complete')
                    ->label('Mark Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'completed')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'completed',
                            'completion_percentage' => 100,
                            'completed_at' => now(),
                            'submitted_at' => now(),
                        ]);
                        
                        Notification::make()
                            ->title('Assessment completed!')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn($record) => route('assessments.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record->status === 'completed' && \Illuminate\Support\Facades\Route::has('assessments.print')),
                
                Tables\Actions\Action::make('auto_referrals')
                    ->label('Referrals')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->visible(fn($record) => $record->autoReferrals()->exists())
                    ->badge()
                    ->badgeColor('warning')
                    ->modalContent(fn($record) => view('filament.modals.auto-referrals', [
                        'referrals' => $record->autoReferrals
                    ]))
                    ->modalWidth('3xl'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => Pages\ListDynamicAssessments::route('/'),
            'create' => Pages\CreateDynamicAssessment::route('/create'),
            'view' => Pages\ViewDynamicAssessment::route('/{record}'),
            'edit' => Pages\EditDynamicAssessment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['schema', 'client', 'visit']);
    }
}