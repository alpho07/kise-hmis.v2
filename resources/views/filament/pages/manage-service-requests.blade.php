<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Info Alert -->
        <x-filament::section>
            <x-slot name="heading">
                Service Requests Awaiting Payment
            </x-slot>
            <x-slot name="description">
                These are additional services requested by providers during client visits. Process payment to add the service to the department queue.
            </x-slot>
        </x-filament::section>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600">
                        {{ \App\Models\ServiceRequest::where('status', 'pending_payment')->count() }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">Pending Payment</div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold text-warning-600">
                        {{ \App\Models\ServiceRequest::where('priority', 'urgent')->where('status', 'pending_payment')->count() }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">Urgent Requests</div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-center">
                    <div class="text-3xl font-bold text-success-600">
                        {{ \App\Models\ServiceRequest::whereDate('paid_at', today())->count() }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">Paid Today</div>
                </div>
            </x-filament::card>
        </div>

        <!-- Service Requests Table -->
        {{ $this->table }}
    </div>
</x-filament-panels::page>