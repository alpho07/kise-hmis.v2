<x-filament-panels::page>

<style>
/* ── KISE Intake Editor ─────────────────────────────────────────────────── */
.kie-card        { background:#fff; border:1px solid #E4E6E4; border-radius:14px;
                   box-shadow:0 4px 16px rgba(0,0,0,.08), 0 1px 3px rgba(0,0,0,.05); }
.kie-header-bar  { background:#282F3B; border-radius:14px 14px 0 0; }
.kie-progress    { height:4px; background:#E4E6E4; }
.kie-progress-fill { height:4px; background:#29972E; border-radius:0 2px 2px 0; transition:width .5s ease; }
.kie-section-btn { width:100%; text-align:left; padding:10px 12px; display:flex; align-items:center; gap:10px;
                   border-left:3px solid transparent; transition:background .12s; cursor:pointer;
                   background:none; border-top:none; border-right:none; border-bottom:none; }
.kie-section-btn:hover { background:#f3f4f6; }
.kie-section-btn.active { background:#000; border-left-color:#FFC105; }
.kie-section-btn.active .kie-sec-label { color:#fff; font-weight:700; }
.kie-section-btn.done    { border-left-color:#29972E; }
.kie-section-btn.partial { border-left-color:#FFC105; }
.kie-dot { width:22px; height:22px; border-radius:50%; flex-shrink:0;
           display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; }
.kie-dot-done    { background:#29972E; color:#fff; }
.kie-dot-active  { background:#FFC105; color:#282F3B; }
.kie-dot-partial { background:#FFC105; color:#282F3B; }
.kie-dot-empty   { background:#E4E6E4; color:#9ca3af; }
.kie-sec-label   { font-size:.78rem; color:#374151; line-height:1.3; }
.kie-sec-label .sec-key { font-weight:800; color:#282F3B; }
.kie-section-btn.active .sec-key { color:#FFC105; }

.kie-section-header { padding:16px 24px; border-bottom:1px solid #E4E6E4;
                       display:flex; align-items:center; justify-content:space-between;
                       background:linear-gradient(to right, #f9fafb, #fff); }
.kie-section-icon  { width:36px; height:36px; border-radius:8px; background:#29972E;
                     display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.kie-section-icon svg { color:#fff !important; width:18px; height:18px; }
.kie-save-btn { display:inline-flex; align-items:center; gap:5px; font-size:.72rem;
                font-weight:600; padding:5px 14px; border-radius:20px; border:1.5px solid #29972E;
                background:#f0fdf4; color:#29972E; cursor:pointer; transition:background .12s; }
.kie-save-btn:hover { background:#dcfce7; }
.kie-saving-pill  { display:inline-flex; align-items:center; gap:5px; font-size:.72rem;
                    font-weight:600; padding:5px 12px; border-radius:20px;
                    background:#fef9c3; color:#713f12; border:1.5px solid #fef08a; }
.kie-saved-pill   { display:inline-flex; align-items:center; gap:5px; font-size:.72rem;
                    font-weight:600; padding:5px 12px; border-radius:20px;
                    background:#f0fdf4; color:#166534; border:1.5px solid #bbf7d0; }

.kie-info-tile { border-radius:10px; padding:14px; border:1px solid; }
/* ── Section content transition ─────────────────────────────── */
.kie-section-body { animation: kieFadeIn .22s ease; }
@keyframes kieFadeIn {
    from { opacity:0; transform:translateY(8px); }
    to   { opacity:1; transform:translateY(0); }
}

.kie-footer { padding:12px 24px; border-top:1px solid #E4E6E4;
              display:flex; align-items:center; justify-content:space-between;
              background:#fafafa; border-radius:0 0 12px 12px; }
.kie-pdot { border-radius:9999px; transition:all .2s; }
</style>

{{-- ═══ CLIENT HEADER ═══════════════════════════════════════════════════════ --}}
@php
    $gender   = $this->client?->gender ?? 'other';
    $initials = strtoupper(substr($this->client?->first_name ?? '?', 0, 1))
              . strtoupper(substr($this->client?->last_name  ?? '',  0, 1));
    $avatarBg = match($gender) { 'female' => '#ec4899', 'male' => '#3b82f6', default => '#8b5cf6' };
    $visitType  = $this->intake?->visit?->visit_type ?? 'new';
    $visitBadge = match($visitType) {
        'emergency' => ['background:#fee2e2;color:#b91c1c;border:1px solid #fecaca', 'Emergency'],
        'urgent'    => ['background:#ffedd5;color:#c2410c;border:1px solid #fed7aa', 'Urgent'],
        'follow_up' => ['background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe', 'Follow-up'],
        default     => ['background:#dcfce7;color:#15803d;border:1px solid #bbf7d0', 'New Visit'],
    };
    $shaNumber = $this->client?->sha_number;
    $pct = round(($this->progress / 12) * 100);
@endphp

<div class="sticky top-0 z-20 mb-5">
    <div class="kie-card overflow-hidden">

        {{-- Dark header bar --}}
        <div class="kie-header-bar px-5 py-3 flex items-center gap-4">

            {{-- Avatar --}}
            <div style="width:44px;height:44px;border-radius:50%;background:{{ $avatarBg }};
                        display:flex;align-items:center;justify-content:center;color:#fff;
                        font-weight:800;font-size:15px;flex-shrink:0;
                        box-shadow:0 0 0 3px rgba(255,255,255,.15);">
                {{ $initials }}
            </div>

            {{-- Name + meta --}}
            <div style="flex:1;min-width:0;">
                <div style="font-weight:800;font-size:.95rem;color:#ffffff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $this->client?->full_name ?? '—' }}
                </div>
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:4px;">
                    <span style="font-family:monospace;font-size:.7rem;background:rgba(255,255,255,.12);
                                 color:#E4E6E4;padding:2px 7px;border-radius:4px;border:1px solid rgba(255,255,255,.15);">
                        {{ $this->client?->uci ?? '—' }}
                    </span>
                    <span style="font-size:.7rem;background:rgba(255,255,255,.08);color:#d1d5db;padding:2px 7px;border-radius:4px;">
                        {{ $this->intake?->visit?->visit_number ?? '—' }}
                    </span>
                    @if($this->client?->date_of_birth)
                        <span style="font-size:.7rem;color:#9ca3af;">
                            {{ $this->client->date_of_birth->format('d M Y') }} &middot; {{ $this->client->age }} yrs
                        </span>
                    @endif
                    <span style="font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:20px;{{ $visitBadge[0] }}">
                        {{ $visitBadge[1] }}
                    </span>
                    @if($shaNumber)
                        <span style="font-size:.68rem;font-weight:700;background:rgba(20,184,166,.15);color:#5eead4;
                                     border:1px solid rgba(20,184,166,.25);padding:2px 8px;border-radius:20px;
                                     display:inline-flex;align-items:center;gap:3px;">
                            <x-heroicon-s-shield-check style="width:10px;height:10px;"/>SHA
                        </span>
                    @endif
                </div>
            </div>

            {{-- Progress counter + Export --}}
            <div style="flex-shrink:0;text-align:right;">
                <div style="font-size:1.6rem;font-weight:900;color:#FFC105;line-height:1;font-variant-numeric:tabular-nums;">
                    {{ $this->progress }}<span style="font-size:.8rem;font-weight:400;color:#6b7280;">/12</span>
                </div>
                <div style="font-size:.6rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin-top:2px;">
                    sections done
                </div>
                <a href="{{ route('intake-assessments.report', $this->intakeId) }}"
                   target="_blank"
                   style="margin-top:7px;display:inline-flex;align-items:center;gap:5px;
                          font-size:.67rem;font-weight:700;padding:4px 11px;border-radius:20px;
                          background:rgba(255,193,5,.15);color:#FFC105;
                          border:1.5px solid rgba(255,193,5,.4);text-decoration:none;
                          letter-spacing:.02em;white-space:nowrap;cursor:pointer;
                          transition:background .12s;"
                   title="Open full assessment report as PDF in a new tab">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width:11px;height:11px;" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    Export PDF
                </a>
            </div>
        </div>

        {{-- Progress bar --}}
        <div class="kie-progress">
            <div class="kie-progress-fill" style="width:{{ $pct }}%"></div>
        </div>

    </div>
</div>

{{-- ═══ MAIN LAYOUT ══════════════════════════════════════════════════════════ --}}
<div class="flex gap-8 items-start">

    {{-- ─── SECTION SIDEBAR ──────────────────────────────────────────────────── --}}
    <div style="width:210px;flex-shrink:0;" class="sticky top-24">
        <div class="kie-card overflow-hidden">
            <div style="padding:8px 12px 7px;border-bottom:1px solid #E4E6E4;background:#f9fafb;">
                <p style="font-size:.6rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#6b7280;">
                    Assessment Sections
                </p>
            </div>

            @foreach($this->sectionLabel as $key => $label)
                @php
                    $status   = $sectionStatus[$key] ?? 'incomplete';
                    $isActive = $activeSection === $key;
                    $btnClass = 'kie-section-btn'
                        . ($isActive              ? ' active'  : '')
                        . ($status === 'complete' ? ' done'    : '')
                        . ($status === 'in_progress' && !$isActive ? ' partial' : '');
                    $dotClass = $isActive
                        ? 'kie-dot kie-dot-active'
                        : ($status === 'complete'    ? 'kie-dot kie-dot-done'
                        : ($status === 'in_progress' ? 'kie-dot kie-dot-partial'
                        :                              'kie-dot kie-dot-empty'));
                    $dotIcon = $status === 'complete' ? '✓'
                             : ($isActive             ? $key
                             : ($status === 'in_progress' ? '…' : $key));
                @endphp
                <button wire:click="switchSection('{{ $key }}')"
                        wire:loading.class="opacity-60 pointer-events-none"
                        class="{{ $btnClass }}">
                    <span class="{{ $dotClass }}">{{ $dotIcon }}</span>
                    <span class="kie-sec-label">
                        <span class="sec-key">{{ $key }}.</span> {{ $label }}
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- ─── CONTENT PANEL ─────────────────────────────────────────────────────── --}}
    <div style="flex:1;min-width:0;">
        <div class="kie-card overflow-hidden">

            {{-- Section title bar --}}
            @php
                $meta         = $this->sectionMeta[$activeSection] ?? [];
                $icon         = $meta['icon'] ?? 'document-text';
                $desc         = $meta['description'] ?? '';
                $currentLabel = $this->sectionLabel[$activeSection] ?? $activeSection;
            @endphp
            <div class="kie-section-header">
                <div style="display:flex;align-items:flex-start;gap:12px;">
                    <div class="kie-section-icon">
                        <x-dynamic-component :component="'heroicon-o-' . $icon" style="width:18px;height:18px;color:#fff;"/>
                    </div>
                    <div>
                        <h2 style="font-size:.88rem;font-weight:800;color:#282F3B;line-height:1.25;">
                            Section {{ $activeSection }} &mdash; {{ $currentLabel }}
                        </h2>
                        @if($desc)
                            <p style="font-size:.75rem;color:#6b7280;margin-top:3px;line-height:1.5;">{{ $desc }}</p>
                        @endif
                    </div>
                </div>

                {{-- Save controls --}}
                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                    @if($activeSection !== 'A')
                        <button type="button"
                                wire:click="saveSectionData('{{ $activeSection }}', true)"
                                wire:loading.attr="disabled"
                                wire:target="saveSectionData"
                                class="kie-save-btn">
                            <x-heroicon-o-arrow-down-tray style="width:12px;height:12px;"/>
                            Save
                        </button>
                    @endif
                    @if($isSaving)
                        <span class="kie-saving-pill">
                            <x-filament::loading-indicator style="width:12px;height:12px;"/>
                            Saving…
                        </span>
                    @else
                        <span class="kie-saved-pill">
                            <svg style="width:12px;height:12px;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Saved
                        </span>
                    @endif
                </div>
            </div>

            {{-- Section body --}}
            <div style="padding:24px;" class="kie-section-body" wire:key="section-body-{{ $activeSection }}">

                @if($activeSection === 'A')
                    {{-- Section A: read-only client overview --}}
                    @php
                        $dobDisplay = $this->client?->date_of_birth
                            ? $this->client->date_of_birth->format('d M Y') . ' (' . $this->client->age . ' yrs)'
                            : '—';
                        $tiles = [
                            ['label'=>'Full Name',     'icon'=>'user',              'value'=>$this->client?->full_name,                       'bg'=>'#f0fdf4','border'=>'#bbf7d0','ic'=>'#29972E'],
                            ['label'=>'UCI',           'icon'=>'identification',     'value'=>$this->client?->uci ?? '—',                      'bg'=>'#fefce8','border'=>'#fef08a','ic'=>'#d97706','mono'=>true],
                            ['label'=>'Date of Birth', 'icon'=>'calendar',           'value'=>$dobDisplay,                                     'bg'=>'#f5f3ff','border'=>'#ddd6fe','ic'=>'#7c3aed'],
                            ['label'=>'Gender',        'icon'=>'user-circle',        'value'=>ucfirst($this->client?->gender ?? '—'),          'bg'=>'#fdf2f8','border'=>'#f9a8d4','ic'=>'#db2777'],
                            ['label'=>'County',        'icon'=>'map-pin',            'value'=>$this->client?->county?->name ?? '—',            'bg'=>'#f0fdf4','border'=>'#bbf7d0','ic'=>'#29972E'],
                            ['label'=>'Visit No.',     'icon'=>'clipboard-document', 'value'=>$this->intake?->visit?->visit_number ?? '—',     'bg'=>'#eff6ff','border'=>'#bfdbfe','ic'=>'#2563eb','mono'=>true],
                            ['label'=>'SHA Number',    'icon'=>'shield-check',       'value'=>$this->client?->sha_number ?? 'Not registered',  'bg'=>'#f0fdfa','border'=>'#99f6e4','ic'=>'#0d9488'],
                        ];
                    @endphp
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                        @foreach($tiles as $t)
                            <div class="kie-info-tile" style="background:{{ $t['bg'] }};border-color:{{ $t['border'] }};">
                                <div style="display:flex;align-items:center;gap:5px;margin-bottom:6px;">
                                    <x-dynamic-component :component="'heroicon-o-'.$t['icon']"
                                        style="width:13px;height:13px;flex-shrink:0;color:{{ $t['ic'] }};"/>
                                    <span style="font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:{{ $t['ic'] }};">
                                        {{ $t['label'] }}
                                    </span>
                                </div>
                                <div style="font-size:.85rem;font-weight:700;color:#282F3B;
                                            {{ ($t['mono'] ?? false) ? 'font-family:monospace;' : '' }}
                                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    {{ $t['value'] ?? '—' }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div style="margin-top:16px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;
                                padding:12px 14px;display:flex;align-items:flex-start;gap:10px;">
                        <x-heroicon-o-information-circle style="width:15px;height:15px;color:#2563eb;flex-shrink:0;margin-top:1px;"/>
                        <p style="font-size:.78rem;color:#1d4ed8;line-height:1.55;">
                            Section A is auto-completed from the client record and is read-only.
                            Use <strong>Section B — ID &amp; Contact</strong> to update contact details, SHA number, or address.
                        </p>
                    </div>

                @else
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
                                $incomplete  = array_keys(array_filter($sectionStatus, fn($s) => $s !== 'complete'));
                                $allComplete = empty($incomplete);
                            @endphp

                            <div style="margin-top:24px;padding-top:20px;border-top:1px solid #E4E6E4;">
                                @if(!$allComplete)
                                    <div style="margin-bottom:16px;border-radius:8px;background:#fefce8;
                                                border:1px solid #fef08a;padding:12px 16px;
                                                display:flex;align-items:flex-start;gap:10px;">
                                        <x-heroicon-o-exclamation-triangle style="width:15px;height:15px;color:#d97706;flex-shrink:0;margin-top:1px;"/>
                                        <p style="font-size:.78rem;color:#92400e;line-height:1.5;">
                                            <strong>Cannot finalize yet.</strong>
                                            Still incomplete: <span style="font-weight:700;color:#d97706;">{{ implode(', ', $incomplete) }}</span>
                                        </p>
                                    </div>
                                @else
                                    <div style="margin-bottom:16px;border-radius:8px;background:#f0fdf4;
                                                border:1px solid #bbf7d0;padding:12px 16px;
                                                display:flex;align-items:center;gap:10px;">
                                        <x-heroicon-o-check-circle style="width:15px;height:15px;color:#29972E;flex-shrink:0;"/>
                                        <p style="font-size:.78rem;font-weight:600;color:#166534;">
                                            All 12 sections complete — ready to finalize.
                                        </p>
                                    </div>
                                @endif

                                <div style="display:flex;justify-content:flex-end;">
                                    <button
                                        type="button"
                                        wire:click="finalize"
                                        wire:loading.attr="disabled"
                                        wire:target="finalize"
                                        style="{{ $allComplete
                                            ? 'background:#29972E;color:#fff;border:none;cursor:pointer;box-shadow:0 2px 8px rgba(41,151,46,.3);'
                                            : 'background:#E4E6E4;color:#9ca3af;border:none;cursor:not-allowed;' }}
                                            display:inline-flex;align-items:center;gap:8px;
                                            padding:10px 24px;border-radius:8px;font-size:.85rem;
                                            font-weight:700;letter-spacing:.01em;transition:background .15s;"
                                        @disabled(!$allComplete)
                                    >
                                        <span wire:loading wire:target="finalize">
                                            <x-filament::loading-indicator style="width:16px;height:16px;"/>
                                        </span>
                                        <x-heroicon-o-check-circle wire:loading.remove wire:target="finalize" style="width:16px;height:16px;"/>
                                        Finalize Assessment
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Footer navigation --}}
            <div class="kie-footer">

                <x-filament::button
                    wire:click="prevSection"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-chevron-left"
                    :disabled="$activeSection === 'A'"
                >
                    Previous
                </x-filament::button>

                {{-- Section progress dots --}}
                <div style="display:flex;align-items:center;gap:4px;">
                    @foreach(array_keys($this->sectionLabel) as $key)
                        @php $s = $sectionStatus[$key] ?? 'incomplete'; @endphp
                        <div class="kie-pdot" style="
                            {{ $activeSection === $key
                                ? 'width:16px;height:8px;background:#29972E;'
                                : ($s === 'complete'
                                    ? 'width:8px;height:8px;background:#29972E;'
                                    : ($s === 'in_progress'
                                        ? 'width:8px;height:8px;background:#FFC105;'
                                        : 'width:8px;height:8px;background:#E4E6E4;')) }}">
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
