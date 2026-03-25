<x-filament-panels::page>
    {{-- Client Header --}}
    <div class="rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 p-4 text-white mb-4 shadow">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-white/20 flex items-center justify-center text-xl font-bold flex-shrink-0">
                {{ strtoupper(substr($this->client?->first_name ?? '?', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-lg font-bold truncate">{{ $this->client?->full_name }}</div>
                <div class="text-sm text-indigo-200">
                    UCI: {{ $this->client?->uci ?? '—' }} &nbsp;·&nbsp;
                    Visit: {{ $this->intake?->visit?->visit_number ?? '—' }}
                </div>
            </div>
            <div class="text-right flex-shrink-0">
                <div class="text-2xl font-bold">{{ $this->progress }}<span class="text-base font-normal">/12</span></div>
                <div class="text-xs text-indigo-200">complete</div>
            </div>
        </div>
        <div class="mt-3 h-1.5 bg-white/20 rounded-full overflow-hidden">
            <div class="h-full bg-white rounded-full transition-all duration-500"
                 style="width: {{ ($this->progress / 12) * 100 }}%"></div>
        </div>
    </div>

    {{-- Main Layout --}}
    <div class="flex gap-4" style="min-height: 70vh;">

        {{-- Sidebar --}}
        <div class="w-44 flex-shrink-0">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden sticky top-4">
                @foreach($this->sectionLabel as $key => $label)
                    @php $status = $sectionStatus[$key] ?? 'incomplete'; @endphp
                    <button wire:click="switchSection('{{ $key }}')"
                        class="w-full text-left px-3 py-2.5 flex items-center gap-1.5 text-xs transition-colors
                            {{ $activeSection === $key
                                ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold border-l-4 border-indigo-600'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 border-l-4 border-transparent' }}">
                        @if($status === 'complete')
                            <span class="text-green-500 text-xs w-3">✓</span>
                        @elseif($activeSection === $key)
                            <span class="text-indigo-500 text-xs w-3">▶</span>
                        @else
                            <span class="text-gray-300 text-xs w-3">○</span>
                        @endif
                        <span class="truncate">{{ $key }}. {{ $label }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Content Area --}}
        <div class="flex-1 min-w-0">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">

                {{-- Section header --}}
                <div class="flex items-center justify-between px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Section {{ $activeSection }} — {{ $this->sectionLabel[$activeSection] }}
                    </h2>
                    <span class="text-xs {{ $isSaving ? 'text-amber-500' : 'text-green-600' }} flex items-center gap-1">
                        @if($isSaving)
                            <x-filament::loading-indicator class="h-3 w-3"/> Saving...
                        @else
                            Saved ✓
                        @endif
                    </span>
                </div>

                {{-- Section content with Alpine autosave capture --}}
                <div class="p-6">

                    @if($activeSection === 'A')
                        {{-- Section A: read-only client display --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            @foreach([
                                'Full Name'     => $this->client?->full_name,
                                'UCI'           => $this->client?->uci,
                                'Date of Birth' => $this->client?->date_of_birth?->format('d M Y'),
                                'Gender'        => ucfirst($this->client?->gender ?? '—'),
                                'County'        => $this->client?->county?->name ?? '—',
                                'Visit Number'  => $this->intake?->visit?->visit_number ?? '—',
                            ] as $label => $value)
                                <div class="flex gap-2">
                                    <span class="text-gray-500 w-28 flex-shrink-0">{{ $label }}:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $value ?? '—' }}</span>
                                </div>
                            @endforeach
                        </div>

                    @else
                        {{--
                            Alpine.js captures blur and change events from ALL child inputs.
                            Debounce: 1000ms after last event fires $wire.saveSectionData(section).
                            This works regardless of Filament's internal form wiring.
                        --}}
                        <div
                            x-data="{ saveTimer: null, section: $wire.entangle('activeSection') }"
                            @blur.capture="clearTimeout(saveTimer); saveTimer = setTimeout(() => $wire.saveSectionData(section), 1000)"
                            @change.capture="clearTimeout(saveTimer); saveTimer = setTimeout(() => $wire.saveSectionData(section), 1000)"
                        >
                            @if($activeSection === 'B') {{ $this->sectionBForm }}
                            @elseif($activeSection === 'C') {{ $this->sectionCForm }}
                            @elseif($activeSection === 'D') {{ $this->sectionDForm }}
                            @elseif($activeSection === 'E') {{ $this->sectionEForm }}
                            @elseif($activeSection === 'F') {{ $this->sectionFForm }}
                            @elseif($activeSection === 'G') {{ $this->sectionGForm }}
                            @elseif($activeSection === 'H') {{ $this->sectionHForm }}
                            @elseif($activeSection === 'I') {{ $this->sectionIForm }}
                            @elseif($activeSection === 'J') {{ $this->sectionJForm }}
                            @elseif($activeSection === 'K') {{ $this->sectionKForm }}
                            @elseif($activeSection === 'L')
                                {{ $this->sectionLForm }}
                                @php
                                    $allComplete = !in_array('incomplete', $sectionStatus)
                                               && !in_array('in_progress', $sectionStatus);
                                @endphp
                                <div class="mt-6 flex justify-end">
                                    <button
                                        type="button"
                                        wire:click="finalize"
                                        wire:loading.attr="disabled"
                                        wire:target="finalize"
                                        @class([
                                            'inline-flex items-center gap-2 px-6 py-3 rounded-lg text-sm font-semibold transition-all',
                                            'bg-green-600 hover:bg-green-700 text-white cursor-pointer' => $allComplete,
                                            'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed' => !$allComplete,
                                        ])
                                        @disabled(! $allComplete)
                                    >
                                        <span wire:loading wire:target="finalize">
                                            <x-filament::loading-indicator class="h-4 w-4"/>
                                        </span>
                                        Finalize Assessment →
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Footer nav --}}
                <div class="flex justify-between px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button wire:click="prevSection" color="gray" :disabled="$activeSection === 'A'">
                        ← Previous
                    </x-filament::button>
                    <x-filament::button wire:click="nextSection" color="primary" :disabled="$activeSection === 'L'">
                        Next →
                    </x-filament::button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
