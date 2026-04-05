<?php

namespace App\Filament\Pages;

use App\Models\AssessmentFormResponse;
use App\Models\Client;
use App\Models\QueueEntry;
use App\Models\Visit;
use Filament\Pages\Page;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;

class SpecialistHub extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    protected static string $view = 'filament.pages.specialist-hub';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Specialist Hub';

    #[\Livewire\Attributes\Url]
    public ?int $clientId = null;

    #[\Livewire\Attributes\Url]
    public ?int $visitId = null;

    #[\Livewire\Attributes\Url]
    public ?int $queueId = null;

    public ?Client $client = null;
    public ?Visit $visit = null;
    public ?QueueEntry $queueEntry = null;

    public function mount(): void
    {
        abort_unless(
            auth()->user()->hasAnyRole(['service_provider', 'admin', 'super_admin', 'branch_manager']),
            403
        );

        if ($this->queueId) {
            $this->queueEntry = QueueEntry::with([
                'client',
                'visit.triage.triagedBy',
                'visit.intakeAssessment',
                'visit.serviceBookings.service.department',
                'visit.serviceBookings.service.assessmentForms',
                'visit.serviceBookings.sessions',
                'visit.assessmentFormResponses.schema',
                'service.department',
                'department',
                'serviceProvider',
            ])->findOrFail($this->queueId);

            $this->client = $this->queueEntry->client;
            $this->visit  = $this->queueEntry->visit;
        } elseif ($this->clientId) {
            $this->client = Client::findOrFail($this->clientId);

            if ($this->visitId) {
                $this->visit = Visit::with([
                    'triage.triagedBy',
                    'intakeAssessment',
                    'serviceBookings.service.department',
                    'serviceBookings.service.assessmentForms',
                    'serviceBookings.sessions',
                    'assessmentFormResponses.schema',
                ])->findOrFail($this->visitId);
            } else {
                $this->visit = $this->client->visits()
                    ->with([
                        'triage.triagedBy',
                        'intakeAssessment',
                        'serviceBookings.service.department',
                        'serviceBookings.service.assessmentForms',
                        'serviceBookings.sessions',
                        'assessmentFormResponses.schema',
                    ])
                    ->whereIn('status', ['in_service', 'in_queue', 'checked_in', 'in_progress'])
                    ->latest()
                    ->first();
            }
        }

        abort_unless($this->client, 404);
    }

    public function getMaxWidth(): MaxWidth|string
    {
        return MaxWidth::Full;
    }

    public function getTitle(): string
    {
        return $this->client
            ? "{$this->client->full_name} — Specialist Hub"
            : 'Specialist Hub';
    }

    // =========================================================
    // COMPUTED PROPERTIES
    // =========================================================

    public function getClientAgeProperty(): ?int
    {
        return $this->client->date_of_birth?->age;
    }

    public function getIsPaediatricProperty(): bool
    {
        $age = $this->client_age;
        return $age !== null && $age < 18;
    }

    /**
     * Full triage data for current visit
     */
    public function getTriageDataProperty(): ?array
    {
        $t = $this->visit?->triage;
        if (! $t) return null;

        $bp = ($t->blood_pressure_systolic && $t->blood_pressure_diastolic)
            ? "{$t->blood_pressure_systolic}/{$t->blood_pressure_diastolic} mmHg"
            : null;

        return [
            'vitals' => array_filter([
                'Weight'           => $t->weight     ? $t->weight . ' kg'   : null,
                'Height'           => $t->height     ? $t->height . ' cm'   : null,
                'BMI'              => $t->bmi        ? $t->bmi              : null,
                'Temperature'      => $t->temperature ? $t->temperature . ' °C' : null,
                'Blood Pressure'   => $bp,
                'Heart Rate'       => $t->heart_rate        ? $t->heart_rate . ' bpm'       : null,
                'Respiratory Rate' => $t->respiratory_rate  ? $t->respiratory_rate . ' /min' : null,
                'SpO₂'             => $t->oxygen_saturation ? $t->oxygen_saturation . '%'    : null,
                'Pain Scale'       => $t->pain_scale !== null ? $t->pain_scale . '/10'       : null,
                'Consciousness'    => $t->consciousness_level ? ucfirst($t->consciousness_level) : null,
            ]),
            'clinical' => array_filter([
                'Presenting Complaint' => $t->notes ?? null,   // notes holds the presenting complaint in TriageResource
                'Triage Status'        => $t->triage_status   ? ucfirst(str_replace('_', ' ', $t->triage_status)) : null,
                'Risk Level'           => $t->risk_level      ? ucfirst($t->risk_level)    : null,
                'Clearance Status'     => $t->clearance_status ? ucfirst(str_replace('_', ' ', $t->clearance_status)) : null,
                'Next Step'            => $t->next_step       ? ucfirst(str_replace('_', ' ', $t->next_step)) : null,
                'Handover Summary'     => $t->handover_summary ?? null,
                'Pending Actions'      => $t->pending_actions ?? null,
                'Triaged By'           => $t->triagedBy?->name ?? null,
            ]),
            'red_flags'             => $t->red_flags ?? [],
            'has_red_flags'         => (bool) $t->has_red_flags,
            'safeguarding'          => $t->safeguarding_concerns ?? [],
            'has_safeguarding'      => (bool) $t->has_safeguarding_concerns,
            'crisis_activated'      => (bool) $t->crisis_protocol_activated,
            'risk_score'            => $t->risk_score,
        ];
    }

    /**
     * Intake assessment data for current visit
     */
    public function getIntakeDataProperty(): ?array
    {
        $ia = $this->visit?->intakeAssessment;
        if (! $ia) return null;

        return array_filter([
            'Reason for Visit'        => $ia->reason_for_visit         ?? null,
            'Current Concerns'        => $ia->current_concerns         ?? null,
            'Previous Interventions'  => $ia->previous_interventions   ?? null,
            'Developmental History'   => $ia->developmental_history    ?? null,
            'Educational Background'  => $ia->educational_background   ?? null,
            'Family History'          => $ia->family_history           ?? null,
            'Social History'          => $ia->social_history           ?? null,
            'Assessment Summary'      => $ia->assessment_summary       ?? null,
            'Recommendations'         => $ia->recommendations          ?? null,
            'Priority Level'          => $ia->priority_level           ? 'Level ' . $ia->priority_level : null,
            'Services Required'       => is_array($ia->services_required)
                                           ? implode(', ', $ia->services_required) : ($ia->services_required ?? null),
        ]);
    }

    /**
     * Completed assessment form responses for the current visit
     */
    public function getCurrentFormResponsesProperty(): Collection
    {
        return $this->visit?->assessmentFormResponses ?? collect();
    }

    /**
     * Previous completed visits (not the current visit), newest first, last 10
     */
    public function getPreviousVisitsProperty(): Collection
    {
        if (! $this->client) return collect();

        $excludeId = $this->visit?->id;

        return $this->client->visits()
            ->with([
                'triage.triagedBy',
                'intakeAssessment',
                'serviceBookings.service',
                'assessmentFormResponses.schema',
            ])
            ->whereIn('status', ['completed', 'discharged', 'checked_out'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->latest()
            ->limit(10)
            ->get();
    }

    /**
     * Grouped forms per service booking — age-filtered, pivot-based
     */
    public function getServiceFormsProperty(): array
    {
        if (! $this->visit) return [];

        $groups      = [];
        $bookings    = $this->visit->serviceBookings ?? collect();
        $isPaediatric = $this->is_paediatric;
        $doneSlugIds  = $this->current_form_responses->pluck('form_schema_id')->toArray();

        foreach ($bookings as $booking) {
            $service = $booking->service;
            if (! $service) continue;

            // Use pivot relationship — explicit, not heuristic
            $allForms = $service->assessmentForms ?? collect();

            $forms = $allForms->filter(function ($schema) use ($isPaediatric) {
                $slug = $schema->slug ?? '';
                $name = strtolower($schema->name ?? '');
                if ($isPaediatric) {
                    if (str_contains($slug, '-adult') || str_contains($name, '(adult)')) return false;
                } else {
                    if (str_contains($slug, '-pediatric') || str_contains($slug, '-paediatric')
                        || str_contains($name, 'paediatric') || str_contains($name, 'pediatric')) return false;
                }
                return true;
            });

            if ($forms->isEmpty()) $forms = $allForms;

            $forms = $forms->map(fn ($schema) => [
                'schema'    => $schema,
                'completed' => in_array($schema->id, $doneSlugIds),
            ])->values();

            $groups[] = [
                'booking'    => $booking,
                'service'    => $service,
                'department' => $service->department,
                'forms'      => $forms,
                'sessions'   => $booking->sessions ?? collect(),
            ];
        }

        return $groups;
    }

    public function addSessionAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('addSession')
            ->label('Add Session')
            ->icon('heroicon-o-plus-circle')
            ->slideOver()
            ->form([
                \Filament\Forms\Components\Hidden::make('service_booking_id'),

                \Filament\Forms\Components\DatePicker::make('session_date')
                    ->default(today())
                    ->required(),

                \Filament\Forms\Components\Textarea::make('session_goals')
                    ->rows(2),

                \Filament\Forms\Components\Textarea::make('activities_performed')
                    ->rows(2),

                \Filament\Forms\Components\Select::make('progress_status')
                    ->options([
                        'improving'  => 'Improving',
                        'stable'     => 'Stable',
                        'regressing' => 'Regressing',
                        'completed'  => 'Completed',
                    ]),

                \Filament\Forms\Components\Select::make('attendance')
                    ->options([
                        'present' => 'Present',
                        'absent'  => 'Absent',
                        'late'    => 'Late',
                    ])
                    ->required(),

                \Filament\Forms\Components\DatePicker::make('next_session_date')
                    ->label('Next Session Date (optional)'),
            ])
            ->action(function (array $data) {
                \App\Models\ServiceSession::create(array_merge($data, [
                    'provider_id'        => auth()->id(),
                    'service_booking_id' => $data['service_booking_id'],
                    'session_date'       => $data['session_date'],
                ]));

                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Session Recorded')
                    ->send();

                // Refresh page data
                if ($this->visitId) {
                    $this->visit = \App\Models\Visit::with([
                        'serviceBookings.service.assessmentForms',
                        'serviceBookings.sessions',
                    ])->find($this->visitId);
                }
            });
    }

    public function formUrl(string $slug): string
    {
        $params = ['form_slug' => $slug];
        if ($this->visitId) $params['visit_id'] = $this->visitId;
        return route('filament.admin.resources.dynamic-assessments.create', $params);
    }

    public function viewResponseUrl(int $responseId): string
    {
        return route('filament.admin.resources.dynamic-assessments.view', $responseId);
    }

    public function getClientSummaryProperty(): array
    {
        return [
            'UCI'     => $this->client->uci ?? '—',
            'Name'    => $this->client->full_name,
            'Age'     => $this->client_age !== null ? $this->client_age . ' yrs' : '—',
            'Gender'  => ucfirst($this->client->gender ?? 'Unknown'),
            'Queue #' => $this->queueEntry?->queue_number ? '#' . $this->queueEntry->queue_number : '—',
            'Room'    => $this->queueEntry?->room_assigned ?? '—',
        ];
    }
}
