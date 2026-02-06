{{-- OVERVIEW TAB --}}
<div class="space-y-6">
    {{-- CURRENT VISIT STATUS --}}
    @if($currentVisit)
        <x-filament::section>
            <x-slot name="heading">
                Current Visit
            </x-slot>

            <div class="space-y-4">
                {{-- Visit Info --}}
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-gray-700">Check-in Time</div>
                        <div class="text-lg">{{ $currentVisit->check_in_time->format('h:i A') }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-700">Current Stage</div>
                        <x-filament::badge size="lg">
                            {{ str($currentVisit->current_stage)->title() }}
                        </x-filament::badge>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-700">Status</div>
                        <x-filament::badge size="lg" color="success">
                            {{ str($currentVisit->status)->title() }}
                        </x-filament::badge>
                    </div>
                </div>

                {{-- Active Services --}}
                @php
                    $activeServices = $this->getCurrentVisitServices();
                @endphp

                @if($activeServices->count() > 0)
                    <div class="mt-4">
                        <h4 class="text-sm font-semibold text-gray-900 mb-2">Active Services</h4>
                        <div class="space-y-2">
                            @foreach($activeServices as $booking)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <x-heroicon-o-beaker class="w-5 h-5 text-gray-400"/>
                                        <div>
                                            <div class="font-medium">{{ $booking->service->name }}</div>
                                            <div class="text-sm text-gray-600">{{ $booking->department->name }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if($booking->queueEntry)
                                            <x-filament::badge>
                                                Queue #{{ $booking->queueEntry->queue_number }}
                                            </x-filament::badge>
                                        @endif
                                        <x-filament::badge color="{{ $booking->service_status === 'completed' ? 'success' : 'warning' }}">
                                            {{ str($booking->service_status)->title() }}
                                        </x-filament::badge>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="text-center py-8">
                <x-heroicon-o-calendar-days class="mx-auto h-12 w-12 text-gray-400"/>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">No Active Visit</h3>
                <p class="mt-1 text-sm text-gray-500">Client does not currently have an active visit</p>
            </div>
        </x-filament::section>
    @endif

    {{-- PENDING SERVICE REQUESTS --}}
    @php
        $pendingRequests = $this->getPendingServiceRequests();
    @endphp

    @if($pendingRequests->count() > 0)
        <x-filament::section>
            <x-slot name="heading">
                Pending Service Requests
            </x-slot>
            <x-slot name="description">
                These services are awaiting payment at the cashier
            </x-slot>

            <div class="space-y-2">
                @foreach($pendingRequests as $request)
                    <div class="flex items-center justify-between p-3 bg-warning-50 border border-warning-200 rounded-lg">
                        <div class="flex items-center gap-3">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-600"/>
                            <div>
                                <div class="font-medium text-gray-900">{{ $request->service->name }}</div>
                                <div class="text-sm text-gray-600">
                                    Requested by {{ $request->requestedBy->name }} • {{ $request->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::badge color="{{ $request->priority_color }}">
                                {{ str($request->priority)->upper() }}
                            </x-filament::badge>
                            <x-filament::badge color="warning">
                                Pending Payment
                            </x-filament::badge>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- QUICK ACTIONS --}}
    @if($currentVisit)
        <x-filament::section>
            <x-slot name="heading">
                Quick Actions
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Request Service Action --}}
                <div wire:click="$wire.requestServiceAction()" 
                     class="flex flex-col items-center justify-center p-6 bg-white border-2 border-dashed border-gray-300 rounded-lg hover:border-primary-500 hover:bg-primary-50 cursor-pointer transition-all">
                    <x-heroicon-o-plus-circle class="w-10 h-10 text-primary-600 mb-2"/>
                    <span class="font-semibold text-gray-900">Request Service</span>
                    <span class="text-sm text-gray-500 text-center mt-1">Add another service during visit</span>
                </div>

                {{-- View Queue Status --}}
                <a href="{{ route('filament.admin.resources.service-point-dashboards.index') }}" 
                   class="flex flex-col items-center justify-center p-6 bg-white border-2 border-dashed border-gray-300 rounded-lg hover:border-info-500 hover:bg-info-50 cursor-pointer transition-all">
                    <x-heroicon-o-queue-list class="w-10 h-10 text-info-600 mb-2"/>
                    <span class="font-semibold text-gray-900">Queue Status</span>
                    <span class="text-sm text-gray-500 text-center mt-1">View service queue position</span>
                </a>

                {{-- Print Summary --}}
                <button 
                   class="flex flex-col items-center justify-center p-6 bg-white border-2 border-dashed border-gray-300 rounded-lg hover:border-success-500 hover:bg-success-50 cursor-pointer transition-all">
                    <x-heroicon-o-printer class="w-10 h-10 text-success-600 mb-2"/>
                    <span class="font-semibold text-gray-900">Print Summary</span>
                    <span class="text-sm text-gray-500 text-center mt-1">Print visit summary</span>
                </button>
            </div>
        </x-filament::section>
    @endif
</div>