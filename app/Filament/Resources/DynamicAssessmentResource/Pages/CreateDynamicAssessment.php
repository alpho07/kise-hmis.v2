<?php

namespace App\Filament\Resources\DynamicAssessmentResource\Pages;

use App\Filament\Resources\DynamicAssessmentResource;
use App\Models\Visit;
use App\Models\AssessmentFormSchema;
use App\Services\DynamicFormBuilder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateDynamicAssessment extends CreateRecord
{
    protected static string $resource = DynamicAssessmentResource::class;

    protected function getFormSchema(): array
    {
        // Get form slug from URL query parameter
        $formSlug = request()->query('form_slug');
        $visitId = request()->query('visit_id');
        $serviceBookingId = request()->query('service_booking');
        
        if (!$formSlug) {
            return [
                \Filament\Forms\Components\Placeholder::make('no_slug')
                    ->label('No Form Selected')
                    ->content('Please specify a form slug in the URL. Example: ?form_slug=vision-basic-eye&visit_id=123'),
            ];
        }

        if (!$visitId) {
            return [
                \Filament\Forms\Components\Placeholder::make('no_visit')
                    ->label('No Visit Selected')
                    ->content('Please provide a visit_id in the URL.'),
            ];
        }

        // Get the form schema by slug
        $schema = AssessmentFormSchema::where('slug', $formSlug)
            ->where('is_active', true)
            ->latest('version')
            ->first();

        if (!$schema) {
            return [
                \Filament\Forms\Components\Placeholder::make('no_schema')
                    ->label('Form Not Found')
                    ->content("No active assessment form found with slug: {$formSlug}"),
            ];
        }

        // Use DynamicFormBuilder to build the form
        return DynamicFormBuilder::buildForm($schema, $visitId);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get parameters from URL
        $visitId = request()->query('visit_id');
        $formSlug = request()->query('form_slug');
        $serviceBookingId = request()->query('service_booking');

        // Get form schema
        $schema = AssessmentFormSchema::where('slug', $formSlug)->first();
        
        // Get visit details
        $visit = $visitId ? Visit::find($visitId) : null;

        // Prepare data for AssessmentFormResponse
        return [
            'form_schema_id' => $schema->id ?? $data['form_schema_id'] ?? null,
            'visit_id' => $visitId ?? $data['visit_id'] ?? null,
            'client_id' => $visit->client_id ?? $data['client_id'] ?? null,
            'branch_id' => $visit->branch_id ?? auth()->user()->branch_id ?? $data['branch_id'] ?? null,
            'response_data' => $data['response_data'] ?? $data,
            'metadata' => [
                'service_booking_id' => $serviceBookingId,
                'provider_id' => auth()->id(),
                'created_via' => 'service_point',
                'form_slug' => $formSlug,
            ],
            'status' => 'completed',
            'completion_percentage' => 100,
            'started_at' => now()->subMinutes(15), // Estimate
            'completed_at' => now(),
            'submitted_at' => now(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ];

        \Log::info('Creating assessment', [
            'form_slug' => $formSlug,
            'visit_id' => $visitId,
            'schema_id' => $schema->id ?? null,
        ]);
    }

    protected function afterCreate(): void
    {
        $assessment = $this->record;

        // Check if this is an intake assessment
        $isIntake = $assessment->schema->slug === 'intake-assessment';

        if ($isIntake) {
            // Mark as completed
            $assessment->update(['status' => 'completed']);

            \Log::info('Intake assessment created, triggering payment routing', [
                'assessment_id' => $assessment->id,
                'visit_id' => $assessment->visit_id,
            ]);

            // Trigger payment routing
            if (class_exists('\App\Services\PaymentRoutingService')) {
                $routingService = new \App\Services\PaymentRoutingService();
                $result = $routingService->routeAfterIntake($assessment);

                if ($result['success']) {
                    Notification::make()
                        ->success()
                        ->title('Intake Assessment Completed')
                        ->body($result['message'])
                        ->duration(10000)
                        ->send();

                    \Log::info('Payment routing successful', $result);
                } else {
                    Notification::make()
                        ->danger()
                        ->title('Routing Error')
                        ->body($result['error'] ?? 'Failed to route client to payment queue')
                        ->persistent()
                        ->send();

                    \Log::error('Payment routing failed', $result);
                }
            }
        }

        // Check for auto-referrals (for all assessment types)
        if ($assessment->schema->auto_referrals) {
            foreach ($assessment->schema->auto_referrals as $referralRule) {
                $this->checkAutoReferral($assessment, $referralRule);
            }
        }

        // Update service booking status if applicable
        $serviceBookingId = request()->query('service_booking');
        if ($serviceBookingId) {
            $booking = \App\Models\ServiceBooking::find($serviceBookingId);
            if ($booking) {
                $booking->update(['service_status' => 'completed']);
            }
        }
    }

    protected function checkAutoReferral($response, array $rule): void
    {
        $condition = $rule['condition'] ?? null;
        $action = $rule['action'] ?? null;

        if (!$condition || !$action) {
            return;
        }

        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (!$field || !$operator) {
            return;
        }

        $fieldValue = $response->response_data[$field] ?? null;

        $triggered = match($operator) {
            'equals' => $fieldValue == $value,
            'in' => is_array($value) && in_array($fieldValue, $value),
            'greater_than' => is_numeric($fieldValue) && is_numeric($value) && $fieldValue > $value,
            'less_than' => is_numeric($fieldValue) && is_numeric($value) && $fieldValue < $value,
            default => false,
        };

        if ($triggered) {
            \App\Models\AssessmentAutoReferral::create([
                'form_response_id' => $response->id,
                'client_id' => $response->client_id,
                'visit_id' => $response->visit_id,
                'service_point' => $action['service_point'] ?? 'general',
                'department' => $action['department'] ?? null,
                'priority' => $action['priority'] ?? 'normal',
                'reason' => $action['reason'] ?? 'Auto-referral triggered',
                'trigger_data' => [
                    'field' => $field,
                    'value' => $fieldValue,
                    'rule' => $rule,
                ],
                'status' => 'pending',
            ]);

            Notification::make()
                ->warning()
                ->title('Auto-Referral Created')
                ->body("Client referred to {$action['service_point']} - {$action['reason']}")
                ->send();

            \Log::info('Auto-referral created', [
                'assessment_id' => $response->id,
                'service_point' => $action['service_point'],
                'reason' => $action['reason'],
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        $formSlug = request()->query('form_slug');
        
        // Redirect to intake queue if this was an intake assessment
        if ($formSlug === 'intake-assessment') {
            if (class_exists('\App\Filament\Resources\IntakeQueueResource')) {
                return \App\Filament\Resources\IntakeQueueResource::getUrl('index');
            }
        }

        // Redirect back to Service Point Dashboard for service assessments
        if (class_exists('\App\Filament\Resources\ServicePointDashboardResource')) {
            return \App\Filament\Resources\ServicePointDashboardResource::getUrl('index');
        }

        // Default: return to assessments index
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $formSlug = $this->record->schema->slug ?? '';
        return "Assessment completed successfully";
    }
}