<x-filament-panels::page>

    {{-- ═══ CLIENT HEADER CARD ════════════════════════════════════════════════ --}}
    @php
        $gender   = $this->client?->gender ?? 'other';
        $initials = strtoupper(substr($this->client?->first_name ?? '?', 0, 1))
                  . strtoupper(substr($this->client?->last_name  ?? '',  0, 1));
        $avatarColor = $gender === 'female'
            ? 'bg-pink-500'
            : ($gender === 'male' ? 'bg-blue-500' : 'bg-violet-500');
        $visitType  = $this->intake?->visit?->visit_type  ?? 'new';
        $visitBadge = match($visitType) {
            'emergency' => ['bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',   'Emergency'],
            'urgent'    => ['bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300', 'Urgent'],
            'follow_up' => ['bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300','Follow-up'],
            default     => ['bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300','New'],
        };
        $shaNumber = $this->client?->sha_number;
    @endphp

    <div class="sticky top-0 z-20 mb-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">

            {{-- Main row --}}
            <div class="px-5 py-3.5 flex items-center gap-4">

                {{-- Avatar --}}
                <div class="h-12 w-12 rounded-full {{ $avatarColor }} flex items-center justify-center text-white font-bold text-base flex-shrink-0 select-none shadow-sm ring-2 ring-white dark:ring-gray-700">
                    {{ $initials }}
                </div>

                {{-- Client info --}}
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-gray-900 dark:text-white text-sm leading-snug truncate">
                        {{ $this->client?->full_name ?? '—' }}
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                        <span class="inline-flex items-center text-[11px] font-mono bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-700 px-1.5 py-0.5 rounded">
                            {{ $this->client?->uci ?? '—' }}
                        </span>
                        <span class="inline-flex items-center text-[11px] bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded">
                            {{ $this->intake?->visit?->visit_number ?? '—' }}
                        </span>
                        @if($this->client?->date_of_birth)
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                {{ $this->client->date_of_birth->format('d M Y') }}
                                &nbsp;·&nbsp;{{ $this->client->age }} yrs
                            </span>
                        @endif
                        <span class="inline-flex items-center text-[11px] font-medium {{ $visitBadge[0] }} px-1.5 py-0.5 rounded">
                            {{ $visitBadge[1] }}
                        </span>
                        @if($shaNumber)
                            <span class="inline-flex items-center gap-1 text-[11px] bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-700 px-1.5 py-0.5 rounded">
                                <x-heroicon-s-shield-check class="w-3 h-3"/>SHA
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Progress ring / counter --}}
                <div class="flex-shrink-0 flex flex-col items-end gap-0.5">
                    <div class="text-2xl font-extrabold tabular-nums leading-none text-gray-900 dark:text-white">
                        {{ $this->progress }}<span class="text-sm font-normal text-gray-400 dark:text-gray-500">/12</span>
                    </div>
                    <div class="text-[10px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">sections done</div>
                </div>

            </div>

            {{-- Progress bar --}}
            <div class="h-1.5 bg-gray-100 dark:bg-gray-700">
                <div class="h-full bg-indigo-500 dark:bg-indigo-400 transition-all duration-500 ease-out rounded-r-sm"
                     style="width: {{ ($this->progress / 12) * 100 }}%"></div>
            </div>

        </div>
    </div>

    {{-- ═══ MAIN LAYOUT ══════════════════════════════════════════════════════════ --}}
    <div class="flex gap-4 items-start">

        {{-- ─── SIDEBAR ────────────────────────────────────────────────────────── --}}
        <div class="w-52 flex-shrink-0 sticky top-24">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

                <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                    <p class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Sections</p>
                </div>

                @foreach($this->sectionLabel as $key => $label)
                    @php
                        $status   = $sectionStatus[$key] ?? 'incomplete';
                        $isActive = $activeSection === $key;
                    @endphp
                    <button
                        wire:click="switchSection('{{ $key }}')"
                        wire:loading.class="opacity-60 pointer-events-none"
                        class="w-full text-left px-3 py-2.5 flex items-center gap-2.5 transition-colors border-l-[3px]
                            {{ $isActive
                                ? 'bg-indigo-50 dark:bg-indigo-900/25 border-indigo-500'
                                : ($status === 'complete'
                                    ? 'border-green-400 hover:bg-gray-50 dark:hover:bg-gray-750'
                                    : ($status === 'in_progress'
                                        ? 'border-amber-300 hover:bg-gray-50 dark:hover:bg-gray-750'
                                        : 'border-transparent hover:bg-gray-50 dark:hover:bg-gray-750')) }}"
                    >
                        {{-- Status dot --}}
                        @if($status === 'complete')
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-500 text-white flex-shrink-0" style="font-size:9px;">✓</span>
                        @elseif($isActive)
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-white flex-shrink-0" style="font-size:9px;">▶</span>
                        @elseif($status === 'in_progress')
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-400 text-white flex-shrink-0" style="font-size:9px;">…</span>
                        @else
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-600 flex-shrink-0" style="font-size:9px;">○</span>
                        @endif

                        {{-- Text --}}
                        <span class="text-xs leading-tight truncate
                            {{ $isActive
                                ? 'font-semibold text-indigo-700 dark:text-indigo-300'
                                : 'text-gray-600 dark:text-gray-400' }}">
                            <span class="font-bold">{{ $key }}.</span> {{ $label }}
                        </span>
                    </button>
                @endforeach

            </div>
        </div>

        {{-- ─── CONTENT PANEL ──────────────────────────────────────────────────── --}}
        <div class="flex-1 min-w-0">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

                {{-- Section title bar --}}
                @php
                    $meta = $this->sectionMeta[$activeSection] ?? [];
                    $icon = $meta['icon'] ?? 'document-text';
                    $desc = $meta['description'] ?? '';
                    $currentLabel = $this->sectionLabel[$activeSection] ?? $activeSection;
                @endphp
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/20">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 mt-0.5">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center">
                                <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4.5 h-4.5 text-indigo-600 dark:text-indigo-400" />
                            </div>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">
                                Section {{ $activeSection }} — {{ $currentLabel }}
                            </h2>
                            @if($desc)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">{{ $desc }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Save controls --}}
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($activeSection !== 'A')
                            <button
                                type="button"
                                wire:click="saveSectionData('{{ $activeSection }}', true)"
                                wire:loading.attr="disabled"
                                wire:target="saveSectionData"
                                class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 px-2.5 py-1 rounded-full transition-colors"
                            >
                                <x-heroicon-o-arrow-down-tray class="w-3 h-3"/>
                                Save
                            </button>
                        @endif
                        @if($isSaving)
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-600 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-2.5 py-1 rounded-full">
                                <x-filament::loading-indicator class="h-3 w-3"/>
                                Saving…
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-2.5 py-1 rounded-full">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Saved
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Section body --}}
                <div class="p-6">

                    @if($activeSection === 'A')
                        {{-- ── Section A: Read-only client overview ── --}}
                        @php
                            $dobDisplay = $this->client?->date_of_birth
                                ? $this->client->date_of_birth->format('d M Y') . ' (' . $this->client->age . ' yrs)'
                                : '—';
                        @endphp
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach([
                                ['label' => 'Full Name',     'icon' => 'user',             'value' => $this->client?->full_name,                         'accent' => 'indigo'],
                                ['label' => 'UCI',           'icon' => 'identification',    'value' => $this->client?->uci ?? '—',                        'accent' => 'indigo', 'mono' => true],
                                ['label' => 'Date of Birth', 'icon' => 'calendar',          'value' => $dobDisplay,                                       'accent' => 'violet'],
                                ['label' => 'Gender',        'icon' => 'user-circle',       'value' => ucfirst($this->client?->gender ?? '—'),            'accent' => 'pink'],
                                ['label' => 'County',        'icon' => 'map-pin',           'value' => $this->client?->county?->name ?? '—',              'accent' => 'emerald'],
                                ['label' => 'Visit No.',     'icon' => 'clipboard-document','value' => $this->intake?->visit?->visit_number ?? '—',       'accent' => 'blue', 'mono' => true],
                                ['label' => 'SHA Number',    'icon' => 'shield-check',      'value' => $this->client?->sha_number ?? 'Not registered',    'accent' => 'teal'],
                            ] as $info)
                                @php
                                    $accentMap = [
                                        'indigo'  => 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-100 dark:border-indigo-800 text-indigo-500',
                                        'violet'  => 'bg-violet-50 dark:bg-violet-900/20 border-violet-100 dark:border-violet-800 text-violet-500',
                                        'pink'    => 'bg-pink-50 dark:bg-pink-900/20 border-pink-100 dark:border-pink-800 text-pink-500',
                                        'emerald' => 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-100 dark:border-emerald-800 text-emerald-500',
                                        'blue'    => 'bg-blue-50 dark:bg-blue-900/20 border-blue-100 dark:border-blue-800 text-blue-500',
                                        'teal'    => 'bg-teal-50 dark:bg-teal-900/20 border-teal-100 dark:border-teal-800 text-teal-500',
                                    ];
                                    $accentClass = $accentMap[$info['accent']] ?? $accentMap['indigo'];
                                @endphp
                                <div class="rounded-xl {{ $accentClass }} border p-3 shadow-sm">
                                    <div class="flex items-center gap-1.5 mb-1.5">
                                        <x-dynamic-component :component="'heroicon-o-' . $info['icon']" class="w-3.5 h-3.5 flex-shrink-0"/>
                                        <span class="text-[10px] font-bold uppercase tracking-wide opacity-70">{{ $info['label'] }}</span>
                                    </div>
                                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate {{ ($info['mono'] ?? false) ? 'font-mono' : '' }}">
                                        {{ $info['value'] ?? '—' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 flex items-start gap-2.5">
                            <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0"/>
                            <p class="text-xs text-blue-700 dark:text-blue-300 leading-relaxed">
                                Section A is auto-completed from the client record and is read-only. Use <strong>Section B — ID &amp; Contact</strong> to update contact details, SHA number, or address.
                            </p>
                        </div>

                    @else
                        {{--
                            Autosave wrapper.
                            @blur.capture  → fires when user leaves any text/textarea input.
                            @change.capture is intentionally omitted: Filament fields with ->live()
                              (toggles, radios, selects) trigger their own Livewire round-trips for
                              reactive visibility. Injecting a competing saveSectionData call from
                              @change.capture races with those round-trips and breaks conditional fields.
                              All section data is saved automatically on section navigation.
                        --}}
                        <div
                            x-data="{ saveTimer: null, section: $wire.entangle('activeSection') }"
                            @blur.capture="
                                const tag = $event.target.tagName;
                                if (tag === 'INPUT' || tag === 'TEXTAREA') {
                                    clearTimeout(saveTimer);
                                    saveTimer = setTimeout(() => $wire.saveSectionData(section), 1200);
                                }
                            "
                        >
                            @if($activeSection === 'B')      {{ $this->sectionBForm }}
                            @elseif($activeSection === 'C')  {{ $this->sectionCForm }}
                            @elseif($activeSection === 'D')  {{ $this->sectionDForm }}
                            @elseif($activeSection === 'E')  {{ $this->sectionEForm }}
                            @elseif($activeSection === 'F')  {{ $this->sectionFForm }}
                            @elseif($activeSection === 'G')  {{ $this->sectionGForm }}
                            @elseif($activeSection === 'H')  {{ $this->sectionHForm }}
                            @elseif($activeSection === 'I')  {{ $this->sectionIForm }}
                            @elseif($activeSection === 'J')  {{ $this->sectionJForm }}
                            @elseif($activeSection === 'K')  {{ $this->sectionKForm }}
                            @elseif($activeSection === 'L')
                                {{ $this->sectionLForm }}

                                @php
                                    $incomplete = array_keys(array_filter($sectionStatus, fn($s) => $s !== 'complete'));
                                    $allComplete = empty($incomplete);
                                @endphp

                                <div class="mt-6 pt-5 border-t border-gray-200 dark:border-gray-700">
                                    @if(!$allComplete)
                                        <div class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 flex items-start gap-2">
                                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0"/>
                                            <p class="text-xs text-amber-700 dark:text-amber-300">
                                                <strong>Cannot finalize yet.</strong>
                                                Still incomplete: <span class="font-semibold">{{ implode(', ', $incomplete) }}</span>
                                            </p>
                                        </div>
                                    @else
                                        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 flex items-center gap-2">
                                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 flex-shrink-0"/>
                                            <p class="text-xs font-medium text-green-700 dark:text-green-300">All 12 sections complete — ready to finalize.</p>
                                        </div>
                                    @endif

                                    <div class="flex justify-end">
                                        <button
                                            type="button"
                                            wire:click="finalize"
                                            wire:loading.attr="disabled"
                                            wire:target="finalize"
                                            @class([
                                                'inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-offset-2',
                                                'fi-btn fi-btn-color-primary fi-color-primary bg-primary-600 hover:bg-primary-500 text-white shadow-sm focus:ring-primary-500' => $allComplete,
                                                'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed' => !$allComplete,
                                            ])
                                            @disabled(!$allComplete)
                                        >
                                            <span wire:loading wire:target="finalize">
                                                <x-filament::loading-indicator class="h-4 w-4"/>
                                            </span>
                                            <x-heroicon-o-check-circle wire:loading.remove wire:target="finalize" class="h-4 w-4"/>
                                            Finalize Assessment
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                </div>

                {{-- Footer navigation --}}
                <div class="flex items-center justify-between px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/20">

                    <x-filament::button
                        wire:click="prevSection"
                        color="gray"
                        size="sm"
                        icon="heroicon-o-chevron-left"
                        :disabled="$activeSection === 'A'"
                    >
                        Previous
                    </x-filament::button>

                    {{-- Mini progress dots --}}
                    <div class="flex items-center gap-1">
                        @foreach(array_keys($this->sectionLabel) as $key)
                            @php $s = $sectionStatus[$key] ?? 'incomplete'; @endphp
                            <div class="rounded-full transition-all duration-200
                                {{ $activeSection === $key
                                    ? 'w-4 h-2 bg-indigo-600'
                                    : ($s === 'complete'
                                        ? 'w-2 h-2 bg-green-400'
                                        : ($s === 'in_progress'
                                            ? 'w-2 h-2 bg-amber-400'
                                            : 'w-2 h-2 bg-gray-200 dark:bg-gray-600')) }}">
                            </div>
                        @endforeach
                    </div>

                    <x-filament::button
                        wire:click="nextSection"
                        color="primary"
                        size="sm"
                        icon="heroicon-o-chevron-right"
                        icon-position="after"
                        :disabled="$activeSection === 'L'"
                    >
                        Next
                    </x-filament::button>

                </div>
            </div>
        </div>

    </div>

</x-filament-panels::page>
