<?php

namespace App\Filament\Resources\IntakeAssessmentResource\Pages;

use App\Filament\Resources\IntakeAssessmentResource;
use App\Models\Visit;
use App\Models\Client;
use App\Models\IntakeAssessment;
use App\Models\ServiceBooking;
use App\Models\County;
use App\Models\SubCounty;
use App\Models\Ward;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;

class CreateIntakeAssessment extends Page
{
    protected static string $resource = IntakeAssessmentResource::class;

    protected static string $view = 'filament.resources.pages.intake.create-intake-assessment';

    public ?Visit $visit = null;
    public ?Client $client = null;
    public ?array $data = [];

    public function mount(): void
    {
        $visitId = request()->query('visit');
        
        if (!$visitId) {
            Notification::make()
                ->danger()
                ->title('No Visit Selected')
                ->body('Please select a visit from the intake queue.')
                ->persistent()
                ->send();
            
            redirect()->route('filament.admin.resources.intake-assessments.index');
            return;
        }

        $this->visit = Visit::with(['client', 'triage'])->find($visitId);
        
        if (!$this->visit) {
            Notification::make()
                ->danger()
                ->title('Visit Not Found')
                ->body('The selected visit could not be found.')
                ->persistent()
                ->send();
            
            redirect()->route('filament.admin.resources.intake-assessments.index');
            return;
        }

        $this->client = $this->visit->client;

        // Pre-fill form with existing client data
        $this->form->fill([
            'visit_id' => $this->visit->id,
            'client_id' => $this->client->id,
            
            // Pre-fill existing client data
            'phone_secondary' => $this->client->phone_secondary,
            'email' => $this->client->email,
            'county_id' => $this->client->county_id,
            'sub_county_id' => $this->client->sub_county_id,
            'ward_id' => $this->client->ward_id,
            'address' => $this->client->address,
            'ncpwd_number' => $this->client->ncpwd_number,
            
            // Services will be selected fresh
            'selected_services' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Hidden fields
                Forms\Components\Hidden::make('visit_id'),
                Forms\Components\Hidden::make('client_id'),

                // Client Info Display
                Forms\Components\Section::make('Client Information')
                    ->description('Review and complete client profile')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Placeholder::make('client_summary')
                            ->label('')
                            ->content(fn () => $this->getClientSummaryHtml())
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                // Contact Information
                Forms\Components\Section::make('Contact Information')
                    ->description('Complete contact details')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Forms\Components\TextInput::make('phone_secondary')
                            ->label('Secondary Phone')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('+254712345678')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('client@example.com')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // Location Information
                Forms\Components\Section::make('Location Details')
                    ->description('Complete residential address')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Forms\Components\Select::make('county_id')
                            ->label('County')
                            ->options(County::active()->ordered()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('sub_county_id', null);
                                $set('ward_id', null);
                            })
                            ->columnSpan(1),

                        Forms\Components\Select::make('sub_county_id')
                            ->label('Sub-County')
                            ->options(function (Get $get) {
                                $countyId = $get('county_id');
                                if (!$countyId) return [];
                                return SubCounty::where('county_id', $countyId)
                                    ->active()
                                    ->ordered()
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('ward_id', null))
                            ->disabled(fn (Get $get): bool => !$get('county_id'))
                            ->columnSpan(1),

                        Forms\Components\Select::make('ward_id')
                            ->label('Ward')
                            ->options(function (Get $get) {
                                $subCountyId = $get('sub_county_id');
                                if (!$subCountyId) return [];
                                return Ward::where('sub_county_id', $subCountyId)
                                    ->active()
                                    ->ordered()
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabled(fn (Get $get): bool => !$get('sub_county_id'))
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('address')
                            ->label('Physical Address / Landmarks')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Building name, street, estate, landmarks...')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // Disability & NCPWD
                Forms\Components\Section::make('Disability Information')
                    ->description('Disability details and NCPWD registration')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\Select::make('disability_type')
                            ->label('Primary Disability Type')
                            ->options([
                                'physical' => 'Physical Disability',
                                'visual' => 'Visual Impairment',
                                'hearing' => 'Hearing Impairment',
                                'intellectual' => 'Intellectual Disability',
                                'mental' => 'Mental/Psychosocial Disability',
                                'multiple' => 'Multiple Disabilities',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\Select::make('disability_severity')
                            ->label('Severity Level')
                            ->options([
                                'mild' => 'Mild',
                                'moderate' => 'Moderate',
                                'severe' => 'Severe',
                                'profound' => 'Profound',
                            ])
                            ->required()
                            ->native(false)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('ncpwd_number')
                            ->label('NCPWD Registration Number')
                            ->maxLength(50)
                            ->placeholder('NCPWD/2024/12345')
                            ->helperText('Leave empty if not registered with NCPWD')
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('ncpwd_registration_date')
                            ->label('NCPWD Registration Date')
                            ->native(false)
                            ->maxDate(now())
                            ->visible(fn (Get $get) => !empty($get('ncpwd_number')))
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('disability_details')
                            ->label('Disability Details & Impact')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Describe the disability, onset, functional impact, assistive devices used...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // Medical History
                Forms\Components\Section::make('Medical History')
                    ->description('Current conditions, medications, allergies')
                    ->icon('heroicon-o-heart')
                    ->schema([
                        Forms\Components\Textarea::make('medical_conditions')
                            ->label('Current Medical Conditions')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('List all current medical conditions, diagnoses...')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('current_medications')
                            ->label('Current Medications')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('List all medications currently taking...')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('allergies')
                            ->label('Known Allergies')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Food, medication, environmental allergies...')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('surgical_history')
                            ->label('Surgical History')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Previous surgeries, dates...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // Insurance Information
                Forms\Components\Section::make('Insurance & Payment')
                    ->description('Insurance coverage and payment details')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\Select::make('primary_payment_method')
                            ->label('Primary Payment Method')
                            ->options([
                                'sha' => 'SHA (Social Health Authority)',
                                'ncpwd' => 'NCPWD Subsidy',
                                'cash' => 'Cash',
                                'mpesa' => 'M-PESA',
                                'private_insurance' => 'Private Insurance',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('sha_number')
                            ->label('SHA Number')
                            ->maxLength(50)
                            ->visible(fn (Get $get) => $get('primary_payment_method') === 'sha')
                            ->placeholder('SHA/2024/12345')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('insurance_provider')
                            ->label('Insurance Provider Name')
                            ->maxLength(100)
                            ->visible(fn (Get $get) => $get('primary_payment_method') === 'private_insurance')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('insurance_policy_number')
                            ->label('Policy Number')
                            ->maxLength(100)
                            ->visible(fn (Get $get) => $get('primary_payment_method') === 'private_insurance')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // ⭐ SERVICE SELECTION - CRITICAL!
                Forms\Components\Section::make('Service Selection')
                    ->description('⭐ SELECT ALL SERVICES NEEDED TODAY - This determines billing and department routing')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->schema([
                        Forms\Components\CheckboxList::make('selected_services')
                            ->label('Select All Services Required')
                            ->options(function () {
                                return Service::active()
                                    ->with('department')
                                    ->get()
                                    ->groupBy('department.name')
                                    ->map(function ($services, $department) {
                                        return $services->mapWithKeys(function ($service) {
                                            return [
                                                $service->id => $service->name . ' (' . $service->department->name . ')',
                                            ];
                                        });
                                    })
                                    ->flatten();
                            })
                            ->required()
                            ->columns(2)
                            ->searchable()
                            ->bulkToggleable()
                            ->helperText('Select all services the client needs today. You can select multiple services.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('service_info')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="rounded-lg bg-primary-50 border border-primary-200 p-4">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-primary-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div class="flex-1">
                                            <h4 class="text-sm font-semibold text-primary-900 mb-1">Important: Service Selection</h4>
                                            <ul class="text-sm text-primary-800 space-y-1">
                                                <li>• Selected services will be used to generate the invoice</li>
                                                <li>• Services determine which department queues the client enters</li>
                                                <li>• Client must pay before entering service queues</li>
                                                <li>• Multiple services can be selected for same visit</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            '))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                // Social & Educational
                Forms\Components\Section::make('Social & Educational Background')
                    ->description('Social circumstances and education details')
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        Forms\Components\Select::make('education_level')
                            ->label('Highest Education Level')
                            ->options([
                                'none' => 'No Formal Education',
                                'primary' => 'Primary',
                                'secondary' => 'Secondary',
                                'tertiary' => 'Tertiary/University',
                                'special' => 'Special Education',
                            ])
                            ->native(false)
                            ->columnSpan(1),

                        Forms\Components\Select::make('employment_status')
                            ->label('Employment Status')
                            ->options([
                                'employed' => 'Employed',
                                'self_employed' => 'Self-Employed',
                                'student' => 'Student',
                                'unemployed' => 'Unemployed',
                                'retired' => 'Retired',
                                'unable_to_work' => 'Unable to Work',
                            ])
                            ->native(false)
                            ->columnSpan(1),

                        Forms\Components\Select::make('living_situation')
                            ->label('Living Situation')
                            ->options([
                                'family' => 'With Family',
                                'alone' => 'Living Alone',
                                'institution' => 'Institution/Care Home',
                                'guardian' => 'With Guardian',
                                'other' => 'Other',
                            ])
                            ->native(false)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('primary_caregiver')
                            ->label('Primary Caregiver/Support Person')
                            ->maxLength(100)
                            ->placeholder('Name of main caregiver')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('social_support_notes')
                            ->label('Social Support & Barriers')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Support system, barriers to accessing services, special considerations...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(true),

                // Intake Notes
                Forms\Components\Section::make('Intake Assessment Notes')
                    ->description('Overall assessment and recommendations')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\Textarea::make('intake_notes')
                            ->label('Assessment Notes')
                            ->rows(4)
                            ->maxLength(2000)
                            ->placeholder('Overall assessment, observations, recommendations for service providers...')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('special_needs')
                            ->label('Special Needs / Accommodations Required')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Communication needs, accessibility requirements, etc...')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(true),
            ])
            ->statePath('data');
    }

    protected function getClientSummaryHtml(): HtmlString
    {
        if (!$this->client) {
            return new HtmlString('<p class="text-gray-500">No client data available</p>');
        }

        $age = $this->client->date_of_birth 
            ? \Carbon\Carbon::parse($this->client->date_of_birth)->age 
            : $this->client->estimated_age;

        $triageRisk = $this->visit->triage ? ucfirst($this->visit->triage->risk_level) : 'N/A';

        return new HtmlString("
            <div class='rounded-lg border-2 border-primary-200 bg-primary-50 p-4'>
                <div class='flex items-center justify-between mb-3'>
                    <h3 class='text-xl font-bold text-primary-900'>{$this->client->full_name}</h3>
                    <div class='flex items-center gap-2'>
                        <span class='px-3 py-1 bg-primary-600 text-white text-sm font-semibold rounded-full'>
                            {$this->client->uci}
                        </span>
                        <span class='px-3 py-1 bg-blue-600 text-white text-sm font-semibold rounded-full'>
                            {$this->visit->visit_number}
                        </span>
                    </div>
                </div>
                
                <div class='grid grid-cols-4 gap-4 mb-3'>
                    <div>
                        <p class='text-xs text-gray-600 mb-1'>Age</p>
                        <p class='font-semibold text-gray-900'>{$age} years</p>
                    </div>
                    <div>
                        <p class='text-xs text-gray-600 mb-1'>Gender</p>
                        <p class='font-semibold text-gray-900'>" . ucfirst($this->client->gender) . "</p>
                    </div>
                    <div>
                        <p class='text-xs text-gray-600 mb-1'>Primary Phone</p>
                        <p class='font-semibold text-gray-900'>{$this->client->phone_primary}</p>
                    </div>
                    <div>
                        <p class='text-xs text-gray-600 mb-1'>Triage Risk</p>
                        <p class='font-semibold text-gray-900'>{$triageRisk}</p>
                    </div>
                </div>

                <div class='grid grid-cols-2 gap-4 pt-3 border-t border-primary-200'>
                    <div>
                        <p class='text-xs text-gray-600 mb-1'>Visit Type</p>
                        <p class='font-semibold text-gray-900'>" . ucwords(str_replace('_', ' ', $this->visit->visit_type)) . "</p>
                    </div>
                    <div>
                        <p class='text-xs text-gray-600 mb-1'>Visit Purpose</p>
                        <p class='font-semibold text-gray-900'>" . ucfirst($this->visit->visit_purpose) . "</p>
                    </div>
                </div>
            </div>
        ");
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Complete Intake & Send to Billing')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action('save')
                ->size('lg'),

            Forms\Components\Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(route('filament.admin.resources.intake-assessments.index')),
        ];
    }

    public function save(): void
    {
        try {
            DB::beginTransaction();

            $data = $this->form->getState();

            // Validate required data
            if (empty($data['selected_services'])) {
                Notification::make()
                    ->danger()
                    ->title('Services Required')
                    ->body('Please select at least one service.')
                    ->send();
                return;
            }

            // Update client profile
            $this->client->update([
                'phone_secondary' => $data['phone_secondary'] ?? null,
                'email' => $data['email'] ?? null,
                'county_id' => $data['county_id'] ?? null,
                'sub_county_id' => $data['sub_county_id'] ?? null,
                'ward_id' => $data['ward_id'] ?? null,
                'address' => $data['address'] ?? null,
                'ncpwd_number' => $data['ncpwd_number'] ?? null,
            ]);

            // Create intake assessment record
            $intake = IntakeAssessment::create([
                'visit_id' => $this->visit->id,
                'client_id' => $this->client->id,
                'assessed_by' => auth()->id(),
                'assessment_date' => now(),
                
                // All captured data
                'disability_type' => $data['disability_type'] ?? null,
                'disability_severity' => $data['disability_severity'] ?? null,
                'disability_details' => $data['disability_details'] ?? null,
                'ncpwd_registration_date' => $data['ncpwd_registration_date'] ?? null,
                
                'medical_conditions' => $data['medical_conditions'] ?? null,
                'current_medications' => $data['current_medications'] ?? null,
                'allergies' => $data['allergies'] ?? null,
                'surgical_history' => $data['surgical_history'] ?? null,
                
                'primary_payment_method' => $data['primary_payment_method'] ?? null,
                'sha_number' => $data['sha_number'] ?? null,
                'insurance_provider' => $data['insurance_provider'] ?? null,
                'insurance_policy_number' => $data['insurance_policy_number'] ?? null,
                
                'education_level' => $data['education_level'] ?? null,
                'employment_status' => $data['employment_status'] ?? null,
                'living_situation' => $data['living_situation'] ?? null,
                'primary_caregiver' => $data['primary_caregiver'] ?? null,
                'social_support_notes' => $data['social_support_notes'] ?? null,
                
                'intake_notes' => $data['intake_notes'] ?? null,
                'special_needs' => $data['special_needs'] ?? null,
            ]);

            // Create service bookings
            foreach ($data['selected_services'] as $serviceId) {
                $service = Service::find($serviceId);
                
                ServiceBooking::create([
                    'visit_id' => $this->visit->id,
                    'client_id' => $this->client->id,
                    'service_id' => $serviceId,
                    'department_id' => $service->department_id,
                    'booked_at' => now(),
                    'status' => 'pending_payment',
                    'service_status' => 'not_started',
                    'booked_by' => auth()->id(),
                ]);
            }

            // Complete intake stage
            $this->visit->completeStage();

            // Move to billing stage
            $this->visit->moveToStage('billing');

            DB::commit();

            Notification::make()
                ->success()
                ->title('Intake Assessment Completed')
                ->body("Client {$this->client->full_name} moved to billing. " . count($data['selected_services']) . " service(s) booked.")
                ->duration(10000)
                ->send();

            redirect()->route('filament.admin.resources.intake-assessments.index');

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->danger()
                ->title('Save Failed')
                ->body('Error: ' . $e->getMessage())
                ->persistent()
                ->send();
        }
    }
}