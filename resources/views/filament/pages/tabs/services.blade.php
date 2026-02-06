{{-- SERVICES TAB --}}
<div class="space-y-6">
    {{-- REQUEST NEW SERVICE --}}
    @if($currentVisit)
        <x-filament::section>
            <x-slot name="heading">
                Request Additional Service
            </x-slot>
            <x-slot name="description">
                Add more services during this visit. Client will need to pay before service is provided.
            </x-slot>

            {{ $this->requestServiceAction() }}
        </x-filament::section>
    @endif

    {{-- CURRENT VISIT SERVICES --}}
    @php
        $activeServices = $this->getCurrentVisitServices();
    @endphp

    @if($activeServices->count() > 0)
        <x-filament::section>
            <x-slot name="heading">
                Services for Current Visit
            </x-slot>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Queue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($activeServices as $booking)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">{{ $booking->service->name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $booking->department->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($booking->queueEntry)
                                        <x-filament::badge>
                                            #{{ $booking->queueEntry->queue_number }}
                                        </x-filament::badge>
                                    @else
                                        <span class="text-sm text-gray-400">Not in queue</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-filament::badge 
                                        color="{{ $booking->service_status === 'completed' ? 'success' : ($booking->service_status === 'in_progress' ? 'primary' : 'warning') }}">
                                        {{ str($booking->service_status)->title() }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-filament::badge color="{{ $booking->payment_status === 'paid' ? 'success' : 'warning' }}">
                                        {{ str($booking->payment_status)->title() }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
                Waiting for payment at cashier
            </x-slot>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($pendingRequests as $request)
                            <tr class="bg-warning-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ $request->service->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $request->serviceDepartment->name }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $request->requestedBy->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-filament::badge color="{{ $request->priority_color }}">
                                        {{ str($request->priority)->upper() }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                    KES {{ number_format($request->cost, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $request->requested_at->diffForHumans() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- SERVICE HISTORY --}}
    @php
        $serviceHistory = $this->getServiceHistory();
    @endphp

    @if($serviceHistory->count() > 0)
        <x-filament::section>
            <x-slot name="heading">
                Recent Service History
            </x-slot>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($serviceHistory as $booking)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $booking->booking_date->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ $booking->service->name }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $booking->department->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-filament::badge color="success">
                                        Completed
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</div>