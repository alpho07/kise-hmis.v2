{{-- HISTORY TAB --}}
<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">
            Visit History
        </x-slot>
        <x-slot name="description">
            Complete record of all visits and services
        </x-slot>

        @php
            $visits = $client->visits()->with(['serviceBookings.service', 'serviceBookings.department'])->latest()->limit(10)->get();
        @endphp

        @if($visits->count() > 0)
            <div class="space-y-4">
                @foreach($visits as $visit)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        {{-- Visit Header --}}
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center">
                                        <x-heroicon-o-calendar class="w-6 h-6 text-primary-600"/>
                                    </div>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">
                                        {{ $visit->check_in_time->format('M d, Y') }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $visit->check_in_time->format('h:i A') }}
                                        @if($visit->check_out_time)
                                            - {{ $visit->check_out_time->format('h:i A') }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::badge color="{{ $visit->status === 'completed' ? 'success' : ($visit->status === 'in_progress' ? 'warning' : 'gray') }}">
                                    {{ str($visit->status)->title() }}
                                </x-filament::badge>
                            </div>
                        </div>

                        {{-- Services for this visit --}}
                        @if($visit->serviceBookings->count() > 0)
                            <div class="ml-15">
                                <div class="text-sm font-medium text-gray-700 mb-2">Services</div>
                                <div class="space-y-2">
                                    @foreach($visit->serviceBookings as $booking)
                                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                            <div class="flex items-center gap-2">
                                                <x-heroicon-o-beaker class="w-4 h-4 text-gray-400"/>
                                                <span class="text-sm text-gray-900">{{ $booking->service->name }}</span>
                                                <span class="text-xs text-gray-500">• {{ $booking->department->name }}</span>
                                            </div>
                                            <x-filament::badge 
                                                size="sm"
                                                color="{{ $booking->service_status === 'completed' ? 'success' : 'warning' }}">
                                                {{ str($booking->service_status)->title() }}
                                            </x-filament::badge>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="ml-15 text-sm text-gray-500 italic">
                                No services recorded for this visit
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12">
                <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400"/>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">No visit history</h3>
                <p class="mt-1 text-sm text-gray-500">This client hasn't had any visits yet</p>
            </div>
        @endif
    </x-filament::section>

    {{-- SUMMARY STATISTICS --}}
    <x-filament::section>
        <x-slot name="heading">
            Summary Statistics
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <div class="text-3xl font-bold text-primary-600">
                    {{ $client->visits()->count() }}
                </div>
                <div class="text-sm text-gray-600 mt-1">Total Visits</div>
            </div>

            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <div class="text-3xl font-bold text-success-600">
                    {{ $client->serviceBookings()->where('service_status', 'completed')->count() }}
                </div>
                <div class="text-sm text-gray-600 mt-1">Completed Services</div>
            </div>

            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <div class="text-3xl font-bold text-info-600">
                    @php
                        $firstVisit = $client->visits()->oldest()->first();
                    @endphp
                    @if($firstVisit)
                        {{ $firstVisit->check_in_time->diffInMonths(now()) }}
                    @else
                        0
                    @endif
                </div>
                <div class="text-sm text-gray-600 mt-1">Months as Client</div>
            </div>
        </div>
    </x-filament::section>
</div>