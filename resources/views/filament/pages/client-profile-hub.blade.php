{{-- resources/views/filament/pages/client-profile-hub.blade.php --}}

<x-filament-panels::page>
    <div class="space-y-6">
        
        {{-- Header Card with Quick Stats --}}
        <x-filament::section>
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                
                {{-- Client Photo & Basic Info --}}
                <div class="lg:col-span-1 flex flex-col items-center space-y-4">
                    <div class="w-32 h-32 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-4xl font-bold shadow-lg">
                        {{ substr($client->first_name ?? 'C', 0, 1) }}{{ substr($client->last_name ?? 'L', 0, 1) }}
                    </div>
                    <div class="text-center">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $client->full_name }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            UCI: {{ $client->uci }}
                        </p>
                        <div class="mt-2 flex gap-2 justify-center flex-wrap">
                            @if($activeVisit)
                                <x-filament::badge color="success" icon="heroicon-o-check-circle">
                                    Active Visit
                                </x-filament::badge>
                            @else
                                <x-filament::badge color="gray" icon="heroicon-o-x-circle">
                                    No Active Visit
                                </x-filament::badge>
                            @endif
                            
                            @if($this->activeInsurances->isNotEmpty())
                                <x-filament::badge color="info" icon="heroicon-o-shield-check">
                                    {{ $this->activeInsurances->count() }} Insurance(s)
                                </x-filament::badge>
                            @endif
                        </div>
                    </div>
                </div>
                
                {{-- Quick Stats Grid --}}
                <div class="lg:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4">
                    
                    {{-- Total Visits --}}
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg p-4 border border-blue-200 dark:border-blue-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Visits</p>
                                <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                                    {{ $client->visits()->count() }}
                                </p>
                            </div>
                            <x-filament::icon
                                icon="heroicon-o-calendar-days"
                                class="w-10 h-10 text-blue-400"
                            />
                        </div>
                    </div>
                    
                    {{-- Services Received --}}
                    <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-lg p-4 border border-green-200 dark:border-green-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-green-600 dark:text-green-400">Services</p>
                                <p class="text-2xl font-bold text-green-900 dark:text-green-100">
                                    {{ \App\Models\ServiceBooking::whereHas('visit', fn($q) => $q->where('client_id', $client->id))->count() }}
                                </p>
                            </div>
                            <x-filament::icon
                                icon="heroicon-o-clipboard-document-check"
                                class="w-10 h-10 text-green-400"
                            />
                        </div>
                    </div>
                    
                    {{-- Upcoming Appointments --}}
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-lg p-4 border border-purple-200 dark:border-purple-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Appointments</p>
                                <p class="text-2xl font-bold text-purple-900 dark:text-purple-100">
                                    {{ $this->upcomingAppointments->count() }}
                                </p>
                            </div>
                            <x-filament::icon
                                icon="heroicon-o-clock"
                                class="w-10 h-10 text-purple-400"
                            />
                        </div>
                    </div>
                    
                </div>
            </div>
            
            {{-- Action Buttons --}}
            <div class="mt-6 flex flex-wrap gap-3">
                @if($activeVisit)
                    <x-filament::button
                        color="primary"
                        icon="heroicon-o-plus-circle"
                        wire:click="$dispatch('open-modal', { id: 'request-service-modal' })"
                    >
                        Request New Service
                    </x-filament::button>
                    
                    <x-filament::button
                        color="info"
                        icon="heroicon-o-arrow-path-rounded-square"
                        wire:click="$dispatch('open-modal', { id: 'internal-referral-modal' })"
                    >
                        Internal Referral
                    </x-filament::button>
                @endif
                
                <x-filament::button
                    color="success"
                    icon="heroicon-o-calendar"
                    wire:click="$dispatch('open-modal', { id: 'appointment-modal' })"
                >
                    Book Appointment
                </x-filament::button>
                
                <x-filament::button
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    wire:click="refreshData"
                >
                    Refresh
                </x-filament::button>
            </div>
        </x-filament::section>
        
        {{-- Tabs Navigation --}}
        <x-filament::tabs>
            <x-filament::tabs.item
                :active="$activeTab === 'overview'"
                wire:click="$set('activeTab', 'overview')"
                icon="heroicon-o-home"
            >
                Overview
            </x-filament::tabs.item>
            
            <x-filament::tabs.item
                :active="$activeTab === 'current-visit'"
                wire:click="$set('activeTab', 'current-visit')"
                icon="heroicon-o-clipboard-document-list"
                :badge="$activeVisit ? 'Active' : null"
                :badge-color="$activeVisit ? 'success' : null"
            >
                Current Visit
            </x-filament::tabs.item>
            
            <x-filament::tabs.item
                :active="$activeTab === 'services'"
                wire:click="$set('activeTab', 'services')"
                icon="heroicon-o-clipboard-document-check"
            >
                Services
            </x-filament::tabs.item>
            
            <x-filament::tabs.item
                :active="$activeTab === 'appointments'"
                wire:click="$set('activeTab', 'appointments')"
                icon="heroicon-o-calendar"
                :badge="$this->upcomingAppointments->count() > 0 ? $this->upcomingAppointments->count() : null"
            >
                Appointments
            </x-filament::tabs.item>
            
            <x-filament::tabs.item
                :active="$activeTab === 'referrals'"
                wire:click="$set('activeTab', 'referrals')"
                icon="heroicon-o-arrow-path-rounded-square"
            >
                Referrals
            </x-filament::tabs.item>
            
            <x-filament::tabs.item
                :active="$activeTab === 'medical'"
                wire:click="$set('activeTab', 'medical')"
                icon="heroicon-o-heart"
            >
                Medical Info
            </x-filament::tabs.item>
            
            <x-filament::tabs.item
                :active="$activeTab === 'demographics'"
                wire:click="$set('activeTab', 'demographics')"
                icon="heroicon-o-user"
            >
                Demographics
            </x-filament::tabs.item>
            
            <x-filament::tabs.item
                :active="$activeTab === 'history'"
                wire:click="$set('activeTab', 'history')"
                icon="heroicon-o-clock"
            >
                Visit History
            </x-filament::tabs.item>
        </x-filament::tabs>
        
        {{-- Tab Content --}}
        <div class="space-y-6">
            
            {{-- ============================================ --}}
            {{-- OVERVIEW TAB --}}
            {{-- ============================================ --}}
            @if($activeTab === 'overview')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    {{-- Quick Demographics --}}
                    <x-filament::section
                        heading="Quick Information"
                        icon="heroicon-o-identification"
                    >
                        @php $demographics = $this->demographics; @endphp
                        <dl class="grid grid-cols-2 gap-4">
                            @foreach($demographics['basic'] as $label => $value)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-filament::section>
                    
                    {{-- Active Insurances --}}
                    <x-filament::section
                        heading="Active Insurance Coverage"
                        icon="heroicon-o-shield-check"
                    >
                        @forelse($this->activeInsurances as $insurance)
                            <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700 mb-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-semibold text-green-900 dark:text-green-100">
                                            {{ $insurance['Provider'] }}
                                        </h4>
                                        <p class="text-sm text-green-700 dark:text-green-300">
                                            Member: {{ $insurance['Member Number'] }}
                                        </p>
                                        <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                            Valid until: {{ $insurance['Valid Until'] }}
                                        </p>
                                    </div>
                                    <x-filament::badge color="success">
                                        {{ $insurance['Status'] }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No active insurance coverage</p>
                        @endforelse
                    </x-filament::section>
                    
                    {{-- Allergies Alert --}}
                    @if($this->allergies->isNotEmpty())
                        <x-filament::section
                            heading="⚠️ Allergies & Alerts"
                            icon="heroicon-o-exclamation-triangle"
                            class="lg:col-span-2"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @foreach($this->allergies as $allergy)
                                    <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border-l-4 border-red-500">
                                        <div class="flex justify-between">
                                            <h5 class="font-semibold text-red-900 dark:text-red-100">
                                                {{ $allergy['Allergen'] }}
                                            </h5>
                                            <x-filament::badge color="danger">
                                                {{ $allergy['Severity'] }}
                                            </x-filament::badge>
                                        </div>
                                        <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                                            {{ $allergy['Reaction'] }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    @endif
                    
                    {{-- Current Visit Summary --}}
                    @if($activeVisit)
                        <x-filament::section
                            heading="Current Visit Summary"
                            icon="heroicon-o-clipboard-document-check"
                            class="lg:col-span-2"
                        >
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Visit Number</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">
                                        {{ $activeVisit->visit_number }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Check-in Time</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">
                                        {{ $activeVisit->check_in_time?->format('H:i') }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Current Stage</p>
                                    <x-filament::badge color="warning">
                                        {{ ucfirst($activeVisit->current_stage) }}
                                    </x-filament::badge>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Services</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">
                                        {{ $activeVisit->serviceBookings->count() }}
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>
                    @endif
                    
                </div>
            @endif
            
            {{-- ============================================ --}}
            {{-- CURRENT VISIT TAB --}}
            {{-- ============================================ --}}
            @if($activeTab === 'current-visit')
                @if($activeVisit)
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        {{-- Visit Summary --}}
                        <x-filament::section
                            heading="Visit Summary"
                            icon="heroicon-o-clipboard-document-list"
                            class="lg:col-span-2"
                        >
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Visit Number</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $activeVisit->visit_number }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Visit Date</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $activeVisit->check_in_time?->format('M d, Y H:i') }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Visit Type</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $activeVisit->visit_type ?? 'Walk-in')) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                                    <x-filament::badge :color="$activeVisit->status === 'in_progress' ? 'warning' : 'success'">
                                        {{ ucfirst($activeVisit->status) }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        </x-filament::section>
                        
                        {{-- Triage Information --}}
                        @if($this->currentVisitTriage)
                            <x-filament::section heading="Triage Assessment" icon="heroicon-o-heart">
                                <div class="space-y-4">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vital Signs</h4>
                                        <dl class="grid grid-cols-2 gap-3">
                                            @foreach($this->currentVisitTriage['vital_signs'] as $label => $value)
                                                <div class="flex justify-between py-1 text-sm">
                                                    <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                                    <dd class="font-semibold text-gray-900 dark:text-white">{{ $value }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </div>
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Assessment</h4>
                                        <dl class="space-y-2">
                                            @foreach($this->currentVisitTriage['assessment'] as $label => $value)
                                                <div class="flex justify-between py-1 text-sm">
                                                    <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                                    <dd class="font-semibold text-gray-900 dark:text-white">{{ $value }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </div>
                                </div>
                            </x-filament::section>
                        @endif
                        
                        {{-- Intake Assessment --}}
                        @if($this->currentVisitIntake)
                            <x-filament::section heading="Intake Assessment" icon="heroicon-o-clipboard-document">
                                <div class="space-y-4">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Assessment</h4>
                                        <dl class="space-y-2">
                                            @foreach($this->currentVisitIntake['assessment'] as $label => $value)
                                                <div class="py-1">
                                                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                                    <dd class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $value }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </div>
                                </div>
                            </x-filament::section>
                        @endif
                        
                    </div>
                @else
                    <x-filament::section>
                        <div class="text-center py-12">
                            <x-filament::icon icon="heroicon-o-x-circle" class="w-16 h-16 text-gray-400 mx-auto mb-4"/>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Active Visit</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">This client doesn't have an active visit at the moment.</p>
                        </div>
                    </x-filament::section>
                @endif
            @endif
            
            {{-- ============================================ --}}
            {{-- SERVICES TAB --}}
            {{-- ============================================ --}}
            @if($activeTab === 'services')
                <x-filament::section heading="Current Visit Services" icon="heroicon-o-clipboard-document-check">
                    @forelse($this->currentServices as $service)
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 mb-3">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h5 class="font-semibold text-gray-900 dark:text-white">{{ $service['Service'] }}</h5>
                                        <x-filament::badge color="info" size="sm">{{ $service['Source'] }}</x-filament::badge>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                        <div>
                                            <p class="text-gray-500 dark:text-gray-400">Department</p>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $service['Department'] }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 dark:text-gray-400">Provider</p>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $service['Provider'] }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 dark:text-gray-400">Queue Status</p>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $service['Queue Status'] }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 dark:text-gray-400">Queue #</p>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $service['Queue Position'] }}</p>
                                        </div>
                                    </div>
                                </div>
                                <x-filament::badge :color="match($service['Status']) {
                                    'Pending' => 'gray', 'Pending payment' => 'warning', 'Scheduled' => 'info',
                                    'In progress' => 'warning', 'Completed' => 'success', default => 'gray'
                                }">{{ $service['Status'] }}</x-filament::badge>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">No services for this visit</p>
                    @endforelse
                </x-filament::section>
            @endif
            
            {{-- ============================================ --}}
            {{-- APPOINTMENTS TAB --}}
            {{-- ============================================ --}}
            @if($activeTab === 'appointments')
                <x-filament::section heading="Upcoming Appointments" icon="heroicon-o-calendar">
                    @forelse($this->upcomingAppointments as $apt)
                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700 mb-3">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h5 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">{{ $apt['Service'] }}</h5>
                                    <div class="grid grid-cols-4 gap-3 text-sm">
                                        <div><p class="text-blue-600 dark:text-blue-400">Date</p><p class="font-medium">{{ $apt['Date'] }}</p></div>
                                        <div><p class="text-blue-600 dark:text-blue-400">Time</p><p class="font-medium">{{ $apt['Time'] }}</p></div>
                                        <div><p class="text-blue-600 dark:text-blue-400">Department</p><p class="font-medium">{{ $apt['Department'] }}</p></div>
                                        <div><p class="text-blue-600 dark:text-blue-400">Provider</p><p class="font-medium">{{ $apt['Provider'] }}</p></div>
                                    </div>
                                </div>
                                <x-filament::badge :color="$apt['Status'] === 'Confirmed' ? 'success' : 'info'">{{ $apt['Status'] }}</x-filament::badge>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">No upcoming appointments</p>
                    @endforelse
                </x-filament::section>
            @endif
            
            {{-- Other tabs can be added similarly... --}}
            
        </div>
        
    </div>
    
    {{-- ============================================ --}}
    {{-- MODALS --}}
    {{-- ============================================ --}}
    
    {{-- Request New Service Modal --}}
    <x-filament::modal id="request-service-modal" width="3xl">
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-plus-circle" class="w-6 h-6 text-primary-500"/>
                <span>Request Additional Service</span>
            </div>
        </x-slot>
        <x-slot name="description">Add services to the current visit. Client must pay at Cashier before service delivery.</x-slot>
        <form wire:submit="requestNewService">
            {{ $this->requestNewServiceForm }}
            <div class="mt-6 flex justify-end gap-3">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'request-service-modal' })">Cancel</x-filament::button>
                <x-filament::button type="submit" color="primary" icon="heroicon-o-check">Submit Request</x-filament::button>
            </div>
        </form>
    </x-filament::modal>
    
    {{-- Book Appointment Modal --}}
    <x-filament::modal id="appointment-modal" width="3xl">
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-calendar" class="w-6 h-6 text-success-500"/>
                <span>Book Appointment</span>
            </div>
        </x-slot>
        <x-slot name="description">Schedule a future appointment for this client</x-slot>
        <form wire:submit="createAppointment">
            {{ $this->createAppointmentForm }}
            <div class="mt-6 flex justify-end gap-3">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'appointment-modal' })">Cancel</x-filament::button>
                <x-filament::button type="submit" color="success" icon="heroicon-o-check-circle">Book Appointment</x-filament::button>
            </div>
        </form>
    </x-filament::modal>
    
    {{-- Internal Referral Modal --}}
    <x-filament::modal id="internal-referral-modal" width="3xl">
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-arrow-path-rounded-square" class="w-6 h-6 text-info-500"/>
                <span>Create Internal Referral</span>
            </div>
        </x-slot>
        <x-slot name="description">Refer this client to another department. Client must pay before service delivery.</x-slot>
        <form wire:submit="createInternalReferral">
            {{ $this->createInternalReferralForm }}
            <div class="mt-6 flex justify-end gap-3">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'internal-referral-modal' })">Cancel</x-filament::button>
                <x-filament::button type="submit" color="info" icon="heroicon-o-check-circle">Create Referral</x-filament::button>
            </div>
        </form>
    </x-filament::modal>
    
</x-filament-panels::page>