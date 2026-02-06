<x-filament-panels::page>
    @if($client)
        {{-- HEADER SECTION --}}
        <div class="mb-6">
            {{-- Client Info Card --}}
            <div class="bg-gradient-to-r from-primary-600 to-primary-800 rounded-lg shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-3xl font-bold">{{ $client->full_name }}</h2>
                        <div class="flex gap-4 mt-2 text-primary-100">
                            <span class="flex items-center gap-1">
                                <x-heroicon-o-identification class="w-4 h-4"/>
                                {{ $client->uci }}
                            </span>
                            <span class="flex items-center gap-1">
                                <x-heroicon-o-cake class="w-4 h-4"/>
                                {{ $client->age }} years
                            </span>
                            @if($client->phone)
                                <span class="flex items-center gap-1">
                                    <x-heroicon-o-phone class="w-4 h-4"/>
                                    {{ $client->phone }}
                                </span>
                            @endif
                        </div>
                    </div>
                    
                    @if($currentVisit)
                        <div class="bg-white/20 rounded-lg px-4 py-2">
                            <div class="text-sm text-primary-100">Active Visit</div>
                            <div class="text-lg font-semibold">{{ $currentVisit->current_stage }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Stats Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                <x-filament::card>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-primary-600">{{ $this->getStats()['total_visits'] }}</div>
                            <div class="text-sm text-gray-600">Total Visits</div>
                        </div>
                        <x-heroicon-o-clipboard-document-list class="w-8 h-8 text-primary-300"/>
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-success-600">{{ $this->getStats()['total_services'] }}</div>
                            <div class="text-sm text-gray-600">Services Received</div>
                        </div>
                        <x-heroicon-o-check-circle class="w-8 h-8 text-success-300"/>
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-warning-600">{{ $this->getStats()['pending_requests'] }}</div>
                            <div class="text-sm text-gray-600">Pending Requests</div>
                        </div>
                        <x-heroicon-o-clock class="w-8 h-8 text-warning-300"/>
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-gray-600">
                                @if($this->getStats()['last_visit'])
                                    {{ $this->getStats()['last_visit']->diffForHumans() }}
                                @else
                                    Never
                                @endif
                            </div>
                            <div class="text-sm text-gray-600">Last Visit</div>
                        </div>
                        <x-heroicon-o-calendar class="w-8 h-8 text-gray-300"/>
                    </div>
                </x-filament::card>
            </div>
        </div>

        {{-- TAB NAVIGATION --}}
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button
                        wire:click="setActiveTab('overview')"
                        class="{{ $activeTab === 'overview' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                    >
                        Overview
                    </button>
                    <button
                        wire:click="setActiveTab('services')"
                        class="{{ $activeTab === 'services' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                    >
                        Services
                    </button>
                    <button
                        wire:click="setActiveTab('history')"
                        class="{{ $activeTab === 'history' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                    >
                        History
                    </button>
                </nav>
            </div>
        </div>

        {{-- TAB CONTENT --}}
        <div>
            @if($activeTab === 'overview')
                @include('filament.pages.client-profile.tabs.overview')
            @elseif($activeTab === 'services')
                @include('filament.pages.client-profile.tabs.services')
            @elseif($activeTab === 'history')
                @include('filament.pages.client-profile.tabs.history')
            @endif
        </div>

    @else
        {{-- NO CLIENT SELECTED --}}
        <div class="flex items-center justify-center h-96">
            <div class="text-center">
                <x-heroicon-o-user-circle class="mx-auto h-12 w-12 text-gray-400"/>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">No client selected</h3>
                <p class="mt-1 text-sm text-gray-500">Access this page from the Service Point Dashboard</p>
            </div>
        </div>
    @endif
</x-filament-panels::page>