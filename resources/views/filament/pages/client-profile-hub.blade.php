{{-- resources/views/filament/pages/client-profile-hub.blade.php --}}
<x-filament-panels::page>

@if(!$client)
    <x-filament::section>
        <div class="flex flex-col items-center justify-center py-24 space-y-4">
            <x-filament::icon icon="heroicon-o-user-circle" class="w-20 h-20 text-gray-300 dark:text-gray-600"/>
            <h3 class="text-xl font-semibold text-gray-500 dark:text-gray-400">No Client Selected</h3>
            <p class="text-sm text-gray-400 dark:text-gray-500">Navigate to a client record and use the "Open Profile Hub" action.</p>
        </div>
    </x-filament::section>
@else

{{-- ====================================================================== --}}
{{-- HEADER CARD                                                             --}}
{{-- ====================================================================== --}}
@php
    $age    = $client->estimated_age ?? $client->date_of_birth?->age ?? null;
    $gender = $client->gender ?? null;
    $avatarGradient = match($gender) {
        'male'   => 'from-blue-500 to-blue-700',
        'female' => 'from-rose-400 to-rose-600',
        default  => 'from-gray-400 to-gray-600',
    };
    $totalVisits = $client->visits()->count();
@endphp
<div class="relative space-y-6">
    <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[30rem] rounded-[2rem] bg-[linear-gradient(180deg,_rgba(248,250,252,0.98),_rgba(241,245,249,0.88))] dark:bg-[linear-gradient(180deg,_rgba(15,23,42,0.94),_rgba(2,6,23,0.82))]"></div>

<div class="relative overflow-hidden rounded-[1.5rem] border border-slate-200 dark:border-slate-700 bg-white/95 dark:bg-slate-900/94 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.28)] backdrop-blur">
    <div class="absolute inset-x-0 top-0 h-20 bg-gradient-to-r from-slate-50 via-white to-sky-50/60 dark:from-slate-900 dark:via-slate-900 dark:to-sky-950/20"></div>
    <div class="p-6 sm:p-7">

        {{-- Top row: avatar + identity + stats --}}
            <div class="relative flex flex-col sm:flex-row gap-6">

            {{-- Avatar --}}
            <div class="relative flex-shrink-0 self-start">
                @if($client->photo)
                    <img src="{{ asset('storage/' . $client->photo) }}"
                         class="w-18 h-18 rounded-2xl object-cover shadow ring-2 ring-gray-100 dark:ring-gray-700 w-[72px] h-[72px]"/>
                @else
                    <div class="w-[72px] h-[72px] rounded-2xl bg-gradient-to-br {{ $avatarGradient }} flex items-center justify-center text-white text-2xl font-bold shadow-md select-none">
                        {{ strtoupper(substr($client->first_name ?? '?', 0, 1) . substr($client->last_name ?? '', 0, 1)) }}
                    </div>
                @endif
                <span class="absolute -bottom-1.5 -right-1.5 w-5 h-5 rounded-full border-[3px] border-white dark:border-gray-900 shadow-sm
                    {{ $activeVisit ? 'bg-emerald-400' : 'bg-gray-300 dark:bg-gray-600' }}"
                    title="{{ $activeVisit ? 'Active visit' : 'No active visit' }}">
                </span>
            </div>

            {{-- Identity column --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white leading-tight">
                        {{ $client->full_name }}
                    </h1>
                    @if($age)
                        <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">{{ $age }} yrs</span>
                    @endif
                    @if($gender)
                        <span class="text-sm text-gray-400 dark:text-gray-500">{{ ucfirst($gender) }}</span>
                    @endif
                </div>

                {{-- UCI + IDs row --}}
                <div class="flex flex-wrap items-center gap-2.5 mt-2.5">
                    <span class="font-mono text-xs bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 px-2.5 py-1 rounded-md shadow-sm">
                        {{ $client->uci }}
                    </span>
                    @if($client->sha_number)
                        <span class="inline-flex items-center gap-1 text-xs bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 px-2 py-0.5 rounded-md border border-teal-200 dark:border-teal-700 font-medium">
                            <x-filament::icon icon="heroicon-m-shield-check" class="w-3 h-3"/>
                            SHA
                        </span>
                    @endif
                    @if($client->ncpwd_number)
                        <span class="inline-flex items-center gap-1 text-xs bg-violet-50 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 px-2 py-0.5 rounded-md border border-violet-200 dark:border-violet-700 font-medium">
                            <x-filament::icon icon="heroicon-m-star" class="w-3 h-3"/>
                            NCPWD
                        </span>
                    @endif
                    @if($client->county)
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            <x-filament::icon icon="heroicon-m-map-pin" class="w-3 h-3 inline -mt-0.5"/>
                            {{ $client->county->name }}
                        </span>
                    @endif
                </div>

                {{-- Status badges --}}
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    @if($activeVisit)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 px-2.5 py-1 rounded-full border border-emerald-200 dark:border-emerald-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                            {{ ucfirst(str_replace('_', ' ', $activeVisit->current_stage)) }}
                        </span>
                    @endif
                    @if($this->activeInsurances->isNotEmpty())
                        <x-filament::badge color="info" icon="heroicon-m-shield-check" size="sm">
                            {{ $this->activeInsurances->count() }} Insurance
                        </x-filament::badge>
                    @endif
                    @if($this->allergies->isNotEmpty())
                        <x-filament::badge color="danger" icon="heroicon-m-exclamation-triangle" size="sm">
                            {{ $this->allergies->count() }} {{ $this->allergies->count() === 1 ? 'Allergy' : 'Allergies' }}
                        </x-filament::badge>
                    @endif
                    @if($client->phone_primary)
                        <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">
                            <x-filament::icon icon="heroicon-m-phone" class="w-3 h-3 inline -mt-0.5 mr-0.5"/>{{ $client->phone_primary }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Stats strip --}}
            <div class="flex sm:flex-col gap-3 sm:gap-3 sm:items-end justify-start sm:justify-center flex-shrink-0">
                <div class="flex gap-3">
                    <div class="min-w-[78px] rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-center shadow-sm dark:border-slate-700 dark:bg-slate-800/85">
                        <p class="text-xl font-bold text-slate-700 dark:text-slate-100 leading-none">{{ $totalVisits }}</p>
                        <p class="mt-1 text-[10px] uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Visits</p>
                    </div>
                    <div class="min-w-[78px] rounded-2xl border border-sky-200 bg-sky-50/90 px-4 py-3 text-center shadow-sm dark:border-sky-900/50 dark:bg-sky-950/25">
                        <p class="text-xl font-bold text-sky-700 dark:text-sky-200 leading-none">{{ $this->upcomingAppointments->count() }}</p>
                        <p class="mt-1 text-[10px] uppercase tracking-[0.22em] text-sky-600/80 dark:text-sky-300/70">Appts</p>
                    </div>
                    <div class="min-w-[78px] rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-center shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/25">
                        <p class="text-xl font-bold text-emerald-700 dark:text-emerald-200 leading-none">{{ $this->schoolPlacements->count() }}</p>
                        <p class="mt-1 text-[10px] uppercase tracking-[0.22em] text-emerald-600/80 dark:text-emerald-300/70">Schools</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action bar --}}
        <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800/80">
            @if($activeVisit)
                <x-filament::button size="sm" color="primary" icon="heroicon-m-plus-circle" class="!rounded-lg !px-4 !py-2.5 !shadow-sm"
                    wire:click="$dispatch('open-modal', { id: 'request-service-modal' })">
                    Request Service
                </x-filament::button>
            @endif
            <x-filament::button size="sm" color="success" icon="heroicon-m-calendar-days" class="!rounded-lg !px-4 !py-2.5 !shadow-sm"
                wire:click="$dispatch('open-modal', { id: 'appointment-modal' })">
                Book Appointment
            </x-filament::button>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-path" class="!rounded-lg !border !border-slate-200 !bg-white !px-4 !py-2.5 !text-slate-700 shadow-sm dark:!border-slate-700 dark:!bg-slate-800 dark:!text-slate-200" wire:click="refreshData">
                Refresh
            </x-filament::button>
        </div>
    </div>
</div>

{{-- ====================================================================== --}}
{{-- MAIN LAYOUT: SIDEBAR + CONTENT                                         --}}
{{-- ====================================================================== --}}
@php
$navGroups = [
    [
        'label' => 'Visit',
        'tabs'  => [
            ['id' => 'overview',      'label' => 'Overview',      'icon' => 'heroicon-m-home'],
            ['id' => 'current-visit', 'label' => 'Current Visit', 'icon' => 'heroicon-m-signal',
             'badge' => $activeVisit ? ucfirst(str_replace('_', ' ', $activeVisit->current_stage)) : null, 'badge_color' => 'success'],
            ['id' => 'services',      'label' => 'Services',      'icon' => 'heroicon-m-clipboard-document-check',
             'badge' => $activeVisit ? $activeVisit->serviceBookings->count() ?: null : null, 'badge_color' => 'info'],
            ['id' => 'history',       'label' => 'Visit History', 'icon' => 'heroicon-m-clock'],
        ],
    ],
    [
        'label' => 'Clinical',
        'tabs'  => [
            ['id' => 'clinical',     'label' => 'Clinical',     'icon' => 'heroicon-m-heart'],
            ['id' => 'assessments',  'label' => 'Assessments',  'icon' => 'heroicon-m-beaker'],
            ['id' => 'referrals',    'label' => 'Referrals',    'icon' => 'heroicon-m-arrow-path-rounded-square'],
        ],
    ],
    [
        'label' => 'Client Profile',
        'tabs'  => [
            ['id' => 'demographics', 'label' => 'Demographics',      'icon' => 'heroicon-m-identification'],
            ['id' => 'school',       'label' => 'School / Placement', 'icon' => 'heroicon-m-academic-cap',
             'badge' => $this->schoolPlacements->count() ?: null, 'badge_color' => 'teal'],
        ],
    ],
    [
        'label' => 'Coordination',
        'tabs'  => [
            ['id' => 'appointments', 'label' => 'Appointments', 'icon' => 'heroicon-m-calendar-days',
             'badge' => $this->upcomingAppointments->count() ?: null, 'badge_color' => 'warning'],
            ['id' => 'mdt',          'label' => 'MDT',          'icon' => 'heroicon-m-users'],
        ],
    ],
];
@endphp

{{-- ============================================================ --}}
{{-- MOBILE TAB BAR (visible on small screens only)               --}}
{{-- ============================================================ --}}
<div class="mt-6 md:hidden">
    <div class="rounded-2xl border border-slate-200 bg-white/95 shadow-[0_14px_36px_-28px_rgba(15,23,42,0.18)] backdrop-blur dark:border-slate-700 dark:bg-slate-900/92">
        <nav class="flex overflow-x-auto scrollbar-thin px-1.5 py-1.5" aria-label="Profile Hub Tabs">
            @foreach($navGroups as $group)
                @foreach($group['tabs'] as $tab)
                    <button
                        wire:click="setActiveTab('{{ $tab['id'] }}')"
                        class="flex-shrink-0 flex items-center gap-1.5 rounded-lg px-4 py-3 text-sm font-medium border-b-2 transition-colors
                            {{ $activeTab === $tab['id']
                                ? 'border-primary-500 bg-sky-50 text-sky-700 shadow-sm dark:bg-sky-900/20 dark:text-sky-300'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:bg-slate-50 hover:text-gray-700 dark:hover:bg-slate-800/70 dark:hover:text-gray-200' }}"
                    >
                        <x-filament::icon :icon="$tab['icon']" class="w-4 h-4 flex-shrink-0"/>
                        <span>{{ $tab['label'] }}</span>
                        @if(!empty($tab['badge']))
                            <x-filament::badge :color="$tab['badge_color'] ?? 'gray'" size="sm" class="ml-1">
                                {{ $tab['badge'] }}
                            </x-filament::badge>
                        @endif
                    </button>
                @endforeach
            @endforeach
        </nav>
    </div>
</div>

{{-- ============================================================ --}}
{{-- DESKTOP: SIDEBAR + CONTENT                                   --}}
{{-- ============================================================ --}}
<div class="mt-6 flex gap-6 items-start">

    {{-- Vertical Sidebar (desktop only) --}}
    <aside class="hidden md:block w-52 flex-shrink-0 sticky top-4 self-start">
        <div class="rounded-2xl border border-slate-200 bg-white/96 shadow-[0_14px_36px_-28px_rgba(15,23,42,0.18)] backdrop-blur dark:border-slate-700 dark:bg-slate-900/92 overflow-hidden">
            @foreach($navGroups as $group)
                <div class="px-3 pt-4 pb-2">
                    <p class="px-2.5 mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        {{ $group['label'] }}
                    </p>
                    @foreach($group['tabs'] as $tab)
                        <button
                            wire:click="setActiveTab('{{ $tab['id'] }}')"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors mb-1
                                {{ $activeTab === $tab['id']
                                    ? 'border border-sky-200 bg-sky-50 text-sky-700 shadow-sm dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300'
                                    : 'text-gray-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-slate-800/70 hover:text-gray-900 dark:hover:text-gray-100' }}"
                        >
                            <x-filament::icon
                                :icon="$tab['icon']"
                                class="w-4 h-4 flex-shrink-0 {{ $activeTab === $tab['id'] ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500' }}"
                            />
                            <span class="flex-1 text-left truncate">{{ $tab['label'] }}</span>
                            @if(!empty($tab['badge']))
                                <x-filament::badge :color="$tab['badge_color'] ?? 'gray'" size="sm">
                                    {{ $tab['badge'] }}
                                </x-filament::badge>
                            @endif
                        </button>
                    @endforeach
                </div>
                @if(!$loop->last)
                    <div class="mx-3 border-t border-gray-100 dark:border-gray-800 my-1"></div>
                @endif
            @endforeach
            <div class="h-2"></div>
        </div>
    </aside>

    {{-- Tab Content --}}
    <div class="flex-1 min-w-0 space-y-6">

{{-- ============================================================ --}}
{{-- OVERVIEW TAB                                                 --}}
{{-- ============================================================ --}}
@if($activeTab === 'overview')
    @php
        $latestIntake = $this->latestIntake;
        $dis = $this->disability;
    @endphp

    {{-- Allergy alert --}}
    @if($this->allergies->isNotEmpty())
        <div class="rounded-xl border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20 p-5 flex items-start gap-4">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5"/>
            <div>
                <p class="font-semibold text-red-800 dark:text-red-200 text-sm">Allergy Alert</p>
                <p class="text-sm text-red-700 dark:text-red-300 mt-0.5">
                    {{ $this->allergies->pluck('allergen')->filter()->implode(' • ') ?: 'See Clinical tab for details' }}
                </p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- LEFT — identity + intake summary + visit --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- ── Key Demographics ── --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] transition-all dark:border-slate-700 dark:bg-slate-900/96">
                <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 dark:border-slate-800 dark:bg-slate-900 flex items-center gap-2">
                    <x-filament::icon icon="heroicon-m-identification" class="w-4 h-4 text-sky-500"/>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Demographics</span>
                </div>
                <div class="p-6 grid grid-cols-2 sm:grid-cols-3 gap-x-8 gap-y-5">
                    @php
                        $demo = $this->demographics;
                        $demoFields = array_filter([
                            'Date of Birth' => $demo['basic']['Date of Birth'] ?? null,
                            'National ID'   => $demo['basic']['National ID'] ?? null,
                            'County'        => $demo['address']['County'] ?? null,
                            'Sub-County'    => $demo['address']['Sub-County'] ?? null,
                            'Guardian'      => $demo['contact']['Guardian Name'] ?? null,
                            'Guardian Phone'=> $demo['contact']['Guardian Phone'] ?? null,
                            'SHA No.'       => $demo['basic']['SHA No.'] ?? null,
                            'NCPWD No.'     => $demo['basic']['NCPWD No.'] ?? null,
                        ], fn($v) => $v && $v !== 'N/A');
                    @endphp
                    @foreach($demoFields as $label => $value)
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ $label }}</p>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mt-0.5">{{ $value }}</p>
                        </div>
                    @endforeach
                    <div class="col-span-full">
                        <button wire:click="setActiveTab('demographics')" class="text-xs text-primary-600 dark:text-primary-400 hover:underline mt-1">
                            Full demographics & socio-economic data →
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── Latest Intake Assessment Summary ── --}}
            @if($latestIntake)
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96">
                    <div class="bg-slate-50 px-5 py-4 border-b border-slate-200 dark:border-slate-800 dark:bg-slate-900 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-clipboard-document-list" class="w-4 h-4 text-sky-500"/>
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Intake Assessment</span>
                            @if($latestIntake->is_finalized)
                                <x-filament::badge color="success" size="sm">Finalized</x-filament::badge>
                            @else
                                <x-filament::badge color="warning" size="sm">In Progress</x-filament::badge>
                            @endif
                        </div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $latestIntake->created_at?->format('M d, Y') }}
                            @if($latestIntake->assessedBy) · {{ $latestIntake->assessedBy->name }} @endif
                        </span>
                    </div>
                    <div class="p-6 space-y-4">
                        @if($latestIntake->reason_for_visit)
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500">Reason for Visit</p>
                                <p class="mt-1 text-sm leading-6 text-slate-800 dark:text-slate-200">{{ $latestIntake->reason_for_visit }}</p>
                            </div>
                        @endif
                        @if($latestIntake->current_concerns)
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500">Current Concerns</p>
                                <p class="mt-1 text-sm leading-6 text-slate-800 dark:text-slate-200">{{ $latestIntake->current_concerns }}</p>
                            </div>
                        @endif
                        @if($latestIntake->recommendations)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-800/70">
                                <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500">Recommendations</p>
                                <p class="mt-1.5 text-sm leading-6 text-slate-800 dark:text-slate-200">{{ $latestIntake->recommendations }}</p>
                            </div>
                        @endif
                        @if($latestIntake->priority_level)
                            <div class="flex items-center gap-2.5 pt-1">
                                <p class="text-xs text-slate-500">Priority:</p>
                                <x-filament::badge :color="match((int)$latestIntake->priority_level) { 1 => 'danger', 2 => 'warning', 3 => 'info', default => 'gray' }" size="sm">
                                    Level {{ $latestIntake->priority_level }}
                                </x-filament::badge>
                            </div>
                        @endif
                        @php
                            $servicesRequired = is_array($latestIntake->services_required) ? $latestIntake->services_required : [];
                            $requiredServiceLabels = collect();

                            if (!empty($servicesRequired['primary_service_id'])) {
                                $primaryService = \App\Models\Service::find($servicesRequired['primary_service_id']);
                                if ($primaryService?->name) {
                                    $requiredServiceLabels->push($primaryService->name);
                                }
                            }

                            if (!empty($servicesRequired['service_ids']) && is_array($servicesRequired['service_ids'])) {
                                $serviceNames = \App\Models\Service::whereIn('id', $servicesRequired['service_ids'])
                                    ->pluck('name')
                                    ->all();
                                $requiredServiceLabels = $requiredServiceLabels->merge($serviceNames);
                            }

                            if (!empty($servicesRequired['service_categories']) && is_array($servicesRequired['service_categories'])) {
                                $requiredServiceLabels = $requiredServiceLabels->merge(
                                    collect($servicesRequired['service_categories'])
                                        ->filter(fn ($value) => is_string($value) && $value !== '')
                                        ->map(fn ($value) => ucwords(str_replace('_', ' ', $value)))
                                );
                            }

                            $requiredServiceLabels = $requiredServiceLabels
                                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                                ->unique()
                                ->values();
                        @endphp
                        @if($requiredServiceLabels->isNotEmpty())
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500">Services Required</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($requiredServiceLabels as $serviceLabel)
                                        <x-filament::badge color="info" size="sm">{{ $serviceLabel }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div class="pt-1">
                            <button wire:click="setActiveTab('history')" class="text-xs text-sky-700 dark:text-sky-300 hover:underline">
                                View all intake assessments in visit history →
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Active Visit ── --}}
            @if($activeVisit)
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96">
                    <div class="bg-slate-50 px-5 py-4 border-b border-slate-200 dark:border-slate-800 dark:bg-slate-900 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Active Visit</span>
                            <x-filament::badge color="success" size="sm">{{ ucfirst(str_replace('_', ' ', $activeVisit->current_stage)) }}</x-filament::badge>
                        </div>
                        <span class="font-mono text-xs text-gray-400">{{ $activeVisit->visit_number }}</span>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-5 text-sm">
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Check-in</p>
                                <p class="font-medium text-gray-800 dark:text-gray-200 mt-0.5">{{ $activeVisit->check_in_time?->format('M d, H:i') }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Visit Type</p>
                                <p class="font-medium text-gray-800 dark:text-gray-200 mt-0.5">{{ ucfirst(str_replace('_', ' ', $activeVisit->visit_type ?? 'walk_in')) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Services</p>
                                <p class="font-bold text-gray-900 dark:text-white mt-0.5">{{ $activeVisit->serviceBookings->count() }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Emergency</p>
                                @if($activeVisit->is_emergency)
                                    <x-filament::badge color="danger" size="sm" class="mt-0.5">Yes</x-filament::badge>
                                @else
                                    <p class="text-sm text-gray-400 mt-0.5">No</p>
                                @endif
                            </div>
                        </div>
                        @if($activeVisit->serviceBookings->isNotEmpty())
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach($activeVisit->serviceBookings as $bk)
                                    <x-filament::badge color="gray" size="sm">{{ $bk->service?->name ?? 'Service' }}</x-filament::badge>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30 p-6 text-center">
                    <x-filament::icon icon="heroicon-m-calendar-x-mark" class="w-9 h-9 text-gray-300 mx-auto mb-2"/>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No active visit today</p>
                </div>
            @endif

        </div>

        {{-- RIGHT — insurance + appointments + disability + flags --}}
        <div class="space-y-6">

            {{-- Insurance --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96">
                    <div class="bg-slate-50 px-4 py-4 border-b border-slate-200 dark:border-slate-800 dark:bg-slate-900 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-shield-check" class="w-4 h-4 text-emerald-500"/>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Insurance</span>
                </div>
                <div class="p-5 space-y-3">
                    @forelse($this->activeInsurances as $ins)
                        <div class="flex items-start justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-800/70">
                            <div>
                                <p class="text-sm font-semibold text-green-900 dark:text-green-100">{{ $ins->insuranceProvider->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-green-600 dark:text-green-400 mt-0.5 font-mono">{{ $ins->membership_number }}</p>
                                @if($ins->valid_to)
                                    <p class="text-xs text-green-500 mt-0.5">Exp: {{ $ins->valid_to->format('M Y') }}</p>
                                @endif
                            </div>
                            <x-filament::badge color="success" size="sm">Active</x-filament::badge>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-3">No active insurance</p>
                    @endforelse
                </div>
            </div>

            {{-- Next Appointments --}}
            @if($this->upcomingAppointments->isNotEmpty())
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96">
                    <div class="bg-slate-50 px-4 py-4 border-b border-slate-200 dark:border-slate-800 dark:bg-slate-900 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-calendar-days" class="w-4 h-4 text-sky-500"/>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Next Appointments</span>
                    </div>
                    <div class="p-5 space-y-3">
                        @foreach($this->upcomingAppointments->take(3) as $apt)
                            <div class="flex items-start gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-800/70">
                                <div class="flex h-11 w-11 flex-shrink-0 flex-col items-center justify-center rounded-xl bg-sky-100 text-center dark:bg-sky-900/30">
                                    <span class="text-xs font-bold leading-none text-sky-700 dark:text-sky-300">{{ $apt->appointment_date->format('d') }}</span>
                                    <span class="mt-0.5 text-[9px] uppercase leading-none text-sky-600 dark:text-sky-400">{{ $apt->appointment_date->format('M') }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $apt->service?->name ?? 'Appointment' }}</p>
                                    <p class="text-xs text-gray-400">{{ $apt->appointment_date->diffForHumans() }}</p>
                                </div>
                            </div>
                        @endforeach
                        @if($this->upcomingAppointments->count() > 3)
                            <button wire:click="setActiveTab('appointments')" class="text-xs text-primary-600 dark:text-primary-400 hover:underline w-full text-center pt-1">
                                +{{ $this->upcomingAppointments->count() - 3 }} more →
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Disability Profile --}}
            @if($dis)
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96">
                    <div class="bg-slate-50 px-4 py-3.5 border-b border-slate-200 dark:border-slate-800 dark:bg-slate-900 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-user-circle" class="w-4 h-4 text-slate-500"/>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Disability Profile</span>
                    </div>
                    <div class="p-5 space-y-4">
                        @if($dis->disability_categories)
                            <div class="flex flex-wrap gap-2">
                                @foreach((array) $dis->disability_categories as $cat)
                                    <x-filament::badge color="warning" size="sm">{{ ucwords(str_replace('_', ' ', $cat)) }}</x-filament::badge>
                                @endforeach
                            </div>
                        @endif
                        @if($dis->level_of_functioning)
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-amber-500">Functioning</p>
                                <p class="text-xs text-amber-900 dark:text-amber-100 mt-0.5">{{ $dis->level_of_functioning }}</p>
                            </div>
                        @endif
                        @if($dis->assistive_technology)
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-amber-500">Assistive Tech</p>
                                <div class="flex flex-wrap gap-2 mt-1">
                                    @foreach((array)$dis->assistive_technology as $at)
                                        <x-filament::badge color="info" size="sm">{{ ucwords(str_replace('_', ' ', $at)) }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <button wire:click="setActiveTab('clinical')" class="text-xs text-amber-700 dark:text-amber-300 hover:underline">Full clinical profile →</button>
                    </div>
                </div>
            @endif

        </div>
    </div>
@endif

{{-- ============================================================ --}}
{{-- CURRENT VISIT TAB                                            --}}
{{-- ============================================================ --}}
@if($activeTab === 'current-visit')
    @if($activeVisit)
        <div class="space-y-5">
            {{-- Visit Header Card --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96">
                <div class="h-0.5 bg-gradient-to-r from-emerald-400 to-emerald-600"></div>
                <div class="bg-slate-50 px-5 py-4 border-b border-slate-200 dark:border-slate-800 dark:bg-slate-900 flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center flex-shrink-0">
                        <x-filament::icon icon="heroicon-m-signal" class="w-3.5 h-3.5 text-emerald-500"/>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex-1">Current Visit</h3>
                    <x-filament::badge color="warning" size="sm">{{ ucfirst(str_replace('_', ' ', $activeVisit->current_stage)) }}</x-filament::badge>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Visit Number</p>
                            <p class="font-mono font-bold text-primary-600 dark:text-primary-400 mt-1 text-base">{{ $activeVisit->visit_number }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Visit Type</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 mt-1">{{ ucfirst(str_replace('_', ' ', $activeVisit->visit_type ?? 'walk_in')) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Check-in</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 mt-1">{{ $activeVisit->check_in_time?->format('M d, Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Emergency</p>
                            <div class="mt-1">
                                @if($activeVisit->is_emergency)
                                    <x-filament::badge color="danger" icon="heroicon-m-exclamation-triangle" size="sm">Emergency</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray" size="sm">Routine</x-filament::badge>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Triage Card --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96">
                    <div class="h-0.5 bg-gradient-to-r from-rose-400 to-rose-600"></div>
                    <div class="bg-slate-50 px-5 py-4 border-b border-slate-200 dark:border-slate-800 dark:bg-slate-900 flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-900/20 flex items-center justify-center flex-shrink-0">
                            <x-filament::icon icon="heroicon-m-heart" class="w-3.5 h-3.5 text-rose-500"/>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Triage Assessment</h3>
                    </div>
                    <div class="p-6">
                    @if($this->currentVisitTriage)
                        @php $triage = $this->currentVisitTriage; @endphp
                        @if(!empty($triage['vital_signs']))
                            <div class="grid grid-cols-2 gap-3 mb-5">
                                @foreach($triage['vital_signs'] as $label => $value)
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-800/70">
                                        <p class="text-[10px] text-rose-400 uppercase tracking-wide">{{ $label }}</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ $value }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="space-y-2 border-t border-gray-100 dark:border-gray-800 pt-4">
                            @foreach($triage['assessment'] as $label => $value)
                                <div class="flex justify-between text-sm py-1.5 border-b border-gray-50 dark:border-gray-800/50 last:border-0">
                                    <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if($triage['notes'])
                            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1">Triage Notes</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 italic">{{ $triage['notes'] }}</p>
                            </div>
                        @endif
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-between text-xs text-gray-400">
                            <span>By: {{ $triage['nurse'] }}</span>
                            <span>{{ $triage['triaged_at'] }}</span>
                        </div>
                    @else
                        <div class="flex flex-col items-center py-8 text-center">
                            <div class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                                <x-filament::icon icon="heroicon-m-clock" class="w-5 h-5 text-gray-400"/>
                            </div>
                            <p class="text-sm font-medium text-gray-500">Awaiting triage</p>
                        </div>
                    @endif
                    </div>
                </div>

                {{-- Intake Card --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96">
                    <div class="h-0.5 bg-gradient-to-r from-blue-400 to-blue-600"></div>
                    <div class="bg-slate-50 px-5 py-4 border-b border-slate-200 dark:border-slate-800 dark:bg-slate-900 flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center flex-shrink-0">
                            <x-filament::icon icon="heroicon-m-clipboard-document" class="w-3.5 h-3.5 text-blue-500"/>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Intake Assessment</h3>
                    </div>
                    <div class="p-6">
                    @if($this->currentVisitIntake)
                        @php $intake = $this->currentVisitIntake; @endphp
                        <div class="space-y-4">
                            @if($intake['presenting_problem'])
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1">Presenting Problem</p>
                                    <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm leading-6 text-gray-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-gray-300">
                                        {{ $intake['presenting_problem'] }}
                                    </p>
                                </div>
                            @endif
                            @if($intake['history_present_illness'])
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1">History of Present Illness</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 italic">{{ $intake['history_present_illness'] }}</p>
                                </div>
                            @endif
                            <div class="grid grid-cols-2 gap-4 pt-1">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-800/70">
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Risk Level</p>
                                    <x-filament::badge :color="match($intake['risk_level']) { 'High' => 'danger', 'Medium' => 'warning', default => 'success' }" size="sm" class="mt-1">
                                        {{ $intake['risk_level'] }}
                                    </x-filament::badge>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-800/70">
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Priority</p>
                                    <x-filament::badge :color="match($intake['priority']) { 'Urgent', '1' => 'danger', 'High', '2' => 'warning', default => 'success' }" size="sm" class="mt-1">
                                        {{ $intake['priority'] }}
                                    </x-filament::badge>
                                </div>
                            </div>
                            @if($intake['recommendations'])
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1">Recommendations</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $intake['recommendations'] }}</p>
                                </div>
                            @endif
                            @if($intake['special_instructions'])
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-800/70">
                                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">Special Instructions</p>
                                    <p class="text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $intake['special_instructions'] }}</p>
                                </div>
                            @endif
                        </div>
                        <div class="mt-5 pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-between text-xs text-gray-400">
                            <span>By: {{ $intake['officer'] }}</span>
                            <span>{{ $intake['assessed_at'] }}</span>
                        </div>
                    @else
                        <div class="flex flex-col items-center py-8 text-center">
                            <div class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                                <x-filament::icon icon="heroicon-m-clock" class="w-5 h-5 text-gray-400"/>
                            </div>
                            <p class="text-sm font-medium text-gray-500">Awaiting intake assessment</p>
                        </div>
                    @endif
                    </div>
                </div>
            </div>

            {{-- Billing Summary --}}
            @if($activeVisit->invoices?->isNotEmpty())
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96" x-data="{ open: false }">
                    <div class="h-0.5 bg-gradient-to-r from-teal-400 to-teal-600"></div>
                    <button @click="open = !open" class="w-full bg-slate-50 px-5 py-4 border-b border-slate-200 dark:border-slate-800 dark:bg-slate-900 flex items-center gap-3 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors">
                        <div class="w-7 h-7 rounded-lg bg-teal-50 dark:bg-teal-900/20 flex items-center justify-center flex-shrink-0">
                            <x-filament::icon icon="heroicon-m-banknotes" class="w-3.5 h-3.5 text-teal-500"/>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex-1 text-left">Billing Summary</h3>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4 text-gray-400 transition-transform duration-200" ::class="open ? 'rotate-180' : ''"/>
                    </button>
                    <div x-show="open" x-collapse>
                        <div class="p-6 space-y-3">
                            @foreach($activeVisit->invoices as $invoice)
                                <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-800/70">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $invoice->invoice_number }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $invoice->created_at->format('M d, Y') }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">KES {{ number_format($invoice->total_amount, 2) }}</p>
                                        <x-filament::badge :color="match($invoice->status ?? 'pending') { 'paid' => 'success', 'partial' => 'warning', default => 'gray' }" size="sm" class="mt-0.5">
                                            {{ ucfirst($invoice->status ?? 'pending') }}
                                        </x-filament::badge>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30 p-14 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
                <x-filament::icon icon="heroicon-m-x-circle" class="w-6 h-6 text-gray-400"/>
            </div>
            <h3 class="text-base font-semibold text-gray-500 dark:text-gray-400">No Active Visit</h3>
            <p class="text-sm text-gray-400 mt-1">Client does not have an active visit at the moment.</p>
        </div>
    @endif
@endif

{{-- ============================================================ --}}
{{-- ASSESSMENTS TAB                                              --}}
{{-- ============================================================ --}}
@if($activeTab === 'assessments')
    <div class="overflow-hidden rounded-[1.4rem] border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96">
        <div class="h-0.5 bg-sky-500/70"></div>
        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-cyan-50 dark:bg-cyan-900/20 flex items-center justify-center flex-shrink-0">
                <x-filament::icon icon="heroicon-m-beaker" class="w-3.5 h-3.5 text-cyan-500"/>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Assessment Tools & Service Departments</h3>
                <p class="text-xs text-gray-400 mt-0.5">Links to specialist assessment departments.</p>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($this->assessmentLinks as $link)
                    <div class="group flex flex-col gap-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 hover:border-sky-300 dark:hover:border-sky-700 hover:shadow-md transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-{{ $link['color'] }}-50 dark:bg-{{ $link['color'] }}-900/20 flex items-center justify-center flex-shrink-0">
                                <x-filament::icon :icon="$link['icon']" class="w-5 h-5 text-{{ $link['color'] }}-600 dark:text-{{ $link['color'] }}-400"/>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white leading-tight">{{ $link['label'] }}</p>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">{{ $link['description'] }}</p>
                        @if($link['url'])
                            <a href="{{ $link['url'] }}" class="mt-auto text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">Open Assessment →</a>
                        @else
                            <span class="mt-auto text-xs text-gray-300 dark:text-gray-600">Link not configured</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Assessment History (collapsible) --}}
    <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden" x-data="{ open: false }">
        <div class="h-0.5 bg-gradient-to-r from-cyan-400 to-cyan-600"></div>
        <button @click="open = !open" class="w-full px-5 py-3.5 flex items-center gap-3 hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
            <div class="w-7 h-7 rounded-lg bg-cyan-50 dark:bg-cyan-900/20 flex items-center justify-center flex-shrink-0">
                <x-filament::icon icon="heroicon-m-document-text" class="w-3.5 h-3.5 text-cyan-500"/>
            </div>
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex-1 text-left">Assessment History</h3>
            <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4 text-gray-400 transition-transform duration-200" ::class="open ? 'rotate-180' : ''"/>
        </button>
        <div x-show="open" x-collapse>
            <div class="px-5 pb-5 pt-2">
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-6">Assessment records from each department will appear here.</p>
            </div>
        </div>
    </div>
@endif

{{-- ============================================================ --}}
{{-- SERVICES TAB                                                 --}}
{{-- ============================================================ --}}
@if($activeTab === 'services')
    <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
        <div class="h-0.5 bg-gradient-to-r from-emerald-400 to-emerald-600"></div>
        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center flex-shrink-0">
                <x-filament::icon icon="heroicon-m-clipboard-document-check" class="w-3.5 h-3.5 text-emerald-500"/>
            </div>
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Services for Current Visit</h3>
        </div>
        <div class="p-6">
        @if($this->currentServices->isNotEmpty())
            <div class="space-y-4">
                @foreach($this->currentServices as $svc)
                    <div class="flex items-start gap-4 rounded-2xl border border-emerald-100/80 bg-gradient-to-r from-white via-emerald-50/50 to-teal-50/40 p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md dark:border-emerald-900/30 dark:bg-gradient-to-r dark:from-slate-900 dark:via-emerald-950/20 dark:to-teal-950/10">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-4">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $svc['service_name'] }}</p>
                                <x-filament::badge :color="match($svc['priority']) { 'urgent' => 'danger', 'high' => 'warning', default => 'gray' }" size="sm">
                                    {{ ucfirst($svc['priority']) }}
                                </x-filament::badge>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Department</p>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-0.5">{{ $svc['department'] }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Queue Status</p>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-0.5">{{ $svc['queue_status'] }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Queue #</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ $svc['queue_number'] }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Status</p>
                                    <x-filament::badge :color="match($svc['status']) { 'Completed' => 'success', 'In Progress' => 'warning', 'Waiting' => 'info', default => 'gray' }" size="sm" class="mt-0.5">
                                        {{ $svc['status'] }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center py-10 text-center">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center mb-3">
                    <x-filament::icon icon="heroicon-m-clipboard-document-check" class="w-5 h-5 text-emerald-400"/>
                </div>
                <p class="text-sm font-medium text-gray-500">
                    {{ $activeVisit ? 'No services booked yet' : 'No active visit' }}
                </p>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ $activeVisit ? 'Use the button below to request a service.' : 'Select a visit first.' }}
                </p>
                @if($activeVisit)
                    <x-filament::button class="mt-4 !rounded-xl !px-4 !py-2.5 !shadow-md ring-1 ring-sky-300/40 dark:ring-sky-500/20" size="sm" color="primary" icon="heroicon-m-plus-circle"
                        wire:click="$dispatch('open-modal', { id: 'request-service-modal' })">
                        Request a Service
                    </x-filament::button>
                @endif
            </div>
        @endif
        </div>
    </div>
@endif

{{-- ============================================================ --}}
{{-- APPOINTMENTS TAB                                             --}}
{{-- ============================================================ --}}
@if($activeTab === 'appointments')
    <div class="flex justify-end mb-1">
        <x-filament::button size="sm" color="success" class="!rounded-xl !px-4 !py-2.5 !shadow-md ring-1 ring-emerald-300/40 dark:ring-emerald-500/20" icon="heroicon-m-calendar-days"
            wire:click="$dispatch('open-modal', { id: 'appointment-modal' })">
            Book Appointment
        </x-filament::button>
    </div>

    <div class="overflow-hidden rounded-[1.4rem] border border-slate-200 bg-white shadow-[0_14px_34px_-28px_rgba(15,23,42,0.16)] dark:border-slate-700 dark:bg-slate-900/96">
        <div class="h-0.5 bg-sky-500/70"></div>
        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center flex-shrink-0">
                <x-filament::icon icon="heroicon-m-calendar-days" class="w-3.5 h-3.5 text-sky-500"/>
            </div>
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Upcoming Appointments</h3>
        </div>
        <div class="p-5">
        @forelse($this->upcomingAppointments as $apt)
            <div class="flex items-start gap-4 p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/45 mb-3 last:mb-0">
                <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-sky-100 dark:bg-sky-900/30 flex flex-col items-center justify-center text-center">
                    <span class="text-sm font-bold text-sky-700 dark:text-sky-300 leading-none">{{ $apt->appointment_date->format('d') }}</span>
                    <span class="text-[10px] text-sky-600 uppercase font-semibold leading-none mt-0.5">{{ $apt->appointment_date->format('M') }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2">
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $apt->service?->name ?? 'Appointment' }}</p>
                        <x-filament::badge :color="$apt->status === 'confirmed' ? 'success' : 'info'" size="sm">
                            {{ ucfirst($apt->status) }}
                        </x-filament::badge>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Time</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mt-0.5">{{ $apt->appointment_time ? \Carbon\Carbon::parse($apt->appointment_time)->format('H:i') : 'TBD' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Department</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mt-0.5">{{ $apt->department?->name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Provider</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mt-0.5">{{ $apt->provider?->name ?? 'Not assigned' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">In</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mt-0.5">{{ $apt->appointment_date->diffForHumans() }}</p>
                        </div>
                    </div>
                    @if($apt->notes)
                        <p class="text-xs text-gray-500 mt-2 italic">{{ $apt->notes }}</p>
                    @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center py-10 text-center">
                <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center mb-3">
                    <x-filament::icon icon="heroicon-m-calendar" class="w-5 h-5 text-sky-400"/>
                </div>
                <p class="text-sm font-medium text-gray-500">No upcoming appointments</p>
            </div>
        @endforelse
        </div>
    </div>

    @if($this->pastAppointments->isNotEmpty())
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden" x-data="{ open: false }">
            <div class="h-0.5 bg-gradient-to-r from-gray-300 to-gray-400"></div>
            <button @click="open = !open" class="w-full px-5 py-3.5 flex items-center gap-3 hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                <div class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center flex-shrink-0">
                    <x-filament::icon icon="heroicon-m-clock" class="w-3.5 h-3.5 text-gray-500"/>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex-1 text-left">Past Appointments</h3>
                <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4 text-gray-400 transition-transform duration-200" ::class="open ? 'rotate-180' : ''"/>
            </button>
            <div x-show="open" x-collapse>
                <div class="px-5 pb-5">
                    <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="py-2.5 px-4 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-400">Date</th>
                                    <th class="py-2.5 px-4 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-400">Service</th>
                                    <th class="py-2.5 px-4 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-400">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                                @foreach($this->pastAppointments as $apt)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="py-2.5 px-4 text-gray-600 dark:text-gray-400">{{ $apt->appointment_date->format('M d, Y') }}</td>
                                        <td class="py-2.5 px-4 text-gray-900 dark:text-white font-medium">{{ $apt->service?->name ?? 'N/A' }}</td>
                                        <td class="py-2.5 px-4">
                                            <x-filament::badge :color="match($apt->status) { 'attended', 'completed' => 'success', 'missed', 'no_show' => 'danger', 'cancelled' => 'gray', default => 'warning' }" size="sm">
                                                {{ ucfirst($apt->status) }}
                                            </x-filament::badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif

{{-- ============================================================ --}}
{{-- REFERRALS TAB                                                --}}
{{-- ============================================================ --}}
@if($activeTab === 'referrals')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Internal Referrals --}}
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-violet-400 to-violet-600"></div>
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center flex-shrink-0">
                    <x-filament::icon icon="heroicon-m-arrow-path-rounded-square" class="w-3.5 h-3.5 text-violet-500"/>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Internal Referrals</h3>
            </div>
            <div class="p-5">
            @forelse($this->internalReferrals as $ref)
                <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/20 mb-3 last:mb-0 hover:border-gray-200 transition-colors">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ $ref->service?->name ?? 'N/A' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $ref->created_at->format('M d, Y') }}</p>
                        </div>
                        <x-filament::badge :color="match($ref->status) { 'pending' => 'warning', 'completed' => 'success', 'cancelled' => 'danger', default => 'gray' }" size="sm">
                            {{ ucfirst($ref->status) }}
                        </x-filament::badge>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-lg px-2 py-1">{{ $ref->fromDepartment?->name ?? 'N/A' }}</span>
                        <x-filament::icon icon="heroicon-m-arrow-right" class="w-3 h-3 text-gray-400"/>
                        <span class="bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-400 rounded-lg px-2 py-1 font-medium">{{ $ref->toDepartment?->name ?? 'N/A' }}</span>
                    </div>
                    @if($ref->reason)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 italic line-clamp-2">{{ $ref->reason }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-2">By: {{ $ref->referringProvider?->name ?? 'N/A' }}</p>
                </div>
            @empty
                <div class="flex flex-col items-center py-10 text-center">
                    <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center mb-3">
                        <x-filament::icon icon="heroicon-m-arrow-path-rounded-square" class="w-5 h-5 text-violet-300"/>
                    </div>
                    <p class="text-sm font-medium text-gray-500">No internal referrals</p>
                </div>
            @endforelse
            </div>
        </div>

        {{-- External Referrals --}}
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-indigo-400 to-indigo-600"></div>
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center flex-shrink-0">
                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="w-3.5 h-3.5 text-indigo-500"/>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">External Referrals</h3>
            </div>
            <div class="p-5">
            @forelse($this->externalReferrals as $ref)
                <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/20 mb-3 last:mb-0">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ $ref->referred_to_facility }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $ref->created_at->format('M d, Y') }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <x-filament::badge :color="match($ref->status ?? 'pending') { 'completed' => 'success', 'cancelled' => 'danger', default => 'warning' }" size="sm">
                                {{ ucfirst($ref->status ?? 'Pending') }}
                            </x-filament::badge>
                            @if($ref->urgency_level ?? null)
                                <x-filament::badge :color="match($ref->urgency_level) { 'emergency' => 'danger', 'urgent' => 'warning', default => 'gray' }" size="sm">
                                    {{ ucfirst($ref->urgency_level) }}
                                </x-filament::badge>
                            @endif
                        </div>
                    </div>
                    @if($ref->specialty ?? null)
                        <p class="text-xs text-gray-500">Specialty: {{ $ref->specialty }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-1">By: {{ $ref->referringProvider?->name ?? 'N/A' }}</p>
                </div>
            @empty
                <div class="flex flex-col items-center py-10 text-center">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center mb-3">
                        <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="w-5 h-5 text-indigo-300"/>
                    </div>
                    <p class="text-sm font-medium text-gray-500">No external referrals</p>
                </div>
            @endforelse
            </div>
        </div>
    </div>
@endif

{{-- ============================================================ --}}
{{-- CLINICAL TAB                                                 --}}
{{-- ============================================================ --}}
@if($activeTab === 'clinical')
    <div class="space-y-5">

        {{-- Medical History --}}
        @php $mh = $this->medicalHistory; @endphp
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-rose-400 to-rose-600"></div>
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-900/20 flex items-center justify-center flex-shrink-0">
                    <x-filament::icon icon="heroicon-m-document-text" class="w-3.5 h-3.5 text-rose-500"/>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Medical History</h3>
            </div>
            <div class="p-5">
            @if($mh)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    @if($mh->medical_conditions)
                        <div class="p-3.5 rounded-xl bg-rose-50/40 dark:bg-rose-900/10 border border-rose-100 dark:border-rose-900/30">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-rose-400 mb-1.5">Medical Conditions</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $mh->medical_conditions }}</p>
                        </div>
                    @endif
                    @if($mh->current_medications)
                        <div class="p-3.5 rounded-xl bg-blue-50/40 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-blue-400 mb-1.5">Current Medications</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $mh->current_medications }}</p>
                        </div>
                    @endif
                    @if($mh->surgical_history)
                        <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1.5">Surgical History</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $mh->surgical_history }}</p>
                        </div>
                    @endif
                    @if($mh->family_medical_history)
                        <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1.5">Family Medical History</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $mh->family_medical_history }}</p>
                        </div>
                    @endif
                    @if($mh->developmental_concerns_notes)
                        <div class="md:col-span-2 p-3.5 rounded-xl bg-amber-50/40 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-amber-400 mb-1.5">Developmental Concerns</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $mh->developmental_concerns_notes }}</p>
                        </div>
                    @endif
                    @if($mh->immunization_status)
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1">Immunization Status</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $mh->immunization_status }}</p>
                        </div>
                    @endif
                    @if($mh->assistive_devices_notes)
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1">Assistive Devices</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $mh->assistive_devices_notes }}</p>
                        </div>
                    @endif
                </div>
            @else
                <div class="flex flex-col items-center py-10 text-center">
                    <div class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                        <x-filament::icon icon="heroicon-m-document-text" class="w-5 h-5 text-gray-400"/>
                    </div>
                    <p class="text-sm font-medium text-gray-500">No medical history recorded</p>
                </div>
            @endif
            </div>
        </div>

        {{-- Disability --}}
        @php $dis = $this->disability; @endphp
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-amber-400 to-amber-600"></div>
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center flex-shrink-0">
                    <x-filament::icon icon="heroicon-m-user-circle" class="w-3.5 h-3.5 text-amber-500"/>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Disability Profile</h3>
            </div>
            <div class="p-5">
            @if($dis)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    @if($dis->disability_categories)
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2">Disability Categories</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach((array) $dis->disability_categories as $cat)
                                    <x-filament::badge color="warning">{{ ucwords(str_replace('_', ' ', $cat)) }}</x-filament::badge>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($dis->onset)
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1">Onset</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $dis->onset }}</p>
                        </div>
                    @endif
                    @if($dis->level_of_functioning)
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1">Level of Functioning</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $dis->level_of_functioning }}</p>
                        </div>
                    @endif
                    @if($dis->assistive_technology)
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2">Assistive Technology</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach((array) $dis->assistive_technology as $tech)
                                    <x-filament::badge color="info" size="sm">{{ ucwords(str_replace('_', ' ', $tech)) }}</x-filament::badge>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($dis->disability_notes)
                        <div class="md:col-span-2">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1">Notes</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $dis->disability_notes }}</p>
                        </div>
                    @endif
                </div>
            @else
                <div class="flex flex-col items-center py-10 text-center">
                    <div class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                        <x-filament::icon icon="heroicon-m-user-circle" class="w-5 h-5 text-gray-400"/>
                    </div>
                    <p class="text-sm font-medium text-gray-500">No disability record</p>
                </div>
            @endif
            </div>
        </div>

        {{-- Allergies --}}
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-red-400 to-red-600"></div>
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center flex-shrink-0">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-3.5 h-3.5 text-red-500"/>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Allergies</h3>
            </div>
            <div class="p-5">
            @forelse($this->allergies as $allergy)
                <div class="flex items-start gap-4 p-4 rounded-xl border-l-4 border-red-400 bg-red-50 dark:bg-red-900/20 mb-3 last:mb-0">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1.5">
                            <p class="font-semibold text-red-900 dark:text-red-100">{{ $allergy->allergen }}</p>
                            <x-filament::badge color="danger" size="sm">{{ ucfirst($allergy->severity ?? 'unknown') }}</x-filament::badge>
                            <x-filament::badge color="warning" size="sm">{{ ucfirst($allergy->allergy_type ?? 'unknown') }}</x-filament::badge>
                        </div>
                        @if($allergy->reaction)
                            <p class="text-sm text-red-700 dark:text-red-300">Reaction: {{ $allergy->reaction }}</p>
                        @endif
                        @if($allergy->notes)
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1 italic">{{ $allergy->notes }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center py-10 text-center">
                    <div class="w-9 h-9 rounded-xl bg-green-50 dark:bg-green-900/20 flex items-center justify-center mb-3">
                        <x-filament::icon icon="heroicon-m-check-circle" class="w-5 h-5 text-green-400"/>
                    </div>
                    <p class="text-sm font-medium text-green-600 dark:text-green-400">No known allergies</p>
                </div>
            @endforelse
            </div>
        </div>
    </div>
@endif

{{-- ============================================================ --}}
{{-- DEMOGRAPHICS TAB                                             --}}
{{-- ============================================================ --}}
@if($activeTab === 'demographics')
    @php
        $demo  = $this->demographics;
        $socio = $this->socioDemographic;
        $edu   = $this->education;
        $li    = $this->latestIntake;

        // Helper: render a simple DL list
        function demoRow(string $label, $value, string $class = ''): string {
            if (!$value || $value === 'N/A') return '';
            $v = ucfirst(str_replace('_', ' ', $value));
            return "<div class=\"flex justify-between py-2 border-b last:border-0 border-gray-50 dark:border-gray-800 {$class}\">
                <dt class=\"text-sm text-gray-500 dark:text-gray-400\">{$label}</dt>
                <dd class=\"text-sm font-semibold text-gray-900 dark:text-white text-right\">{$v}</dd>
            </div>";
        }
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Personal Information --}}
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-indigo-400 to-indigo-600"></div>
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center flex-shrink-0">
                    <x-filament::icon icon="heroicon-m-identification" class="w-3.5 h-3.5 text-indigo-500"/>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Personal Information</h3>
            </div>
            <div class="px-5 py-2">
                <dl>
                    @foreach($demo['basic'] as $label => $value)
                        @if($value && $value !== 'N/A')
                            <div class="flex justify-between py-2.5 border-b last:border-0 border-gray-50 dark:border-gray-800">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="text-sm font-semibold text-gray-900 dark:text-white text-right">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- Contact Information --}}
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-indigo-400 to-indigo-600"></div>
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center flex-shrink-0">
                    <x-filament::icon icon="heroicon-m-phone" class="w-3.5 h-3.5 text-indigo-500"/>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Contact Information</h3>
            </div>
            <div class="px-5 py-2">
                <dl>
                    @foreach($demo['contact'] as $label => $value)
                        @if($value && $value !== 'N/A')
                            <div class="flex justify-between py-2.5 border-b last:border-0 border-gray-50 dark:border-gray-800">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="text-sm font-semibold text-gray-900 dark:text-white text-right">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- Address --}}
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-sky-400 to-sky-600"></div>
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center flex-shrink-0">
                    <x-filament::icon icon="heroicon-m-map-pin" class="w-3.5 h-3.5 text-sky-500"/>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Address</h3>
            </div>
            <div class="px-5 py-2">
                <dl>
                    @foreach($demo['address'] as $label => $value)
                        @if($value && $value !== 'N/A')
                            <div class="flex justify-between py-2.5 border-b last:border-0 border-gray-50 dark:border-gray-800">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="text-sm font-semibold text-gray-900 dark:text-white text-right">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- Socio-Demographics --}}
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-sky-400 to-sky-600"></div>
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center flex-shrink-0">
                    <x-filament::icon icon="heroicon-m-user-group" class="w-3.5 h-3.5 text-sky-500"/>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Socio-Demographics</h3>
            </div>
            <div class="px-5 py-2">
            @if($socio)
                <dl>
                    @foreach([
                        'Marital Status'       => $socio->marital_status,
                        'Living Arrangement'   => $socio->living_arrangement,
                        'Household Size'       => $socio->household_size ? (string)$socio->household_size : null,
                        'Primary Caregiver'    => $socio->primary_caregiver,
                        'Primary Language'     => $socio->primary_language,
                        'Accessibility at Home'=> $socio->accessibility_at_home,
                    ] as $label => $value)
                        @if($value)
                            <div class="flex justify-between py-2.5 border-b last:border-0 border-gray-50 dark:border-gray-800">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="text-sm font-semibold text-gray-900 dark:text-white text-right">{{ ucfirst(str_replace('_', ' ', $value)) }}</dd>
                            </div>
                        @endif
                    @endforeach
                    @if($socio->source_of_support)
                        <div class="py-3">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1.5">Source of Support</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach((array)$socio->source_of_support as $src)
                                    <x-filament::badge color="gray" size="sm">{{ ucwords(str_replace('_', ' ', $src)) }}</x-filament::badge>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </dl>
            @else
                <div class="flex flex-col items-center py-10 text-center">
                    <div class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                        <x-filament::icon icon="heroicon-m-user-group" class="w-5 h-5 text-gray-400"/>
                    </div>
                    <p class="text-sm font-medium text-gray-500">No socio-demographic data recorded</p>
                </div>
            @endif
            </div>
        </div>

        {{-- Education & Employment --}}
        @if($edu)
            <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden lg:col-span-2">
                <div class="h-0.5 bg-gradient-to-r from-violet-400 to-violet-600"></div>
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center flex-shrink-0">
                        <x-filament::icon icon="heroicon-m-academic-cap" class="w-3.5 h-3.5 text-violet-500"/>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Education & Employment</h3>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach([
                            'Education Level'   => $edu->education_level,
                            'School Type'       => $edu->school_type,
                            'School Name'       => $edu->school_name,
                            'Grade / Class'     => $edu->grade_level,
                            'Currently Enrolled'=> $edu->currently_enrolled ? 'Yes' : null,
                            'Employment Status' => $edu->employment_status,
                            'Occupation'        => $edu->occupation_type,
                            'Employer'          => $edu->employer_name,
                        ] as $label => $value)
                            @if($value)
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">{{ $label }}</p>
                                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-0.5">{{ ucfirst(str_replace('_', ' ', $value)) }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    @if($edu->attendance_challenges && $edu->attendance_notes)
                        <div class="mt-4 p-3.5 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-800">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-amber-600 dark:text-amber-400 mb-1">Attendance Challenge</p>
                            <p class="text-sm text-amber-700 dark:text-amber-300">{{ $edu->attendance_notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Intake: Family & Social History --}}
        @if($li && ($li->family_history || $li->social_history || $li->previous_interventions || $li->educational_background))
            <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden lg:col-span-2" x-data="{ open: true }">
                <div class="h-0.5 bg-gradient-to-r from-blue-400 to-blue-600"></div>
                <button @click="open = !open" class="w-full px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3 hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center flex-shrink-0">
                        <x-filament::icon icon="heroicon-m-clipboard-document-list" class="w-3.5 h-3.5 text-blue-500"/>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex-1 text-left">Family, Social & Background <span class="font-normal text-gray-400">(from Intake)</span></h3>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4 text-gray-400 transition-transform duration-200" ::class="open ? 'rotate-180' : ''"/>
                </button>
                <div x-show="open" x-collapse>
                    <div class="p-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            @if($li->family_history)
                                <div class="p-3.5 rounded-xl bg-blue-50/40 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30">
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-blue-400 mb-1.5">Family History</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $li->family_history }}</p>
                                </div>
                            @endif
                            @if($li->social_history)
                                <div class="p-3.5 rounded-xl bg-blue-50/40 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30">
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-blue-400 mb-1.5">Social History</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $li->social_history }}</p>
                                </div>
                            @endif
                            @if($li->previous_interventions)
                                <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1.5">Previous Interventions</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $li->previous_interventions }}</p>
                                </div>
                            @endif
                            @if($li->educational_background)
                                <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1.5">Educational Background (Intake)</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $li->educational_background }}</p>
                                </div>
                            @endif
                            @if($li->developmental_history)
                                <div class="md:col-span-2 p-3.5 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1.5">Developmental History</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $li->developmental_history }}</p>
                                </div>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-4">
                            Source: Intake assessment · {{ $li->created_at?->format('M d, Y') }}
                            @if($li->assessedBy) · {{ $li->assessedBy->name }} @endif
                        </p>
                    </div>
                </div>
            </div>
        @endif

    </div>
@endif

{{-- ============================================================ --}}
{{-- SCHOOL / PLACEMENT TAB                                       --}}
{{-- ============================================================ --}}
@if($activeTab === 'school')
    @forelse($this->schoolPlacements as $placement)
        <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-sky-400 to-sky-600"></div>
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center flex-shrink-0">
                        <x-filament::icon icon="heroicon-m-academic-cap" class="w-4.5 h-4.5 text-sky-500"/>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ $placement->school?->name ?? 'School Not Found' }}</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ $placement->program ?? '' }}
                            @if($placement->grade_level) · Grade {{ $placement->grade_level }} @endif
                        </p>
                    </div>
                </div>
                <x-filament::badge :color="match($placement->status) { 'active' => 'success', 'pending' => 'warning', 'completed' => 'info', 'discontinued' => 'danger', default => 'gray' }">
                    {{ ucfirst($placement->status) }}
                </x-filament::badge>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Placement Type</p>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-1">{{ ucfirst(str_replace('_', ' ', $placement->placement_type ?? 'N/A')) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Admission Date</p>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-1">{{ $placement->admission_date?->format('M d, Y') ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Expected Completion</p>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-1">{{ $placement->expected_completion_date?->format('M d, Y') ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Placement Officer</p>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-1">{{ $placement->placementOfficer?->name ?? 'N/A' }}</p>
                    </div>
                </div>

                @if($placement->academic_performance || $placement->social_performance)
                    <div class="grid grid-cols-2 gap-4">
                        @if($placement->academic_performance)
                            <div class="p-3.5 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/30">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-blue-500 mb-1">Academic Performance</p>
                                <p class="text-sm text-blue-700 dark:text-blue-300">{{ $placement->academic_performance }}</p>
                            </div>
                        @endif
                        @if($placement->social_performance)
                            <div class="p-3.5 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-100 dark:border-green-900/30">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-green-500 mb-1">Social Performance</p>
                                <p class="text-sm text-green-700 dark:text-green-300">{{ $placement->social_performance }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                @if($placement->support_services)
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2">Support Services</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach((array)$placement->support_services as $svc)
                                <x-filament::badge color="info" size="sm">{{ ucwords(str_replace('_', ' ', $svc)) }}</x-filament::badge>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($placement->review_notes && $placement->last_review_date)
                    <div class="p-3.5 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1">Last Review ({{ $placement->last_review_date?->format('M d, Y') }})</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $placement->review_notes }}</p>
                    </div>
                @endif

                @if($placement->school_contact_person)
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <x-filament::icon icon="heroicon-m-phone" class="w-3.5 h-3.5"/>
                        <span>School Contact: {{ $placement->school_contact_person }} — {{ $placement->school_contact_phone }}</span>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30 p-14 text-center">
            <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center mx-auto mb-4">
                <x-filament::icon icon="heroicon-m-academic-cap" class="w-6 h-6 text-sky-400"/>
            </div>
            <h3 class="text-base font-semibold text-gray-500 dark:text-gray-400">No School Placements</h3>
            <p class="text-sm text-gray-400 mt-1">No school placement records found for this client.</p>
        </div>
    @endforelse
@endif

{{-- ============================================================ --}}
{{-- VISIT HISTORY TAB                                            --}}
{{-- ============================================================ --}}
@if($activeTab === 'history')
    @if($this->visitHistory->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30 p-14 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
                <x-filament::icon icon="heroicon-m-calendar-days" class="w-6 h-6 text-gray-400"/>
            </div>
            <h3 class="text-base font-semibold text-gray-500 dark:text-gray-400">No Visit History</h3>
            <p class="text-sm text-gray-400 mt-1">This client has no previous visits.</p>
        </div>
    @else
        <div class="space-y-4">
        @foreach($this->visitHistory as $visit)
            @php
                $vTriage = $visit->triage;
                $vIntake = $visit->intakeAssessment;
                $billed  = $visit->invoices->sum('total_amount');
            @endphp
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden"
                 x-data="{ open: false }">

                {{-- Visit header row (always visible) --}}
                <button @click="open = !open"
                    class="w-full flex items-center justify-between px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors text-left">
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="font-mono font-bold text-primary-600 dark:text-primary-400 text-sm">{{ $visit->visit_number }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $visit->check_in_time?->format('M d, Y') }}</span>
                        <x-filament::badge :color="$visit->status === 'completed' ? 'success' : ($visit->status === 'in_progress' ? 'warning' : 'gray')" size="sm">
                            {{ ucfirst(str_replace('_', ' ', $visit->status)) }}
                        </x-filament::badge>
                        @if($visit->is_emergency)
                            <x-filament::badge color="danger" size="sm">Emergency</x-filament::badge>
                        @endif
                        @if($vIntake?->reason_for_visit)
                            <span class="text-xs text-gray-400 italic hidden sm:inline truncate max-w-[200px]">{{ $vIntake->reason_for_visit }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 flex-shrink-0 ml-3">
                        @if($billed > 0)
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">KES {{ number_format($billed) }}</span>
                        @endif
                        <span class="text-xs text-gray-400">{{ $visit->serviceBookings->count() }} svc</span>
                        <x-filament::icon :icon="'heroicon-m-chevron-' . (false ? 'up' : 'down')" class="w-4 h-4 text-gray-400 transition-transform" ::class="open ? 'rotate-180' : ''"/>
                    </div>
                </button>

                {{-- Expanded clinical detail --}}
                <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-800">
                    <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-5">

                        {{-- Visit basics --}}
                        <div class="space-y-3">
                            <p class="text-[10px] uppercase tracking-widest font-bold text-gray-400">Visit Details</p>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Type</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ ucfirst(str_replace('_', ' ', $visit->visit_type ?? 'walk_in')) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Check-in</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $visit->check_in_time?->format('H:i') }}</span>
                                </div>
                                @if($billed > 0)
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Total Billed</span>
                                        <span class="font-semibold text-gray-900 dark:text-white">KES {{ number_format($billed) }}</span>
                                    </div>
                                @endif
                            </div>

                            @if($visit->serviceBookings->isNotEmpty())
                                <div>
                                    <p class="text-[10px] uppercase tracking-widest text-gray-400 mb-1.5">Services</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($visit->serviceBookings as $bk)
                                            <x-filament::badge color="gray" size="sm">{{ $bk->service?->name ?? '—' }}</x-filament::badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Triage --}}
                        <div class="space-y-3">
                            <p class="text-[10px] uppercase tracking-widest font-bold text-gray-400">Triage</p>
                            @if($vTriage)
                                <div class="space-y-1.5 text-sm">
                                    @if($vTriage->presenting_complaint ?? $vTriage->chief_complaint ?? null)
                                        <div>
                                            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Chief Complaint</p>
                                            <p class="text-gray-800 dark:text-gray-200 text-xs mt-0.5">{{ $vTriage->presenting_complaint ?? $vTriage->chief_complaint }}</p>
                                        </div>
                                    @endif
                                    <div class="grid grid-cols-2 gap-2 pt-1">
                                        @if($vTriage->weight)
                                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-2 text-center">
                                                <p class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $vTriage->weight }} kg</p>
                                                <p class="text-[9px] text-gray-400 uppercase">Weight</p>
                                            </div>
                                        @endif
                                        @if($vTriage->height)
                                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-2 text-center">
                                                <p class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $vTriage->height }} cm</p>
                                                <p class="text-[9px] text-gray-400 uppercase">Height</p>
                                            </div>
                                        @endif
                                        @if($vTriage->systolic_bp && $vTriage->diastolic_bp)
                                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-2 text-center col-span-2">
                                                <p class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $vTriage->systolic_bp }}/{{ $vTriage->diastolic_bp }} mmHg</p>
                                                <p class="text-[9px] text-gray-400 uppercase">BP</p>
                                            </div>
                                        @endif
                                    </div>
                                    @if($vTriage->risk_level)
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-400">Risk:</span>
                                            <x-filament::badge :color="match($vTriage->risk_level) { 'high', 'critical' => 'danger', 'medium', 'moderate' => 'warning', default => 'success' }" size="sm">
                                                {{ ucfirst($vTriage->risk_level) }}
                                            </x-filament::badge>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <p class="text-xs text-gray-400 italic">No triage recorded</p>
                            @endif
                        </div>

                        {{-- Intake Assessment --}}
                        <div class="space-y-3">
                            <p class="text-[10px] uppercase tracking-widest font-bold text-gray-400">Intake Assessment</p>
                            @if($vIntake)
                                <div class="space-y-2 text-sm">
                                    @if($vIntake->reason_for_visit)
                                        <div>
                                            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Reason for Visit</p>
                                            <p class="text-xs text-gray-800 dark:text-gray-200 mt-0.5">{{ $vIntake->reason_for_visit }}</p>
                                        </div>
                                    @endif
                                    @if($vIntake->current_concerns)
                                        <div>
                                            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Concerns</p>
                                            <p class="text-xs text-gray-800 dark:text-gray-200 mt-0.5 line-clamp-2">{{ $vIntake->current_concerns }}</p>
                                        </div>
                                    @endif
                                    @if($vIntake->recommendations)
                                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-2.5 border border-blue-100 dark:border-blue-800">
                                            <p class="text-[10px] text-blue-500 uppercase tracking-wide">Recommendations</p>
                                            <p class="text-xs text-blue-800 dark:text-blue-200 mt-0.5">{{ $vIntake->recommendations }}</p>
                                        </div>
                                    @endif
                                    @if($vIntake->priority_level)
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-400">Priority:</span>
                                            <x-filament::badge :color="match((int)$vIntake->priority_level) { 1 => 'danger', 2 => 'warning', 3 => 'info', default => 'gray' }" size="sm">
                                                Level {{ $vIntake->priority_level }}
                                            </x-filament::badge>
                                        </div>
                                    @endif
                                    <div class="text-xs text-gray-400">
                                        By: {{ $vIntake->assessedBy?->name ?? 'N/A' }}
                                        @if($vIntake->is_finalized)
                                            · <span class="text-green-500">Finalized</span>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <p class="text-xs text-gray-400 italic">No intake assessment recorded</p>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
        @endforeach
        </div>
    @endif
@endif

{{-- ============================================================ --}}
{{-- MDT TAB                                                      --}}
{{-- ============================================================ --}}
@if($activeTab === 'mdt')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            {{-- MDT Summary --}}
            <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
                <div class="h-0.5 bg-gradient-to-r from-purple-400 to-purple-600"></div>
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center flex-shrink-0">
                        <x-filament::icon icon="heroicon-m-users" class="w-3.5 h-3.5 text-purple-500"/>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Multidisciplinary Team Summary</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Consolidated input from all clinical departments.</p>
                    </div>
                </div>
                <div class="p-5">
                    @if($activeVisit)
                        @php $depts = $activeVisit->serviceBookings->pluck('service.department.name')->filter()->unique()->values(); @endphp
                        @if($depts->isNotEmpty())
                            <div class="mb-4">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2">Departments Involved (Current Visit)</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($depts as $dept)
                                        <x-filament::badge color="primary" icon="heroicon-m-building-office-2">{{ $dept }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                    <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30 p-8 text-center">
                        <div class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-3">
                            <x-filament::icon icon="heroicon-m-document-plus" class="w-5 h-5 text-gray-400"/>
                        </div>
                        <p class="text-sm font-medium text-gray-500">MDT notes and meeting records will appear here.</p>
                        <p class="text-xs text-gray-400 mt-1">This section is ready for MDT module integration.</p>
                    </div>
                </div>
            </div>

            {{-- Active Internal Referrals --}}
            @if($this->internalReferrals->isNotEmpty())
                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden" x-data="{ open: true }">
                    <div class="h-0.5 bg-gradient-to-r from-amber-400 to-amber-600"></div>
                    <button @click="open = !open" class="w-full px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3 hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center flex-shrink-0">
                            <x-filament::icon icon="heroicon-m-arrow-path-rounded-square" class="w-3.5 h-3.5 text-amber-500"/>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex-1 text-left">Active Internal Referrals</h3>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4 text-gray-400 transition-transform duration-200" ::class="open ? 'rotate-180' : ''"/>
                    </button>
                    <div x-show="open" x-collapse>
                        <div class="p-5 space-y-2">
                            @foreach($this->internalReferrals->where('status', 'pending') as $ref)
                                <div class="flex items-center justify-between p-3.5 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-500">{{ $ref->fromDepartment?->name ?? '?' }}</span>
                                        <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4 text-amber-500"/>
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $ref->toDepartment?->name ?? '?' }}</span>
                                    </div>
                                    <x-filament::badge color="warning" size="sm">Pending</x-filament::badge>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Team roster sidebar --}}
        <div class="space-y-5">
            <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
                <div class="h-0.5 bg-gradient-to-r from-purple-400 to-purple-600"></div>
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center flex-shrink-0">
                        <x-filament::icon icon="heroicon-m-user-group" class="w-3.5 h-3.5 text-purple-500"/>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Care Team</h3>
                </div>
                <div class="p-5 space-y-3">
                    @if($activeVisit?->triage?->user)
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-pink-100 dark:bg-pink-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold text-pink-600 dark:text-pink-400">TN</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $activeVisit->triage->user->name }}</p>
                                <p class="text-xs text-gray-400">Triage Nurse</p>
                            </div>
                        </div>
                    @endif
                    @if($activeVisit?->intakeAssessment?->user)
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold text-blue-600 dark:text-blue-400">IO</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $activeVisit->intakeAssessment->user->name }}</p>
                                <p class="text-xs text-gray-400">Intake Officer</p>
                            </div>
                        </div>
                    @endif
                    @if(!$activeVisit?->triage && !$activeVisit?->intakeAssessment)
                        <div class="flex flex-col items-center py-6 text-center">
                            <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-2">
                                <x-filament::icon icon="heroicon-m-user-group" class="w-4 h-4 text-gray-400"/>
                            </div>
                            <p class="text-sm text-gray-400">No team members assigned yet</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Clinical Flags --}}
            <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06] overflow-hidden">
                <div class="h-0.5 bg-gradient-to-r from-red-400 to-red-600"></div>
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center flex-shrink-0">
                        <x-filament::icon icon="heroicon-m-flag" class="w-3.5 h-3.5 text-red-500"/>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Clinical Flags</h3>
                </div>
                <div class="p-5 space-y-2">
                    @if($this->allergies->isNotEmpty())
                        <div class="flex items-center gap-2 p-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30 text-sm">
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-4 h-4 text-red-500 flex-shrink-0"/>
                            <span class="text-red-700 dark:text-red-300">{{ $this->allergies->count() }} known allerg{{ $this->allergies->count() === 1 ? 'y' : 'ies' }}</span>
                        </div>
                    @endif
                    @if($activeVisit?->is_emergency)
                        <div class="flex items-center gap-2 p-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30 text-sm">
                            <x-filament::icon icon="heroicon-m-bolt" class="w-4 h-4 text-red-500 flex-shrink-0"/>
                            <span class="text-red-700 dark:text-red-300">Emergency visit</span>
                        </div>
                    @endif
                    @if($client->ncpwd_number)
                        <div class="flex items-center gap-2 p-3 rounded-xl bg-purple-50 dark:bg-purple-900/20 border border-purple-100 dark:border-purple-900/30 text-sm">
                            <x-filament::icon icon="heroicon-m-star" class="w-4 h-4 text-purple-500 flex-shrink-0"/>
                            <span class="text-purple-700 dark:text-purple-300">NCPWD registered</span>
                        </div>
                    @endif
                    @if(!$this->allergies->isNotEmpty() && !$activeVisit?->is_emergency && !$client->ncpwd_number)
                        <p class="text-sm text-gray-400 text-center py-3">No flags</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

    </div>{{-- end tab content column --}}

</div>{{-- end sidebar + content flex --}}

</div>{{-- end visual wrapper --}}

{{-- Mobile: show content below nav (no extra wrapper needed, content is already visible) --}}

{{-- ====================================================================== --}}
{{-- MODALS                                                                  --}}
{{-- ====================================================================== --}}

{{-- Request Service Modal --}}
<x-filament::modal id="request-service-modal" width="2xl">
    <x-slot name="heading">Request Additional Service</x-slot>
    <x-slot name="description">Add services to the current visit. Client must pay at Cashier before delivery.</x-slot>
    <form wire:submit="requestNewService">
        {{ $this->requestNewServiceForm }}
        <div class="mt-6 flex justify-end gap-3">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'request-service-modal' })">Cancel</x-filament::button>
            <x-filament::button type="submit" color="primary" icon="heroicon-m-check">Submit Request</x-filament::button>
        </div>
    </form>
</x-filament::modal>

{{-- Book Appointment Modal --}}
<x-filament::modal id="appointment-modal" width="2xl">
    <x-slot name="heading">Book Appointment</x-slot>
    <x-slot name="description">Schedule a future appointment for this client.</x-slot>
    <form wire:submit="createAppointment">
        {{ $this->createAppointmentForm }}
        <div class="mt-6 flex justify-end gap-3">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'appointment-modal' })">Cancel</x-filament::button>
            <x-filament::button type="submit" color="success" icon="heroicon-m-check-circle">Book Appointment</x-filament::button>
        </div>
    </form>
</x-filament::modal>

@endif {{-- end $client check --}}

</x-filament-panels::page>
