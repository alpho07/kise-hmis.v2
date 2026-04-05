<x-filament-panels::page>

<style>
/* ── Specialist Hub Theme ── */
.sh-band {
    background: #282F3B;
    border-radius: 12px;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.sh-band-avatar {
    width: 46px; height: 46px; border-radius: 50%;
    background: #29972E;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; font-weight: 800; color: #fff; flex-shrink: 0;
}
.sh-band-name { font-size: 1rem; font-weight: 700; color: #fff; line-height: 1.2; }
.sh-band-sub  { font-size: 0.72rem; color: #9ca3af; margin-top: 2px; }
.sh-band-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto; align-items: center; }
.sh-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 0.7rem; font-weight: 600;
    background: rgba(255,255,255,.08); color: #e5e7eb;
    border: 1px solid rgba(255,255,255,.1);
}
.sh-pill.green  { background: rgba(41,151,46,.25);  color: #4ade80;  border-color: rgba(41,151,46,.3); }
.sh-pill.yellow { background: rgba(255,193,5,.2);   color: #fbbf24;  border-color: rgba(255,193,5,.25); }
.sh-pill.red    { background: rgba(239,68,68,.25);  color: #f87171;  border-color: rgba(239,68,68,.3); }

/* ── Layout ── */
.sh-grid { display: grid; grid-template-columns: 320px 1fr; gap: 16px; align-items: start; }
@media (max-width: 960px) { .sh-grid { grid-template-columns: 1fr; } }

/* ── Cards ── */
.sh-card {
    background: #fff; border-radius: 12px;
    border: 1px solid #e5e7eb; overflow: hidden;
    margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.sh-card-hdr {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 16px; background: #282F3B;
}
.sh-card-icon {
    width: 26px; height: 26px; border-radius: 7px;
    background: rgba(255,255,255,.1);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sh-card-icon svg { width: 14px; height: 14px; color: #d1d5db; }
.sh-card-title { font-size: 11px; font-weight: 700; color: #fff; letter-spacing: .05em; text-transform: uppercase; }
.sh-card-title-meta { font-size: 10px; color: #9ca3af; margin-left: auto; }
.sh-card-body { padding: 12px 16px; }

/* ── Alert bands ── */
.sh-alert {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 8px 12px; border-radius: 8px; margin-bottom: 8px;
    font-size: 11px; font-weight: 600;
}
.sh-alert.red    { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.sh-alert.orange { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
.sh-alert svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 1px; }

/* ── Info rows ── */
.sh-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 6px 0; border-bottom: 1px solid #f3f4f6; }
.sh-row:last-child { border-bottom: none; }
.sh-lbl { font-size: 11px; color: #6b7280; flex-shrink: 0; padding-right: 8px; min-width: 110px; }
.sh-val { font-size: 11px; font-weight: 600; color: #1e293b; text-align: right; }
.sh-val.long { font-weight: 400; text-align: left; color: #374151; }

/* ── Vitals grid ── */
.sh-vitals-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 4px 0 8px; }
.sh-vital-box {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 8px 10px; text-align: center;
}
.sh-vital-val  { font-size: 15px; font-weight: 700; color: #1e293b; line-height: 1.1; }
.sh-vital-lbl  { font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }

/* ── Section divider ── */
.sh-divider { font-size: 10px; font-weight: 700; color: #9ca3af; letter-spacing: .08em; text-transform: uppercase;
    padding: 10px 0 4px; border-bottom: 1px solid #f1f5f9; margin-bottom: 8px; }

/* ── Forms list ── */
.sh-service-group { margin-bottom: 18px; }
.sh-service-name {
    font-size: 12px; font-weight: 700; color: #282F3B;
    display: flex; align-items: center; gap: 8px;
    padding: 8px 0; border-bottom: 2px solid #29972E; margin-bottom: 10px;
}
.sh-dept-badge {
    font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 10px;
    background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;
}
.sh-form-card {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 12px; border-radius: 9px; border: 1px solid #e5e7eb;
    background: #fff; margin-bottom: 7px;
    transition: border-color .15s, box-shadow .15s;
}
.sh-form-card:hover { border-color: #29972E; box-shadow: 0 0 0 2px rgba(41,151,46,.08); }
.sh-form-card.done  { border-color: #bbf7d0; background: #f0fdf4; }
.sh-form-info { min-width: 0; }
.sh-form-name { font-size: 12px; font-weight: 600; color: #1e293b; }
.sh-form-meta { font-size: 10px; color: #9ca3af; margin-top: 1px; }
.sh-form-actions { display: flex; gap: 6px; flex-shrink: 0; margin-left: 10px; }
.sh-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 11px; border-radius: 7px;
    font-size: 11px; font-weight: 600; text-decoration: none;
    transition: background .15s;
}
.sh-btn-primary  { background: #282F3B; color: #fff; }
.sh-btn-primary:hover  { background: #29972E; color: #fff; }
.sh-btn-outline { background: #fff; color: #374151; border: 1px solid #d1d5db; }
.sh-btn-outline:hover { border-color: #282F3B; color: #282F3B; }
.sh-btn svg { width: 11px; height: 11px; }

/* ── History timeline ── */
.sh-history-item {
    border: 1px solid #e5e7eb; border-radius: 10px;
    overflow: hidden; margin-bottom: 10px;
}
.sh-history-hdr {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; background: #f8fafc;
    cursor: pointer; user-select: none;
}
.sh-history-hdr:hover { background: #f1f5f9; }
.sh-history-date  { font-size: 12px; font-weight: 700; color: #1e293b; }
.sh-history-visit { font-size: 10px; color: #9ca3af; margin-left: 4px; }
.sh-history-services { font-size: 11px; color: #64748b; margin-left: auto; text-align: right; }
.sh-status-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.sh-history-body { padding: 12px 16px; border-top: 1px solid #f1f5f9; }
.sh-sub-section { font-size: 10px; font-weight: 700; color: #64748b; letter-spacing: .06em; text-transform: uppercase;
    margin: 8px 0 4px; }
.sh-tag {
    display: inline-block; padding: 2px 8px; border-radius: 12px;
    font-size: 10px; font-weight: 600; margin: 2px 3px 2px 0;
    background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;
}
.sh-tag.done { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
.sh-tag.draft { background: #fffbeb; color: #92400e; border-color: #fde68a; }
.sh-no-history { text-align: center; padding: 28px; color: #9ca3af; font-size: 12px; }

/* ── Peds badge ── */
.sh-peds-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px;
    background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe;
    font-size: 10px; font-weight: 700;
}

/* ── No-content placeholder ── */
.sh-empty { text-align: center; padding: 24px 12px; color: #9ca3af; font-size: 11px; }
.sh-empty svg { width: 28px; height: 28px; margin: 0 auto 6px; display: block; color: #d1d5db; }
</style>

@if ($client)

{{-- ── CLIENT BAND ── --}}
<div class="sh-band">
    <div class="sh-band-avatar">{{ strtoupper(substr($client->full_name, 0, 1)) }}</div>
    <div>
        <div class="sh-band-name">{{ $client->full_name }}</div>
        <div class="sh-band-sub">{{ $clientSummary['UCI'] }}</div>
    </div>
    <div class="sh-band-pills">
        @if ($is_paediatric)
            <span class="sh-peds-badge">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="12" cy="12" r="9"/></svg>
                Paediatric
            </span>
        @endif
        <span class="sh-pill">{{ $clientSummary['Age'] }}</span>
        <span class="sh-pill">{{ $clientSummary['Gender'] }}</span>
        @if ($clientSummary['Queue #'] !== '—')<span class="sh-pill green">Queue {{ $clientSummary['Queue #'] }}</span>@endif
        @if ($clientSummary['Room'] !== '—')<span class="sh-pill yellow">Room {{ $clientSummary['Room'] }}</span>@endif
        @if ($triage_data['has_red_flags'] ?? false)<span class="sh-pill red">⚠ Red Flags</span>@endif
        @if ($triage_data['crisis_activated'] ?? false)<span class="sh-pill red">🚨 Crisis Protocol</span>@endif
    </div>
</div>

<div class="sh-grid">

{{-- ══════════════════════════════════
     LEFT COLUMN
════════════════════════════════════ --}}
<div>

    {{-- ── CLIENT INFO ── --}}
    <div class="sh-card">
        <div class="sh-card-hdr">
            <div class="sh-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
            </div>
            <span class="sh-card-title">Client</span>
        </div>
        <div class="sh-card-body">
            @foreach (['UCI' => $clientSummary['UCI'], 'DOB' => $client->date_of_birth?->format('M d, Y') ?? '—', 'Age' => $clientSummary['Age'], 'Gender' => $clientSummary['Gender'], 'Phone' => $client->phone_primary ?? '—', 'Guardian' => ($client->guardian_name ?? null) ? "{$client->guardian_name} ({$client->guardian_phone})" : '—'] as $lbl => $val)
            <div class="sh-row">
                <span class="sh-lbl">{{ $lbl }}</span>
                <span class="sh-val">{{ $val }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── TRIAGE VITALS ── --}}
    @php $t = $triage_data; @endphp
    @if ($t)
    <div class="sh-card">
        <div class="sh-card-hdr">
            <div class="sh-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
            </div>
            <span class="sh-card-title">Triage</span>
            @if ($t['clinical']['Risk Level'] ?? null)
                <span class="sh-card-title-meta" style="color:{{ str_contains(strtolower($t['clinical']['Risk Level'] ?? ''), 'high') ? '#f87171' : '#fbbf24' }}">
                    {{ $t['clinical']['Risk Level'] }} Risk
                </span>
            @endif
        </div>
        <div class="sh-card-body">

            {{-- Alerts --}}
            @if ($t['crisis_activated'])
            <div class="sh-alert red">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd"/></svg>
                CRISIS PROTOCOL ACTIVATED
            </div>
            @endif
            @if ($t['has_red_flags'] && !empty($t['red_flags']))
            <div class="sh-alert red">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd"/></svg>
                <div>
                    <div>Red flags present</div>
                    <div style="font-weight:400;margin-top:2px;">{{ implode(' · ', $t['red_flags']) }}</div>
                </div>
            </div>
            @endif
            @if ($t['has_safeguarding'] && !empty($t['safeguarding']))
            <div class="sh-alert orange">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                <div>
                    <div>Safeguarding concerns</div>
                    <div style="font-weight:400;margin-top:2px;">{{ implode(' · ', $t['safeguarding']) }}</div>
                </div>
            </div>
            @endif

            {{-- Vitals grid --}}
            @php
                $vitalMap = [
                    'Weight'   => ['val' => $t['vitals']['Weight'] ?? null,           'lbl' => 'Weight'],
                    'Height'   => ['val' => $t['vitals']['Height'] ?? null,           'lbl' => 'Height'],
                    'BMI'      => ['val' => $t['vitals']['BMI'] ?? null,              'lbl' => 'BMI'],
                    'Temp'     => ['val' => $t['vitals']['Temperature'] ?? null,      'lbl' => 'Temp'],
                    'SpO₂'     => ['val' => $t['vitals']['SpO₂'] ?? null,            'lbl' => 'SpO₂'],
                    'Pain'     => ['val' => $t['vitals']['Pain Scale'] ?? null,       'lbl' => 'Pain'],
                    'BP'       => ['val' => $t['vitals']['Blood Pressure'] ?? null,   'lbl' => 'BP'],
                    'HR'       => ['val' => $t['vitals']['Heart Rate'] ?? null,       'lbl' => 'Heart Rate'],
                    'RR'       => ['val' => $t['vitals']['Respiratory Rate'] ?? null, 'lbl' => 'Resp. Rate'],
                ];
                $vitalMap = array_filter($vitalMap, fn($v) => $v['val'] !== null);
            @endphp
            @if (!empty($vitalMap))
            <div class="sh-vitals-grid">
                @foreach ($vitalMap as $v)
                <div class="sh-vital-box">
                    <div class="sh-vital-val">{{ $v['val'] }}</div>
                    <div class="sh-vital-lbl">{{ $v['lbl'] }}</div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Consciousness --}}
            @if ($t['vitals']['Consciousness'] ?? null)
            <div class="sh-row">
                <span class="sh-lbl">Consciousness</span>
                <span class="sh-val">{{ $t['vitals']['Consciousness'] }}</span>
            </div>
            @endif

            {{-- Clinical details --}}
            @php
                $clinicalShow = ['Triage Status','Clearance Status','Next Step','Risk Level','Triaged By'];
            @endphp
            @foreach ($clinicalShow as $key)
                @if ($t['clinical'][$key] ?? null)
                <div class="sh-row">
                    <span class="sh-lbl">{{ $key }}</span>
                    <span class="sh-val">{{ $t['clinical'][$key] }}</span>
                </div>
                @endif
            @endforeach

            {{-- Presenting complaint / notes --}}
            @if ($t['clinical']['Presenting Complaint'] ?? null)
            <div class="sh-divider" style="margin-top:10px;">Presenting Complaint</div>
            <p style="font-size:11px;color:#374151;line-height:1.5;margin:0;">{{ $t['clinical']['Presenting Complaint'] }}</p>
            @endif

            @if ($t['clinical']['Handover Summary'] ?? null)
            <div class="sh-divider" style="margin-top:10px;">Handover Summary</div>
            <p style="font-size:11px;color:#374151;line-height:1.5;margin:0;">{{ $t['clinical']['Handover Summary'] }}</p>
            @endif

            @if ($t['clinical']['Pending Actions'] ?? null)
            <div class="sh-divider" style="margin-top:10px;">Pending Actions</div>
            <p style="font-size:11px;color:#374151;line-height:1.5;margin:0;">{{ $t['clinical']['Pending Actions'] }}</p>
            @endif

        </div>
    </div>
    @endif

    {{-- ── INTAKE ASSESSMENT ── --}}
    @php $ia = $intake_data; @endphp
    @if ($ia)
    <div class="sh-card">
        <div class="sh-card-hdr">
            <div class="sh-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg>
            </div>
            <span class="sh-card-title">Intake Assessment</span>
        </div>
        <div class="sh-card-body">
            @php
                $shortFields = ['Priority Level','Services Required'];
                $longFields  = ['Reason for Visit','Current Concerns','Previous Interventions','Developmental History','Educational Background','Family History','Social History','Assessment Summary','Recommendations'];
            @endphp
            @foreach ($shortFields as $key)
                @if ($ia[$key] ?? null)
                <div class="sh-row">
                    <span class="sh-lbl">{{ $key }}</span>
                    <span class="sh-val">{{ $ia[$key] }}</span>
                </div>
                @endif
            @endforeach
            @foreach ($longFields as $key)
                @if ($ia[$key] ?? null)
                <div class="sh-divider" style="margin-top:10px;">{{ $key }}</div>
                <p style="font-size:11px;color:#374151;line-height:1.5;margin:0 0 4px;">{{ $ia[$key] }}</p>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── VISIT INFO ── --}}
    @if ($visit)
    <div class="sh-card">
        <div class="sh-card-hdr">
            <div class="sh-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
            </div>
            <span class="sh-card-title">Current Visit</span>
        </div>
        <div class="sh-card-body">
            @foreach (['Visit No.' => $visit->visit_number ?? '—', 'Date' => $visit->created_at?->format('M d, Y') ?? '—', 'Type' => ucfirst($visit->visit_type ?? 'walk-in'), 'Status' => ucfirst(str_replace('_', ' ', $visit->status ?? '—'))] as $lbl => $val)
            <div class="sh-row"><span class="sh-lbl">{{ $lbl }}</span><span class="sh-val">{{ $val }}</span></div>
            @endforeach
        </div>
    </div>
    @endif

</div>

{{-- ══════════════════════════════════
     RIGHT COLUMN
════════════════════════════════════ --}}
<div>

    {{-- ── ASSESSMENT FORMS (current visit) ── --}}
    <div class="sh-card">
        <div class="sh-card-hdr">
            <div class="sh-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
            </div>
            <span class="sh-card-title">Assessment Forms — Current Visit</span>
        </div>
        <div class="sh-card-body">
            @php $serviceFormGroups = $this->service_forms; @endphp

            @if (empty($serviceFormGroups))
                <div class="sh-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                    No services booked for this visit, or no matching assessment forms found.
                </div>
            @else
                @foreach ($serviceFormGroups as $group)
                <div class="sh-service-group">
                    <div class="sh-service-name">
                        {{ $group['service']->name }}
                        @if ($group['department'])<span class="sh-dept-badge">{{ $group['department']->code }}</span>@endif
                        @php $svcType = $group['service']->service_type ?? null; @endphp
                        @if ($svcType)
                            <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:10px;background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;">
                                {{ ucwords(str_replace('_', ' ', $svcType)) }}
                            </span>
                        @endif
                        @if ($group['service']->requires_sessions)
                            @php
                                $sessionCount  = $group['sessions']->count();
                                $totalSessions = $group['service']->default_session_count ?? 0;
                            @endphp
                            <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:10px;
                                background:{{ $sessionCount >= $totalSessions && $totalSessions > 0 ? '#f0fdf4' : '#fffbeb' }};
                                color:{{ $sessionCount >= $totalSessions && $totalSessions > 0 ? '#16a34a' : '#92400e' }};
                                border:1px solid {{ $sessionCount >= $totalSessions && $totalSessions > 0 ? '#bbf7d0' : '#fde68a' }};">
                                {{ $sessionCount }} / {{ $totalSessions }} sessions
                            </span>
                        @endif
                    </div>

                    {{-- Assessment Form Buttons --}}
                    @if ($group['forms']->isEmpty())
                        <p style="font-size:11px;color:#9ca3af;padding:6px 0;">No assessment forms configured for this service.</p>
                    @else
                        @foreach ($group['forms'] as $item)
                        @php $form = $item['schema']; $done = $item['completed']; @endphp
                        <div class="sh-form-card {{ $done ? 'done' : '' }}">
                            <div class="sh-form-info">
                                <div class="sh-form-name">
                                    {{ $form->name }}
                                    @if ($done)<span style="font-size:10px;color:#16a34a;font-weight:600;margin-left:6px;">✓ Completed</span>@endif
                                </div>
                                <div class="sh-form-meta">
                                    {{ $form->estimated_minutes ? $form->estimated_minutes . ' min' : '' }}
                                    @if ($form->estimated_minutes && $form->description) &middot; @endif
                                    {{ Str::limit($form->description ?? '', 55) }}
                                </div>
                            </div>
                            <div class="sh-form-actions">
                                @if ($done)
                                    @php $resp = $current_form_responses->firstWhere('form_schema_id', $form->id); @endphp
                                    @if ($resp)
                                    <a href="{{ $this->viewResponseUrl($resp->id) }}" class="sh-btn sh-btn-outline">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                        View
                                    </a>
                                    @endif
                                @endif
                                <a href="{{ $this->formUrl($form->slug) }}" class="sh-btn sh-btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                    {{ $done ? 'Edit' : 'Open Form' }}
                                </a>
                            </div>
                        </div>
                        @endforeach
                    @endif

                    {{-- Session Tracking (therapy services only) --}}
                    @if ($group['service']->requires_sessions)
                    <div x-data="{ open: false }" style="margin-top:10px;">
                        <button @click="open = !open" style="font-size:11px;color:#6b7280;display:flex;align-items:center;gap:6px;background:none;border:none;cursor:pointer;padding:4px 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:14px;height:14px;transition:transform .2s;" :style="open ? 'transform:rotate(90deg)' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            Sessions ({{ $group['sessions']->count() }})
                        </button>

                        <div x-show="open" style="margin-top:8px;border:1px solid #f1f5f9;border-radius:8px;overflow:hidden;">
                            @forelse($group['sessions']->sortByDesc('session_sequence') as $session)
                                <div style="display:flex;gap:12px;font-size:11px;padding:8px 12px;border-bottom:1px solid #f9fafb;align-items:center;">
                                    <span style="font-weight:700;color:#374151;min-width:20px;">#{{ $session->session_sequence }}</span>
                                    <span style="color:#6b7280;">{{ $session->session_date?->format('d M Y') }}</span>
                                    <span style="color:#374151;text-transform:capitalize;">{{ $session->attendance ?? '—' }}</span>
                                    @if($session->progress_status)
                                    <span style="color:{{ match($session->progress_status) { 'improving' => '#16a34a', 'regressing' => '#dc2626', default => '#6b7280' } }};text-transform:capitalize;font-weight:600;">
                                        {{ $session->progress_status }}
                                    </span>
                                    @endif
                                </div>
                            @empty
                                <p style="font-size:11px;color:#9ca3af;padding:10px 12px;margin:0;">No sessions recorded yet.</p>
                            @endforelse
                        </div>

                        <div style="margin-top:8px;">
                            {{ ($this->addSessionAction)(['service_booking_id' => $group['booking']->id]) }}
                        </div>
                    </div>
                    @endif

                </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ── PREVIOUS VISIT HISTORY ── --}}
    <div class="sh-card">
        <div class="sh-card-hdr">
            <div class="sh-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <span class="sh-card-title">Previous Visit History</span>
            <span class="sh-card-title-meta">Last {{ count($previous_visits) }} completed visits</span>
        </div>
        <div class="sh-card-body" style="padding:10px 12px;">

            @php $prevVisits = $previous_visits; @endphp

            @if ($prevVisits->isEmpty())
                <div class="sh-no-history">No previous completed visits on record.</div>
            @else
                @foreach ($prevVisits as $pv)
                @php
                    $pvTriage  = $pv->triage;
                    $pvIntake  = $pv->intakeAssessment;
                    $pvForms   = $pv->assessmentFormResponses;
                    $pvServices = $pv->serviceBookings->map(fn($b) => $b->service?->name)->filter()->join(', ');
                    $pvDate    = $pv->created_at?->format('M d, Y');
                    $pvStatusColor = match($pv->status ?? '') {
                        'completed','discharged','checked_out' => '#22c55e',
                        default => '#94a3b8'
                    };
                @endphp
                <div class="sh-history-item" x-data="{ open: false }">
                    <div class="sh-history-hdr" @click="open = !open">
                        <div class="sh-status-dot" style="background:{{ $pvStatusColor }};"></div>
                        <div>
                            <span class="sh-history-date">{{ $pvDate }}</span>
                            <span class="sh-history-visit">{{ $pv->visit_number ?? '' }}</span>
                        </div>
                        <div class="sh-history-services">{{ Str::limit($pvServices ?: 'Visit', 50) }}</div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:14px;height:14px;color:#9ca3af;margin-left:8px;flex-shrink:0;transition:transform .2s;" :style="open ? 'transform:rotate(180deg)' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </div>

                    <div class="sh-history-body" x-show="open" x-transition style="display:none;">

                        {{-- Triage snapshot --}}
                        @if ($pvTriage)
                        <div class="sh-sub-section">Triage Vitals</div>
                        @php
                            $pvVitals = array_filter([
                                'Weight'  => $pvTriage->weight     ? $pvTriage->weight . ' kg'     : null,
                                'Height'  => $pvTriage->height     ? $pvTriage->height . ' cm'     : null,
                                'BP'      => ($pvTriage->blood_pressure_systolic && $pvTriage->blood_pressure_diastolic) ? "{$pvTriage->blood_pressure_systolic}/{$pvTriage->blood_pressure_diastolic}" : null,
                                'SpO₂'   => $pvTriage->oxygen_saturation ? $pvTriage->oxygen_saturation . '%' : null,
                                'Temp'    => $pvTriage->temperature  ? $pvTriage->temperature . '°C' : null,
                                'HR'      => $pvTriage->heart_rate   ? $pvTriage->heart_rate . ' bpm' : null,
                                'Pain'    => $pvTriage->pain_scale !== null ? $pvTriage->pain_scale . '/10' : null,
                                'Risk'    => $pvTriage->risk_level   ? ucfirst($pvTriage->risk_level) : null,
                            ]);
                        @endphp
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                            @foreach ($pvVitals as $vk => $vv)
                            <span class="sh-tag">{{ $vk }}: {{ $vv }}</span>
                            @endforeach
                        </div>
                        @if ($pvTriage->notes)
                        <p style="font-size:11px;color:#374151;line-height:1.5;margin:0 0 8px;"><strong>Complaint:</strong> {{ Str::limit($pvTriage->notes, 200) }}</p>
                        @endif
                        @if ($pvTriage->has_red_flags && !empty($pvTriage->red_flags))
                        <div style="font-size:10px;color:#dc2626;font-weight:600;margin-bottom:8px;">⚠ Red flags: {{ implode(', ', $pvTriage->red_flags) }}</div>
                        @endif
                        @endif

                        {{-- Intake snapshot --}}
                        @if ($pvIntake)
                        <div class="sh-sub-section">Intake Summary</div>
                        @if ($pvIntake->reason_for_visit)
                        <p style="font-size:11px;color:#374151;line-height:1.5;margin:0 0 4px;"><strong>Reason:</strong> {{ Str::limit($pvIntake->reason_for_visit, 150) }}</p>
                        @endif
                        @if ($pvIntake->current_concerns)
                        <p style="font-size:11px;color:#374151;line-height:1.5;margin:0 0 4px;"><strong>Concerns:</strong> {{ Str::limit($pvIntake->current_concerns, 150) }}</p>
                        @endif
                        @if ($pvIntake->assessment_summary)
                        <p style="font-size:11px;color:#374151;line-height:1.5;margin:0 0 4px;"><strong>Summary:</strong> {{ Str::limit($pvIntake->assessment_summary, 200) }}</p>
                        @endif
                        @if ($pvIntake->recommendations)
                        <p style="font-size:11px;color:#374151;line-height:1.5;margin:0 0 4px;"><strong>Recommendations:</strong> {{ Str::limit($pvIntake->recommendations, 200) }}</p>
                        @endif
                        @endif

                        {{-- Services received --}}
                        @if ($pv->serviceBookings->isNotEmpty())
                        <div class="sh-sub-section">Services Received</div>
                        <div style="margin-bottom:8px;">
                            @foreach ($pv->serviceBookings as $bk)
                                @if ($bk->service)
                                <span class="sh-tag">{{ $bk->service->name }}</span>
                                @endif
                            @endforeach
                        </div>
                        @endif

                        {{-- Completed assessment forms --}}
                        @if ($pvForms->isNotEmpty())
                        <div class="sh-sub-section">Assessment Forms Completed</div>
                        <div style="margin-bottom:4px;">
                            @foreach ($pvForms as $resp)
                            <span class="sh-tag {{ $resp->status === 'completed' ? 'done' : 'draft' }}" style="display:inline-flex;align-items:center;gap:4px;">
                                {{ $resp->schema?->name ?? 'Form' }}
                                @if ($resp->status === 'completed')
                                    <span style="color:#16a34a;">✓</span>
                                    — <a href="{{ $this->viewResponseUrl($resp->id) }}" style="color:#16a34a;text-decoration:underline;font-size:10px;">View</a>
                                @else
                                    <span style="color:#d97706;">draft</span>
                                @endif
                            </span>
                            @endforeach
                        </div>
                        @endif

                        {{-- Link to full profile --}}
                        <div style="margin-top:10px;">
                            <a href="{{ route('filament.admin.pages.client-profile-hub', ['clientId' => $client->id, 'visitId' => $pv->id]) }}"
                               style="font-size:10px;color:#29972E;font-weight:600;text-decoration:none;">
                                View full visit in Client Profile Hub →
                            </a>
                        </div>

                    </div>
                </div>
                @endforeach
            @endif

        </div>
    </div>

</div>
</div>

@else
<div style="text-align:center;padding:60px 20px;color:#9ca3af;">
    <p style="font-size:14px;">No client loaded. Please navigate here from the Service Queue.</p>
    <a href="{{ route('filament.admin.resources.service-queue-entries.index') }}"
       style="display:inline-block;margin-top:12px;padding:8px 18px;background:#282F3B;color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
        Back to Service Queue
    </a>
</div>
@endif

<x-filament-actions::modals />

</x-filament-panels::page>
