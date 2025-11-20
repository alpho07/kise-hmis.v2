<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-gray-500" />
                    <span>Service Bookings & Queue Status</span>
                </div>
                @if($this->getViewData()['hasServices'])
                    <span class="text-sm font-normal text-gray-500">
                        {{ $this->getViewData()['totalServices'] }} service(s) booked
                    </span>
                @endif
            </div>
        </x-slot>

        @if($this->getViewData()['hasServices'])
            {{-- Summary Stats --}}
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="text-2xl font-bold text-gray-900">
                        {{ $this->getViewData()['stats']['total'] }}
                    </div>
                    <div class="text-xs text-gray-600 mt-1">Total Services</div>
                </div>

                <div class="p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <div class="text-2xl font-bold text-yellow-900">
                        {{ $this->getViewData()['stats']['in_progress'] }}
                    </div>
                    <div class="text-xs text-yellow-700 mt-1">In Progress</div>
                </div>

                <div class="p-4 bg-green-50 rounded-lg border border-green-200">
                    <div class="text-2xl font-bold text-green-900">
                        {{ $this->getViewData()['stats']['completed'] }}
                    </div>
                    <div class="text-xs text-green-700 mt-1">Completed</div>
                </div>
            </div>

            {{-- Service Bookings List --}}
            <div class="space-y-4">
                @foreach($this->getViewData()['serviceBookings'] as $booking)
                    <div class="p-4 border border-gray-200 rounded-lg hover:shadow-md transition">
                        <div class="flex items-start justify-between mb-3">
                            {{-- Service Info --}}
                            <div class="flex-1">
                                <h4 class="text-base font-semibold text-gray-900 mb-1">
                                    {{ $booking['service_name'] }}
                                </h4>
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <x-heroicon-o-building-office class="w-4 h-4" />
                                    <span>{{ $booking['department_name'] }} Department</span>
                                </div>
                            </div>

                            {{-- Status Badges --}}
                            <div class="flex flex-col gap-2 items-end">
                                {{-- Booking Status --}}
                                <span @class([
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    'bg-yellow-100 text-yellow-800' => $booking['booking_status_color'] === 'warning',
                                    'bg-green-100 text-green-800' => $booking['booking_status_color'] === 'success',
                                    'bg-red-100 text-red-800' => $booking['booking_status_color'] === 'danger',
                                    'bg-blue-100 text-blue-800' => $booking['booking_status_color'] === 'info',
                                    'bg-gray-100 text-gray-800' => $booking['booking_status_color'] === 'gray',
                                ])>
                                    {{ ucwords(str_replace('_', ' ', $booking['booking_status'])) }}
                                </span>

                                {{-- Service Status --}}
                                <span @class([
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    'bg-yellow-100 text-yellow-800' => $booking['service_status_color'] === 'warning',
                                    'bg-green-100 text-green-800' => $booking['service_status_color'] === 'success',
                                    'bg-red-100 text-red-800' => $booking['service_status_color'] === 'danger',
                                    'bg-amber-100 text-amber-800' => $booking['service_status_color'] === 'amber',
                                    'bg-gray-100 text-gray-800' => $booking['service_status_color'] === 'gray',
                                ])>
                                    Service: {{ ucwords(str_replace('_', ' ', $booking['service_status'])) }}
                                </span>
                            </div>
                        </div>

                        {{-- Booking Details --}}
                        <div class="flex items-center gap-6 text-xs text-gray-600 mb-3">
                            <div>
                                <span class="font-medium">Booked:</span>
                                <span>{{ \Carbon\Carbon::parse($booking['booking_date'])->format('d M Y, H:i') }}</span>
                            </div>
                            @if($booking['scheduled_date'])
                                <div>
                                    <span class="font-medium">Scheduled:</span>
                                    <span>{{ \Carbon\Carbon::parse($booking['scheduled_date'])->format('d M Y, H:i') }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Queue Status (if in queue) --}}
                        @if($booking['in_queue'])
                            <div class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-100">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <span class="text-xs text-gray-600">Queue Number:</span>
                                            <span class="ml-2 text-lg font-bold text-blue-900">
                                                {{ $booking['queue_number'] }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-600">Queue Status:</span>
                                            <span @class([
                                                'ml-2 text-sm font-semibold',
                                                'text-blue-700' => $booking['queue_status_color'] === 'info',
                                                'text-yellow-700' => $booking['queue_status_color'] === 'warning',
                                                'text-purple-700' => $booking['queue_status_color'] === 'primary',
                                                'text-green-700' => $booking['queue_status_color'] === 'success',
                                                'text-red-700' => $booking['queue_status_color'] === 'danger',
                                            ])>
                                                {{ ucwords(str_replace('_', ' ', $booking['queue_status'])) }}
                                            </span>
                                        </div>
                                        @if($booking['queue_wait_time'])
                                            <div>
                                                <span class="text-xs text-gray-600">Waiting:</span>
                                                <span class="ml-2 text-sm font-semibold text-gray-900">
                                                    {{ $booking['queue_wait_time'] }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Queue Status Icon --}}
                                    <div>
                                        @if($booking['queue_status'] === 'waiting')
                                            <x-heroicon-o-clock class="w-6 h-6 text-blue-600" />
                                        @elseif($booking['queue_status'] === 'called')
                                            <x-heroicon-o-megaphone class="w-6 h-6 text-yellow-600" />
                                        @elseif($booking['queue_status'] === 'serving')
                                            <x-heroicon-o-arrow-path class="w-6 h-6 text-purple-600 animate-spin" />
                                        @elseif($booking['queue_status'] === 'completed')
                                            <x-heroicon-o-check-circle class="w-6 h-6 text-green-600" />
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Not in queue yet --}}
                            @if($booking['booking_status'] === 'pending_payment')
                                <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <div class="flex items-center gap-2 text-sm text-yellow-800">
                                        <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                                        <span class="font-medium">Payment required before entering queue</span>
                                    </div>
                                </div>
                            @elseif($booking['booking_status'] === 'confirmed' && $booking['service_status'] === 'not_started')
                                <div class="mt-3 p-3 bg-green-50 rounded-lg border border-green-200">
                                    <div class="flex items-center gap-2 text-sm text-green-800">
                                        <x-heroicon-o-check-circle class="w-5 h-5" />
                                        <span class="font-medium">Payment confirmed - Ready to enter queue</span>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Overall Progress --}}
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600 font-medium">Overall Progress:</span>
                    <div class="flex items-center gap-2">
                        <div class="text-right">
                            <span class="text-gray-900 font-semibold">
                                {{ $this->getViewData()['stats']['completed'] }} / {{ $this->getViewData()['stats']['total'] }}
                            </span>
                            <span class="text-gray-600">services completed</span>
                        </div>
                        @if($this->getViewData()['stats']['completed'] === $this->getViewData()['stats']['total'])
                            <x-heroicon-o-check-badge class="w-6 h-6 text-green-600" />
                        @endif
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="mt-3 w-full bg-gray-200 rounded-full h-3">
                    <div 
                        class="bg-green-600 h-3 rounded-full transition-all duration-500"
                        style="width: {{ $this->getViewData()['stats']['total'] > 0 ? ($this->getViewData()['stats']['completed'] / $this->getViewData()['stats']['total']) * 100 : 0 }}%"
                    ></div>
                </div>
            </div>
        @else
            {{-- No Services Booked --}}
            <div class="text-center py-12">
                <x-heroicon-o-wrench-screwdriver class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                <h3 class="text-sm font-medium text-gray-900 mb-1">No services booked</h3>
                <p class="text-sm text-gray-500 mb-4">Services will be selected during the intake assessment.</p>
                
                @if($this->record && $this->record->current_stage === 'intake')
                    <a 
                        href="{{ route('filament.admin.resources.intake-assessments.create', ['visit' => $this->record->id]) }}" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700"
                    >
                        <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                        Complete Intake & Select Services
                    </a>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>