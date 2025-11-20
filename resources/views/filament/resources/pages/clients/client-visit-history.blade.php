<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-calendar-days class="w-5 h-5 text-gray-500" />
                    <span>Recent Visit History</span>
                </div>
                @if($this->getViewData()['totalVisits'] > 0)
                    <span class="text-sm font-normal text-gray-500">
                        {{ $this->getViewData()['totalVisits'] }} total visits
                    </span>
                @endif
            </div>
        </x-slot>

        <div class="space-y-3">
            @forelse($this->getViewData()['visits'] as $visit)
                <div class="flex items-start gap-4 p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    {{-- Visit Number Badge --}}
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 text-primary-700">
                            <x-heroicon-o-hashtag class="w-6 h-6" />
                        </div>
                    </div>

                    {{-- Visit Details --}}
                    <div class="flex-1 min-w-0">
                        {{-- Visit Number & Type --}}
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="text-sm font-semibold text-gray-900">
                                {{ $visit->visit_number }}
                            </h4>
                            <span @class([
                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                'bg-blue-100 text-blue-800' => $visit->visit_type === 'initial',
                                'bg-green-100 text-green-800' => $visit->visit_type === 'follow_up',
                                'bg-red-100 text-red-800' => $visit->visit_type === 'emergency',
                                'bg-gray-100 text-gray-800' => $visit->visit_type === 'review',
                            ])>
                                {{ ucwords(str_replace('_', ' ', $visit->visit_type)) }}
                            </span>
                            @if($visit->is_emergency)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                    <x-heroicon-o-exclamation-triangle class="w-3 h-3 mr-1" />
                                    Emergency
                                </span>
                            @endif
                        </div>

                        {{-- Visit Purpose --}}
                        @if($visit->visit_purpose)
                            <p class="text-sm text-gray-600 mb-2">
                                <span class="font-medium">Purpose:</span> {{ ucfirst($visit->visit_purpose) }}
                            </p>
                        @endif

                        {{-- Visit Metadata --}}
                        <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500">
                            {{-- Check-in Time --}}
                            <div class="flex items-center gap-1">
                                <x-heroicon-o-clock class="w-4 h-4" />
                                <span>{{ $visit->check_in_time->format('d M Y, H:i') }}</span>
                            </div>

                            {{-- Branch --}}
                            @if($visit->branch)
                                <div class="flex items-center gap-1">
                                    <x-heroicon-o-building-office class="w-4 h-4" />
                                    <span>{{ $visit->branch->name }}</span>
                                </div>
                            @endif

                            {{-- Checked In By --}}
                            @if($visit->checkedInBy)
                                <div class="flex items-center gap-1">
                                    <x-heroicon-o-user class="w-4 h-4" />
                                    <span>{{ $visit->checkedInBy->name }}</span>
                                </div>
                            @endif

                            {{-- Duration (if completed) --}}
                            @if($visit->check_out_time)
                                <div class="flex items-center gap-1">
                                    <x-heroicon-o-arrow-path class="w-4 h-4" />
                                    <span>{{ $visit->check_in_time->diffForHumans($visit->check_out_time, true) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Current Stage & Status --}}
                    <div class="flex-shrink-0 text-right">
                        {{-- Current Stage --}}
                        <div class="mb-2">
                            <span @class([
                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                'bg-gray-100 text-gray-800' => $visit->current_stage === 'reception',
                                'bg-blue-100 text-blue-800' => $visit->current_stage === 'triage',
                                'bg-purple-100 text-purple-800' => $visit->current_stage === 'intake',
                                'bg-yellow-100 text-yellow-800' => $visit->current_stage === 'billing',
                                'bg-orange-100 text-orange-800' => $visit->current_stage === 'payment',
                                'bg-green-100 text-green-800' => $visit->current_stage === 'service',
                            ])>
                                {{ ucfirst($visit->current_stage) }}
                            </span>
                        </div>

                        {{-- Visit Status --}}
                        <div>
                            <span @class([
                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                'bg-yellow-100 text-yellow-800' => $visit->status === 'in_progress',
                                'bg-green-100 text-green-800' => $visit->status === 'completed',
                                'bg-red-100 text-red-800' => $visit->status === 'cancelled',
                            ])>
                                @if($visit->status === 'in_progress')
                                    <x-heroicon-o-arrow-path class="w-3 h-3 mr-1 animate-spin" />
                                @elseif($visit->status === 'completed')
                                    <x-heroicon-o-check-circle class="w-3 h-3 mr-1" />
                                @else
                                    <x-heroicon-o-x-circle class="w-3 h-3 mr-1" />
                                @endif
                                {{ ucwords(str_replace('_', ' ', $visit->status)) }}
                            </span>
                        </div>

                        {{-- View Visit Link --}}
                        <div class="mt-2">
                            <a 
                                href="{{ route('filament.admin.resources.visits.view', $visit) }}" 
                                class="text-xs text-primary-600 hover:text-primary-800 font-medium"
                            >
                                View Details →
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-12">
                    <x-heroicon-o-calendar-days class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                    <h3 class="text-sm font-medium text-gray-900 mb-1">No visit history</h3>
                    <p class="text-sm text-gray-500">This client hasn't visited the facility yet.</p>
                    <div class="mt-4">
                        <a 
                            href="{{ route('filament.admin.resources.visits.create', ['client' => $this->record?->id]) }}" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        >
                            <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                            Create First Visit
                        </a>
                    </div>
                </div>
            @endforelse

            {{-- Show More Link --}}
            @if($this->getViewData()['totalVisits'] > 10)
                <div class="pt-3 border-t border-gray-200">
                    <a 
                        href="{{ route('filament.admin.resources.clients.view', ['record' => $this->record?->id]) }}#visits" 
                        class="text-sm text-primary-600 hover:text-primary-800 font-medium"
                    >
                        View all {{ $this->getViewData()['totalVisits'] }} visits →
                    </a>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>