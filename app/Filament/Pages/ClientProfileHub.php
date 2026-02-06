<?php

namespace App\Filament\Pages;

use Livewire\Attributes\Url;
use App\Models\Client;
use App\Models\Visit;
use App\Models\ServiceBooking;
use App\Models\ServiceRequest;
use App\Models\Service;
use App\Models\Appointment;
use App\Models\InternalReferral;
use App\Models\ExternalReferral;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;

class ClientProfileHub extends Page
{
    protected static string $view = 'filament.pages.client-profile-hub';
    
    protected static bool $shouldRegisterNavigation = false;
    
    protected static ?string $title = 'Client Profile Hub';
    
    // Use Livewire URL property binding
    #[\Livewire\Attributes\Url]
    public ?int $clientId = null;
    
    #[\Livewire\Attributes\Url]
    public ?int $visitId = null;
    
    public ?Client $client = null;
    public ?Visit $activeVisit = null;
    public string $activeTab = 'overview';
    
    // Form states
    public ?array $newServiceData = null;
    public ?array $appointmentData = null;
    public ?array $internalReferralData = null;
    
    public function mount(): void
    {

       
        if (request()->get('clientId')) {
            $this->client = Client::findOrFail(request()->get('clientId'));
          
            
            // Get active visit or specific visit
            if (request()->get('visitId')) {
                $this->activeVisit = Visit::with([
                    'triage',
                    'intakeAssessment',
                    'serviceBookings.service.department',
                    'serviceRequests.service',
                    'invoices.payments',
                    'queueEntries.queue',
                ])->findOrFail(request()->get('visitId'));
                  dd($this->activeVisit);
            } else {
                $this->activeVisit = $this->client->visits()
                    ->with([
                        'triage',
                        'intakeAssessment',
                        'serviceBookings.service.department',
                        'serviceRequests.service',
                        'invoices.payments',
                        'queueEntries.queue',
                    ])
                    ->whereIn('status', ['checked_in', 'in_progress'])
                    ->latest()
                    ->first();
            }
        }
    }
    
    public function getMaxWidth(): MaxWidth | string
    {
        return MaxWidth::Full;
    }
    
    public function getTitle(): string
    {
        return $this->client 
            ? "{$this->client->full_name} - Profile Hub"
            : 'Client Profile Hub';
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
                'Date of Birth' => $this->client->date_of_birth?->format('M d, Y'),
                'Age' => $this->client->date_of_birth?->age . ' years',
                'Gender' => ucfirst($this->client->gender ?? 'Not specified'),
                'ID Number' => $this->client->id_number ?? 'N/A',
                'Passport' => $this->client->passport_number ?? 'N/A',
            ],
            'contact' => [
                'Phone' => $primaryContact?->contact_value ?? 'N/A',
                'Email' => $this->client->contacts->firstWhere('contact_type', 'email')?->contact_value ?? 'N/A',
                'Alternative Phone' => $this->client->contacts
                    ->where('contact_type', 'phone')
                    ->where('is_primary', false)
                    ->first()?->contact_value ?? 'N/A',
            ],
            'address' => [
                'County' => $primaryAddress?->county?->name ?? 'N/A',
                'Sub-County' => $primaryAddress?->subCounty?->name ?? 'N/A',
                'Ward' => $primaryAddress?->ward?->name ?? 'N/A',
                'Street Address' => $primaryAddress?->street_address ?? 'N/A',
                'Postal Code' => $primaryAddress?->postal_code ?? 'N/A',
            ],
            'emergency' => [
                'Emergency Contact' => $this->client->contacts->firstWhere('contact_type', 'emergency_contact')?->contact_value ?? 'N/A',
                'Emergency Relationship' => $this->client->contacts->firstWhere('contact_type', 'emergency_contact')?->notes ?? 'N/A',
            ]
        ];
    }
    
    public function getActiveInsurancesProperty()
    {
        return $this->client->insurances()
            ->with('insuranceProvider')
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now());
            })
            ->get()
            ->map(fn($insurance) => [
                'Provider' => $insurance->insuranceProvider->name,
                'Member Number' => $insurance->member_number,
                'Coverage Type' => ucfirst(str_replace('_', ' ', $insurance->coverage_type ?? 'standard')),
                'Valid From' => $insurance->valid_from?->format('M d, Y'),
                'Valid Until' => $insurance->valid_until?->format('M d, Y') ?? 'No expiry',
                'Status' => '✓ Active',
            ]);
    }
    
    public function getDisabilitiesProperty()
    {
        return $this->client->disabilities()
            ->get()
            ->map(fn($disability) => [
                'Type' => ucfirst(str_replace('_', ' ', $disability->disability_type)),
                'Severity' => ucfirst($disability->severity ?? 'Not specified'),
                'Description' => $disability->description,
                'Diagnosed Date' => $disability->diagnosed_date?->format('M d, Y'),
                'Support Needed' => $disability->support_needed ? 'Yes' : 'No',
            ]);
    }
    
    public function getAllergiesProperty()
    {
        return $this->client->allergies()
            ->get()
            ->map(fn($allergy) => [
                'Allergen' => $allergy->allergen,
                'Type' => ucfirst($allergy->allergy_type ?? 'unknown'),
                'Severity' => ucfirst($allergy->severity ?? 'unknown'),
                'Reaction' => $allergy->reaction,
                'Notes' => $allergy->notes,
            ]);
    }
    
    public function getMedicalHistoriesProperty()
    {
        return $this->client->medicalHistories()
            ->latest('diagnosed_date')
            ->get()
            ->map(fn($history) => [
                'Condition' => $history->condition_name,
                'Type' => ucfirst(str_replace('_', ' ', $history->condition_type ?? 'other')),
                'Diagnosed' => $history->diagnosed_date?->format('M d, Y'),
                'Status' => ucfirst($history->status ?? 'active'),
                'Treatment' => $history->treatment,
                'Notes' => $history->notes,
            ]);
    }
    
    public function getCurrentVisitTriageProperty(): ?array
    {
        if (!$this->activeVisit?->triage) {
            return null;
        }
        
        $triage = $this->activeVisit->triage;
        
        return [
            'vital_signs' => [
                'Blood Pressure' => $triage->blood_pressure ?? 'N/A',
                'Heart Rate' => $triage->heart_rate ? $triage->heart_rate . ' bpm' : 'N/A',
                'Temperature' => $triage->temperature ? $triage->temperature . ' °C' : 'N/A',
                'Respiratory Rate' => $triage->respiratory_rate ? $triage->respiratory_rate . ' /min' : 'N/A',
                'Oxygen Saturation' => $triage->oxygen_saturation ? $triage->oxygen_saturation . ' %' : 'N/A',
                'Weight' => $triage->weight ? $triage->weight . ' kg' : 'N/A',
                'Height' => $triage->height ? $triage->height . ' cm' : 'N/A',
                'BMI' => $triage->bmi ?? 'N/A',
            ],
            'assessment' => [
                'Priority Level' => ucfirst($triage->priority_level ?? 'routine'),
                'Chief Complaint' => $triage->chief_complaint ?? 'N/A',
                'Pain Scale' => $triage->pain_scale ? $triage->pain_scale . '/10' : 'N/A',
                'Red Flags' => $triage->redFlags->pluck('flag_description')->implode(', ') ?: 'None',
            ],
            'nurse' => [
                'Triaged By' => $triage->user?->name ?? 'N/A',
                'Triaged At' => $triage->created_at?->format('M d, Y H:i'),
            ]
        ];
    }
    
    public function getCurrentVisitIntakeProperty(): ?array
    {
        if (!$this->activeVisit?->intakeAssessment) {
            return null;
        }
        
        $intake = $this->activeVisit->intakeAssessment;
        
        return [
            'assessment' => [
                'Presenting Problem' => $intake->presenting_problem ?? 'N/A',
                'History of Present Illness' => $intake->history_present_illness ?? 'N/A',
                'Assessment Type' => ucfirst(str_replace('_', ' ', $intake->assessment_type ?? 'standard')),
                'Risk Level' => ucfirst($intake->risk_level ?? 'low'),
            ],
            'recommendations' => [
                'Services Recommended' => $intake->recommendations ?? 'N/A',
                'Priority' => ucfirst($intake->priority ?? 'routine'),
                'Special Instructions' => $intake->special_instructions ?? 'None',
            ],
            'officer' => [
                'Assessed By' => $intake->user?->name ?? 'N/A',
                'Assessed At' => $intake->created_at?->format('M d, Y H:i'),
            ]
        ];
    }
    
    public function getCurrentServicesProperty()
    {
        if (!$this->activeVisit) {
            return collect();
        }
        
        // Combine both ServiceBookings and ServiceRequests
        $bookings = $this->activeVisit->serviceBookings()
            ->with(['service.department', 'queueEntry', 'provider'])
            ->get()
            ->map(fn($booking) => [
                'id' => $booking->id,
                'type' => 'booking',
                'Service' => $booking->service->name,
                'Department' => $booking->service->department?->name ?? 'N/A',
                'Status' => ucfirst(str_replace('_', ' ', $booking->service_status ?? 'scheduled')),
                'Queue Status' => $booking->queueEntry 
                    ? ucfirst(str_replace('_', ' ', $booking->queueEntry->status))
                    : 'Not in queue',
                'Queue Position' => $booking->queueEntry?->queue_number ?? 'N/A',
                'Provider' => $booking->provider?->name ?? 'Not assigned',
                'Source' => 'Initial Intake',
            ]);
            
        $requests = $this->activeVisit->serviceRequests()
            ->with(['service.department', 'requestedBy'])
            ->get()
            ->map(fn($request) => [
                'id' => $request->id,
                'type' => 'request',
                'Service' => $request->service->name,
                'Department' => $request->service->department?->name ?? 'N/A',
                'Status' => ucfirst(str_replace('_', ' ', $request->status)),
                'Queue Status' => $request->status === 'paid' ? 'In queue' : 'Awaiting payment',
                'Queue Position' => $request->serviceBooking?->queueEntry?->queue_number ?? 'N/A',
                'Provider' => $request->requestedBy?->name ?? 'N/A',
                'Source' => 'Mid-Journey Request',
            ]);
            
        return $bookings->merge($requests);
    }
    
    public function getVisitHistoryProperty()
    {
        return $this->client->visits()
            ->with(['triage', 'intakeAssessment', 'serviceBookings', 'invoices'])
            ->where('id', '!=', $this->activeVisit?->id)
            ->latest('check_in_time')
            ->limit(20)
            ->get()
            ->map(fn($visit) => [
                'id' => $visit->id,
                'Date' => $visit->check_in_time?->format('M d, Y H:i'),
                'Type' => ucfirst(str_replace('_', ' ', $visit->visit_type ?? 'walk_in')),
                'Status' => ucfirst($visit->status),
                'Services' => $visit->serviceBookings->count(),
                'Total Amount' => 'KES ' . number_format($visit->invoices->sum('total_amount'), 2),
                'Paid Amount' => 'KES ' . number_format($visit->invoices->sum('paid_amount'), 2),
            ]);
    }
    
    public function getUpcomingAppointmentsProperty()
    {
        return Appointment::query()
            ->where('client_id', $this->client->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('appointment_date', '>=', now())
            ->with(['service', 'department', 'provider'])
            ->orderBy('appointment_date')
            ->limit(10)
            ->get()
            ->map(fn($apt) => [
                'id' => $apt->id,
                'Date' => $apt->appointment_date->format('M d, Y'),
                'Time' => $apt->appointment_time?->format('H:i') ?? 'Not set',
                'Service' => $apt->service?->name ?? 'N/A',
                'Department' => $apt->department?->name ?? 'N/A',
                'Provider' => $apt->provider?->name ?? 'Not assigned',
                'Status' => ucfirst($apt->status),
                'Notes' => $apt->notes,
            ]);
    }
    
    public function getPastAppointmentsProperty()
    {
        return Appointment::query()
            ->where('client_id', $this->client->id)
            ->where('appointment_date', '<', now())
            ->with(['service', 'department', 'visit'])
            ->orderBy('appointment_date', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($apt) => [
                'Date' => $apt->appointment_date->format('M d, Y'),
                'Service' => $apt->service?->name ?? 'N/A',
                'Status' => ucfirst($apt->status),
                'Attended' => $apt->visit_id ? 'Yes' : 'No',
            ]);
    }
    
    public function getInternalReferralsProperty()
    {
        return InternalReferral::query()
            ->where('client_id', $this->client->id)
            ->with(['fromDepartment', 'toDepartment', 'service', 'referringProvider'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($ref) => [
                'id' => $ref->id,
                'Date' => $ref->created_at->format('M d, Y'),
                'From' => $ref->fromDepartment?->name ?? 'N/A',
                'To' => $ref->toDepartment?->name ?? 'N/A',
                'Service' => $ref->service?->name ?? 'N/A',
                'Referred By' => $ref->referringProvider?->name ?? 'N/A',
                'Status' => ucfirst($ref->status),
                'Reason' => $ref->reason,
            ]);
    }
    
    public function getExternalReferralsProperty()
    {
        return ExternalReferral::query()
            ->where('client_id', $this->client->id)
            ->with(['referringProvider'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($ref) => [
                'Date' => $ref->created_at->format('M d, Y'),
                'Facility' => $ref->referred_to_facility,
                'Specialty' => $ref->specialty,
                'Referred By' => $ref->referringProvider?->name ?? 'N/A',
                'Status' => ucfirst($ref->status),
                'Urgency' => ucfirst($ref->urgency_level ?? 'routine'),
            ]);
    }
    
    // ==========================================
    // FORMS
    // ==========================================
    
    public function requestNewServiceForm(): Form
    {
        return Form::make()
            ->schema([
                Forms\Components\Section::make('Request Additional Services')
                    ->description('Select services to add to the current visit. Client must pay before service delivery.')
                    ->schema([
                        Forms\Components\Select::make('service_ids')
                            ->label('Services')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function() {
                                return Service::query()
                                    ->where('is_active', true)
                                    ->where('branch_id', auth()->user()->branch_id)
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->helperText('Select one or more services')
                            ->columnSpanFull(),
                            
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
                            ->required()
                            ->columnSpanFull(),
                    ])
            ])
            ->statePath('newServiceData');
    }
    
    public function createAppointmentForm(): Form
    {
        return Form::make()
            ->schema([
                Forms\Components\Section::make('Book Appointment')
                    ->schema([
                        Forms\Components\DatePicker::make('appointment_date')
                            ->label('Appointment Date')
                            ->required()
                            ->minDate(now())
                            ->native(false),
                            
                        Forms\Components\TimePicker::make('appointment_time')
                            ->label('Preferred Time')
                            ->seconds(false)
                            ->required(),
                            
                        Forms\Components\Select::make('service_id')
                            ->label('Service')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                            
                        Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\Select::make('provider_id')
                            ->label('Preferred Provider (Optional)')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
            ])
            ->statePath('appointmentData');
    }
    
    public function createInternalReferralForm(): Form
    {
        return Form::make()
            ->schema([
                Forms\Components\Section::make('Internal Referral')
                    ->schema([
                        Forms\Components\Select::make('to_department_id')
                            ->label('Refer To Department')
                            ->relationship('toDepartment', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                            
                        Forms\Components\Select::make('service_id')
                            ->label('Service Requested')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                            
                        Forms\Components\Select::make('urgency_level')
                            ->label('Urgency')
                            ->options([
                                'routine' => 'Routine',
                                'urgent' => 'Urgent',
                                'emergency' => 'Emergency',
                            ])
                            ->default('routine')
                            ->required(),
                            
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for Referral')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                            
                        Forms\Components\Textarea::make('clinical_notes')
                            ->label('Clinical Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
            ])
            ->statePath('internalReferralData');
    }
    
    // ==========================================
    // ACTIONS
    // ==========================================
    
    public function requestNewService(): void
    {
        if (!$this->activeVisit) {
            Notification::make()
                ->danger()
                ->title('No Active Visit')
                ->body('Client must have an active visit to request services.')
                ->send();
            return;
        }
        
        $data = $this->form->getState();
        
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
            
            Notification::make()
                ->success()
                ->title('Services Requested')
                ->body(count($data['service_ids']) . ' service(s) requested. Client must visit Cashier for payment.')
                ->send();
                
            $this->newServiceData = null;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->danger()
                ->title('Request Failed')
                ->body($e->getMessage())
                ->send();
        }
    }
    
    public function createAppointment(): void
    {
        $data = $this->appointmentData;
        
        DB::beginTransaction();
        try {
            Appointment::create([
                'client_id' => $this->client->id,
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'service_id' => $data['service_id'],
                'department_id' => $data['department_id'] ?? null,
                'provider_id' => $data['provider_id'] ?? null,
                'status' => 'scheduled',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            
            DB::commit();
            
            Notification::make()
                ->success()
                ->title('Appointment Booked')
                ->body('Appointment scheduled successfully.')
                ->send();
                
            $this->appointmentData = null;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->danger()
                ->title('Booking Failed')
                ->body($e->getMessage())
                ->send();
        }
    }
    
    public function createInternalReferral(): void
    {
        if (!$this->activeVisit) {
            Notification::make()
                ->danger()
                ->title('No Active Visit')
                ->body('Client must have an active visit to create referrals.')
                ->send();
            return;
        }
        
        $data = $this->internalReferralData;
        
        DB::beginTransaction();
        try {
            $referral = InternalReferral::create([
                'visit_id' => $this->activeVisit->id,
                'client_id' => $this->client->id,
                'from_department_id' => auth()->user()->department_id,
                'to_department_id' => $data['to_department_id'],
                'service_id' => $data['service_id'],
                'referring_provider_id' => auth()->id(),
                'urgency_level' => $data['urgency_level'],
                'reason' => $data['reason'],
                'clinical_notes' => $data['clinical_notes'] ?? null,
                'status' => 'pending',
            ]);
            
            // Create service request automatically
            ServiceRequest::create([
                'visit_id' => $this->activeVisit->id,
                'client_id' => $this->client->id,
                'service_id' => $data['service_id'],
                'requested_by' => auth()->id(),
                'request_type' => 'internal_referral',
                'status' => 'pending_payment',
                'urgency_level' => $data['urgency_level'],
                'clinical_indication' => $data['reason'],
                'internal_referral_id' => $referral->id,
            ]);
            
            DB::commit();
            
            Notification::make()
                ->success()
                ->title('Referral Created')
                ->body('Internal referral created. Client must pay before proceeding.')
                ->send();
                
            $this->internalReferralData = null;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->danger()
                ->title('Referral Failed')
                ->body($e->getMessage())
                ->send();
        }
    }
    
    public function refreshData(): void
    {
        $this->mount();
        
        Notification::make()
            ->success()
            ->title('Data Refreshed')
            ->send();
    }
}