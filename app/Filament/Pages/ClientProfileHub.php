<?php

namespace App\Filament\Pages;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientDisability;
use App\Models\ClientEducation;
use App\Models\ClientMedicalHistory;
use App\Models\ClientSocioDemographic;
use App\Models\ExternalReferral;
use App\Models\IntakeAssessment;
use App\Models\InternalReferral;
use App\Models\SchoolPlacement;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;

class ClientProfileHub extends Page
{
    protected static string $view = 'filament.pages.client-profile-hub';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Client Profile Hub';

    #[\Livewire\Attributes\Url]
    public ?int $clientId = null;

    #[\Livewire\Attributes\Url]
    public ?int $visitId = null;

    #[\Livewire\Attributes\Url(as: 'tab')]
    public string $activeTab = 'overview';

    public ?Client $client = null;
    public ?Visit $activeVisit = null;

    // Form states
    public ?array $newServiceData = null;
    public ?array $appointmentData = null;
    public ?array $internalReferralData = null;

    public function mount(): void
    {
        if ($this->clientId) {
            $this->client = Client::with([
                'county', 'subCounty', 'ward',
                'contacts', 'addresses', 'insurances.insuranceProvider',
                'allergies',
            ])->findOrFail($this->clientId);

            if ($this->visitId) {
                $this->activeVisit = Visit::with([
                    'triage',
                    'intakeAssessment',
                    'serviceBookings.service.department',
                    'serviceRequests.service',
                    'invoices.payments',
                    'queueEntries',
                ])->findOrFail($this->visitId);
            } else {
                $this->activeVisit = $this->client->visits()
                    ->with([
                        'triage',
                        'intakeAssessment',
                        'serviceBookings.service.department',
                        'serviceRequests.service',
                        'invoices.payments',
                        'queueEntries',
                    ])
                    ->whereIn('status', ['checked_in', 'in_progress'])
                    ->latest()
                    ->first();
            }
        }
    }

    public function getMaxWidth(): MaxWidth|string
    {
        return MaxWidth::Full;
    }

    public function getTitle(): string
    {
        return $this->client
            ? "{$this->client->full_name} — Profile Hub"
            : 'Client Profile Hub';
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // ==========================================
    // COMPUTED PROPERTIES
    // ==========================================

    public function getDemographicsProperty(): array
    {
        $primaryContact = $this->client->contacts->firstWhere('is_primary', true);
        $primaryAddress = $this->client->addresses->firstWhere('is_primary', true);

        return [
            'basic' => [
                'UCI' => $this->client->uci,
                'Full Name' => $this->client->full_name,
                'Date of Birth' => $this->client->date_of_birth?->format('M d, Y') ?? 'N/A',
                'Age' => ($this->client->estimated_age ?? $this->client->date_of_birth?->age ?? '—') . ' yrs',
                'Gender' => ucfirst($this->client->gender ?? 'Not specified'),
                'National ID' => $this->client->national_id ?? 'N/A',
                'Birth Certificate' => $this->client->birth_certificate_number ?? 'N/A',
                'NCPWD No.' => $this->client->ncpwd_number ?? 'N/A',
                'SHA No.' => $this->client->sha_number ?? 'N/A',
            ],
            'contact' => [
                'Primary Phone' => $this->client->phone_primary ?? 'N/A',
                'Secondary Phone' => $this->client->phone_secondary ?? 'N/A',
                'Email' => $this->client->email ?? 'N/A',
                'Guardian Name' => $this->client->guardian_name ?? 'N/A',
                'Guardian Phone' => $this->client->guardian_phone ?? 'N/A',
                'Relationship' => $this->client->guardian_relationship ?? 'N/A',
            ],
            'address' => [
                'County' => $this->client->county?->name ?? $primaryAddress?->county?->name ?? 'N/A',
                'Sub-County' => $this->client->subCounty?->name ?? $primaryAddress?->subCounty?->name ?? 'N/A',
                'Ward' => $this->client->ward?->name ?? $primaryAddress?->ward?->name ?? 'N/A',
                'Village' => $this->client->village ?? 'N/A',
                'Landmark' => $this->client->landmark ?? 'N/A',
                'Primary Address' => $this->client->primary_address ?? 'N/A',
            ],
        ];
    }

    public function getActiveInsurancesProperty()
    {
        return $this->client->insurances()
            ->with('insuranceProvider')
            ->valid()
            ->get();
    }

    public function getDisabilityProperty(): ?ClientDisability
    {
        return ClientDisability::where('client_id', $this->client->id)->first();
    }

    public function getAllergiesProperty()
    {
        return $this->client->allergies;
    }

    public function getMedicalHistoryProperty(): ?ClientMedicalHistory
    {
        return ClientMedicalHistory::where('client_id', $this->client->id)->first();
    }

    public function getSocioDemographicProperty(): ?ClientSocioDemographic
    {
        return ClientSocioDemographic::where('client_id', $this->client->id)->first();
    }

    public function getEducationProperty(): ?ClientEducation
    {
        return ClientEducation::where('client_id', $this->client->id)->first();
    }

    public function getSchoolPlacementsProperty()
    {
        return SchoolPlacement::where('client_id', $this->client->id)
            ->with(['school', 'placementOfficer'])
            ->latest('admission_date')
            ->get();
    }

    public function getCurrentVisitTriageProperty(): ?array
    {
        if (! $this->activeVisit?->triage) {
            return null;
        }

        $triage = $this->activeVisit->triage;

        $bp = ($triage->systolic_bp && $triage->diastolic_bp)
            ? "{$triage->systolic_bp}/{$triage->diastolic_bp} mmHg"
            : null;

        return [
            'vital_signs' => array_filter([
                'Blood Pressure' => $bp,
                'Heart Rate' => $triage->heart_rate ? $triage->heart_rate . ' bpm' : null,
                'Temperature' => $triage->temperature ? $triage->temperature . ' °C' : null,
                'Respiratory Rate' => $triage->respiratory_rate ? $triage->respiratory_rate . ' /min' : null,
                'Oxygen Saturation' => $triage->oxygen_saturation ? $triage->oxygen_saturation . '%' : null,
                'Weight' => $triage->weight ? $triage->weight . ' kg' : null,
                'Height' => $triage->height ? $triage->height . ' cm' : null,
                'BMI' => $triage->bmi ?? null,
            ]),
            'assessment' => [
                'Chief Complaint' => $triage->presenting_complaint ?? $triage->chief_complaint ?? 'N/A',
                'Pain Scale' => $triage->pain_scale ? $triage->pain_scale . '/10' : 'N/A',
                'Risk Level' => ucfirst($triage->risk_level ?? 'routine'),
            ],
            'nurse' => $triage->triagedBy?->name ?? 'N/A',
            'triaged_at' => $triage->triaged_at?->format('M d, Y H:i') ?? $triage->created_at?->format('M d, Y H:i'),
            'notes' => $triage->triage_notes ?? null,
        ];
    }

    public function getCurrentVisitIntakeProperty(): ?array
    {
        if (! $this->activeVisit?->intakeAssessment) {
            return null;
        }

        $intake = $this->activeVisit->intakeAssessment;

        return [
            'presenting_problem' => $intake->presenting_problem ?? null,
            'history_present_illness' => $intake->history_present_illness ?? null,
            'assessment_type' => ucfirst(str_replace('_', ' ', $intake->assessment_type ?? 'standard')),
            'risk_level' => ucfirst($intake->risk_level ?? 'low'),
            'recommendations' => $intake->recommendations ?? null,
            'priority' => ucfirst($intake->priority_level ?? $intake->priority ?? 'routine'),
            'special_instructions' => $intake->special_instructions ?? null,
            'officer' => $intake->assessedBy?->name ?? 'N/A',
            'assessed_at' => $intake->assessed_at?->format('M d, Y H:i') ?? $intake->created_at?->format('M d, Y H:i'),
        ];
    }

    public function getCurrentServicesProperty()
    {
        if (! $this->activeVisit) {
            return collect();
        }

        return $this->activeVisit->serviceBookings()
            ->with(['service.department', 'queueEntry'])
            ->get()
            ->map(fn ($booking) => [
                'service_name' => $booking->service->name ?? 'Unknown',
                'department' => $booking->service->department?->name ?? 'N/A',
                'status' => ucfirst(str_replace('_', ' ', $booking->service_status ?? 'scheduled')),
                'queue_status' => $booking->queueEntry
                    ? ucfirst(str_replace('_', ' ', $booking->queueEntry->status))
                    : 'Not in queue',
                'queue_number' => $booking->queueEntry?->queue_number ?? '—',
                'priority' => $booking->priority ?? 'routine',
            ]);
    }

    public function getLatestIntakeProperty(): ?IntakeAssessment
    {
        return IntakeAssessment::where('client_id', $this->client->id)
            ->with(['assessedBy'])
            ->latest()
            ->first();
    }

    public function getIntakeHistoryProperty()
    {
        return IntakeAssessment::where('client_id', $this->client->id)
            ->with(['visit', 'assessedBy'])
            ->latest()
            ->limit(30)
            ->get();
    }

    public function getVisitHistoryProperty()
    {
        return $this->client->visits()
            ->with([
                'serviceBookings.service.department',
                'invoices.payments',
                'triage',
                'intakeAssessment.assessedBy',
            ])
            ->where('id', '!=', $this->activeVisit?->id)
            ->latest('check_in_time')
            ->limit(20)
            ->get();
    }

    public function getUpcomingAppointmentsProperty()
    {
        return Appointment::where('client_id', $this->client->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('appointment_date', '>=', now())
            ->with(['service', 'department', 'provider'])
            ->orderBy('appointment_date')
            ->limit(10)
            ->get();
    }

    public function getPastAppointmentsProperty()
    {
        return Appointment::where('client_id', $this->client->id)
            ->where('appointment_date', '<', now())
            ->orderBy('appointment_date', 'desc')
            ->limit(15)
            ->get();
    }

    public function getInternalReferralsProperty()
    {
        return InternalReferral::where('client_id', $this->client->id)
            ->with(['fromDepartment', 'toDepartment', 'service', 'referringProvider'])
            ->latest()
            ->limit(15)
            ->get();
    }

    public function getExternalReferralsProperty()
    {
        return ExternalReferral::where('client_id', $this->client->id)
            ->with(['referringProvider'])
            ->latest()
            ->limit(15)
            ->get();
    }

    // Assessment service links — categories with URLs to specific resource pages
    public function getAssessmentLinksProperty(): array
    {
        return [
            [
                'label' => 'Audiology / Hearing',
                'icon' => 'heroicon-o-musical-note',
                'color' => 'info',
                'description' => 'Hearing assessments, audiometry & hearing aid fitting',
                'url' => $this->activeVisit
                    ? route('filament.admin.resources.visits.view', $this->activeVisit->id) . '#audiology'
                    : null,
            ],
            [
                'label' => 'Optometry / Vision',
                'icon' => 'heroicon-o-eye',
                'color' => 'primary',
                'description' => 'Vision screening, refraction & low vision',
                'url' => null,
            ],
            [
                'label' => 'Physiotherapy',
                'icon' => 'heroicon-o-bolt',
                'color' => 'success',
                'description' => 'Motor rehabilitation, mobility & physical therapy',
                'url' => null,
            ],
            [
                'label' => 'Occupational Therapy',
                'icon' => 'heroicon-o-hand-raised',
                'color' => 'warning',
                'description' => 'Daily living skills, fine motor & sensory integration',
                'url' => null,
            ],
            [
                'label' => 'Speech & Language',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'color' => 'purple',
                'description' => 'Communication, AAC & feeding therapy',
                'url' => null,
            ],
            [
                'label' => 'Psychology',
                'icon' => 'heroicon-o-light-bulb',
                'color' => 'rose',
                'description' => 'Psychological assessments, counselling & behaviour support',
                'url' => null,
            ],
            [
                'label' => 'Social Work',
                'icon' => 'heroicon-o-users',
                'color' => 'orange',
                'description' => 'Psychosocial support, family & community linkage',
                'url' => null,
            ],
            [
                'label' => 'Special Education',
                'icon' => 'heroicon-o-academic-cap',
                'color' => 'teal',
                'description' => 'Educational assessments & school readiness',
                'url' => null,
            ],
        ];
    }

    // ==========================================
    // FORMS
    // ==========================================

    protected function getForms(): array
    {
        return [
            'requestNewServiceForm',
            'createAppointmentForm',
        ];
    }

    public function requestNewServiceForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('service_ids')
                    ->label('Services')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn () => Service::where('is_active', true)
                        ->pluck('name', 'id'))
                    ->required()
                    ->helperText('Select one or more services'),

                Forms\Components\Select::make('urgency_level')
                    ->label('Urgency')
                    ->options([
                        'routine' => 'Routine',
                        'urgent' => 'Urgent',
                        'emergency' => 'Emergency',
                    ])
                    ->default('routine')
                    ->required(),

                Forms\Components\Textarea::make('clinical_indication')
                    ->label('Clinical Indication')
                    ->rows(3)
                    ->placeholder('Why is this service needed?')
                    ->required(),
            ])
            ->statePath('newServiceData');
    }

    public function createAppointmentForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('appointment_date')
                    ->label('Date')
                    ->required()
                    ->minDate(now())
                    ->native(false),

                Forms\Components\TimePicker::make('appointment_time')
                    ->label('Preferred Time')
                    ->seconds(false)
                    ->required(),

                Forms\Components\Select::make('service_id')
                    ->label('Service')
                    ->options(fn () => Service::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2),
            ])
            ->statePath('appointmentData');
    }

    // ==========================================
    // ACTIONS
    // ==========================================

    public function requestNewService(): void
    {
        if (! $this->activeVisit) {
            Notification::make()->danger()
                ->title('No Active Visit')
                ->body('Client must have an active visit to request services.')
                ->send();
            return;
        }

        $data = $this->requestNewServiceForm->getState();

        DB::beginTransaction();
        try {
            foreach ($data['service_ids'] as $serviceId) {
                ServiceRequest::create([
                    'visit_id' => $this->activeVisit->id,
                    'client_id' => $this->client->id,
                    'service_id' => $serviceId,
                    'requested_by' => auth()->id(),
                    'request_type' => 'provider_request',
                    'status' => 'pending_payment',
                    'urgency_level' => $data['urgency_level'],
                    'clinical_indication' => $data['clinical_indication'],
                ]);
            }
            DB::commit();

            $this->activeVisit->refresh();
            $this->requestNewServiceForm->fill();

            Notification::make()->success()
                ->title('Services Requested')
                ->body(count($data['service_ids']) . ' service(s) requested. Client must visit Cashier for payment.')
                ->send();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->danger()
                ->title('Request Failed')->body($e->getMessage())->send();
        }
    }

    public function createAppointment(): void
    {
        $data = $this->createAppointmentForm->getState();

        DB::beginTransaction();
        try {
            Appointment::create([
                'client_id' => $this->client->id,
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'service_id' => $data['service_id'],
                'status' => 'scheduled',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            DB::commit();

            $this->createAppointmentForm->fill();

            Notification::make()->success()
                ->title('Appointment Booked')
                ->body('Appointment scheduled successfully.')
                ->send();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->danger()
                ->title('Booking Failed')->body($e->getMessage())->send();
        }
    }

    public function refreshData(): void
    {
        $this->mount();

        Notification::make()->success()->title('Refreshed')->send();
    }
}
