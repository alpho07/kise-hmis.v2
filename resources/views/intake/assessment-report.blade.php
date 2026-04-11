<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>Intake Assessment Report — {{ $client->uci ?? 'Unknown' }}</title>
<style>
/* ── Base ─────────────────────────────────────────────────────────── */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size:9pt; color:#1a1a1a; background:#fff; }
h1,h2,h3,h4 { font-weight:bold; }
p { margin:0; }

/* ── Colours ──────────────────────────────────────────────────────── */
/* KISE green #29972E  amber #FFC105  dark #282F3B */

/* ── Page header ──────────────────────────────────────────────────── */
.page-header { background:#282F3B; color:#fff; padding:10pt 14pt; margin-bottom:6pt; }
.org-name    { font-size:13pt; font-weight:bold; letter-spacing:.03em; }
.report-title{ font-size:9pt;  color:#9ca3af; margin-top:2pt; }

/* ── Client info band ─────────────────────────────────────────────── */
.client-band { background:#f9fafb; border:1pt solid #e5e7eb; padding:8pt 10pt; margin-bottom:8pt; }
.client-name { font-size:13pt; font-weight:bold; color:#282F3B; }
.client-meta { font-size:8pt; color:#6b7280; margin-top:3pt; }

/* ── Section ──────────────────────────────────────────────────────── */
.section       { margin-bottom:8pt; }
.section-title { background:#29972E; color:#fff; font-size:9pt; font-weight:bold;
                 padding:4pt 8pt; letter-spacing:.03em; }
.section-body  { border:1pt solid #e5e7eb; padding:0; }

/* ── Field table ──────────────────────────────────────────────────── */
.field-table { width:100%; border-collapse:collapse; }
.field-table tr { border-bottom:1pt solid #f3f4f6; }
.field-table tr:last-child { border-bottom:none; }
.field-label { width:36%; font-size:8pt; font-weight:bold; color:#374151;
               padding:4pt 8pt; vertical-align:top; background:#fafafa;
               border-right:1pt solid #f3f4f6; }
.field-value { font-size:8.5pt; color:#111827; padding:4pt 8pt; vertical-align:top; }
.field-value.empty { color:#9ca3af; font-style:italic; }
.field-value.mono  { font-family: DejaVu Sans Mono, monospace; font-size:8pt; }

/* ── Two-column layout ────────────────────────────────────────────── */
.two-col { width:100%; border-collapse:collapse; margin-bottom:8pt; }
.two-col td { width:50%; vertical-align:top; padding-right:6pt; }
.two-col td:last-child { padding-right:0; padding-left:6pt; }

/* ── Status badges ────────────────────────────────────────────────── */
.badge       { display:inline; font-size:7.5pt; font-weight:bold; padding:1pt 5pt; border-radius:3pt; }
.badge-green { background:#dcfce7; color:#166534; border:1pt solid #bbf7d0; }
.badge-amber { background:#fefce8; color:#713f12; border:1pt solid #fef08a; }
.badge-gray  { background:#f3f4f6; color:#6b7280; border:1pt solid #e5e7eb; }
.badge-red   { background:#fee2e2; color:#991b1b; border:1pt solid #fecaca; }

/* ── Section status grid ──────────────────────────────────────────── */
.status-grid      { width:100%; border-collapse:collapse; margin-bottom:8pt; }
.status-grid td   { width:16.6%; padding:3pt 4pt; text-align:center; vertical-align:middle;
                    border:1pt solid #e5e7eb; font-size:7.5pt; }
.status-cell-done { background:#f0fdf4; color:#166534; font-weight:bold; }
.status-cell-ip   { background:#fefce8; color:#713f12; font-weight:bold; }
.status-cell-inc  { background:#f9fafb; color:#9ca3af; }

/* ── Tags (comma lists) ───────────────────────────────────────────── */
.tag-list { font-size:8.5pt; }

/* ── Sub-heading inside section body ─────────────────────────────── */
.sub-heading { background:#f0f9ff; border-bottom:1pt solid #e0f2fe;
               font-size:8pt; font-weight:bold; color:#0c4a6e;
               padding:3pt 8pt; }

/* ── Signature block ──────────────────────────────────────────────── */
.sig-block { margin-top:10pt; border-top:1pt solid #e5e7eb; padding-top:8pt; }
.sig-table { width:100%; border-collapse:collapse; }
.sig-table td { width:33%; padding:0 12pt 0 0; vertical-align:bottom; }
.sig-line { border-top:1pt solid #374151; margin-top:24pt; padding-top:3pt;
            font-size:7.5pt; color:#6b7280; }

/* ── Footer ───────────────────────────────────────────────────────── */
.page-footer { font-size:7pt; color:#9ca3af; text-align:center; margin-top:10pt;
               border-top:1pt solid #e5e7eb; padding-top:5pt; }
</style>
</head>
<body>

{{-- ══ PAGE HEADER ════════════════════════════════════════════════════════ --}}
<div class="page-header">
    <div class="org-name">KISE — Kenya Institute of Special Education</div>
    <div class="report-title">Intake Assessment Report &nbsp;·&nbsp; Confidential Clinical Record</div>
</div>

{{-- ══ CLIENT BAND ═════════════════════════════════════════════════════════ --}}
@php
    $dobStr = $client->date_of_birth
        ? $client->date_of_birth->format('d M Y') . ' (' . $client->age . ' yrs)'
        : '—';
    $genderLabel = $labels['gender'][$client->gender] ?? ucfirst($client->gender ?? '—');
    $visitTypeLabel = $labels['visit_type'][$visit->visit_type ?? ''] ?? ucfirst($visit->visit_type ?? '—');
@endphp

<div class="client-band">
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="width:65%;vertical-align:top;">
                <div class="client-name">{{ $client->full_name ?? '—' }}</div>
                <div class="client-meta">
                    UCI: <strong class="mono">{{ $client->uci ?? '—' }}</strong>
                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    Visit: <strong class="mono">{{ $visit->visit_number ?? '—' }}</strong>
                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    DOB: {{ $dobStr }}
                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    Gender: {{ $genderLabel }}
                </div>
                <div class="client-meta" style="margin-top:3pt;">
                    Branch: {{ $visit->branch->name ?? '—' }}
                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    County: {{ $client->county?->name ?? '—' }}
                    @if($client->subCounty) &nbsp;/ {{ $client->subCounty->name }} @endif
                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    Visit Type:
                    <span class="badge {{ in_array($visit->visit_type, ['urgent','emergency']) ? 'badge-red' : 'badge-green' }}">
                        {{ $visitTypeLabel }}
                    </span>
                </div>
            </td>
            <td style="width:35%;text-align:right;vertical-align:top;">
                <div style="font-size:7.5pt;color:#6b7280;">Report generated</div>
                <div style="font-size:8pt;font-weight:bold;color:#282F3B;">{{ $printedAt->format('d M Y H:i') }}</div>
                <div style="font-size:7.5pt;color:#6b7280;margin-top:3pt;">By: {{ $printedBy->name ?? '—' }}</div>
                @if($intake->is_finalized)
                    <div style="margin-top:5pt;">
                        <span class="badge badge-green">FINALIZED {{ $intake->finalized_at?->format('d M Y') }}</span>
                    </div>
                @else
                    <div style="margin-top:5pt;">
                        <span class="badge badge-amber">DRAFT — NOT FINALIZED</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>
</div>

{{-- ══ SECTION COMPLETION STATUS ═══════════════════════════════════════════ --}}
@php
    $sectionNames = [
        'A'=>'A. Overview','B'=>'B. ID & Contact','C'=>'C. Disability','D'=>'D. Socio-Demog.',
        'E'=>'E. Medical','F'=>'F. Education','G'=>'G. Screening','H'=>'H. Concern',
        'I'=>'I. Services','J'=>'J. Payment','K'=>'K. Deferral','L'=>'L. Summary',
    ];
    $sectionStatus = $intake->section_status ?? [];
@endphp
<table class="status-grid">
    <tr>
        @foreach($sectionNames as $key => $name)
            @php
                $st = $sectionStatus[$key] ?? 'incomplete';
                $cellClass = $st === 'complete' ? 'status-cell-done' : ($st === 'in_progress' ? 'status-cell-ip' : 'status-cell-inc');
                $icon = $st === 'complete' ? '✓' : ($st === 'in_progress' ? '…' : '○');
            @endphp
            <td class="{{ $cellClass }}">{{ $icon }} {{ $name }}</td>
        @endforeach
    </tr>
</table>

{{-- ══ SECTION A — CLIENT OVERVIEW (read-only) ════════════════════════════ --}}
<div class="section">
    <div class="section-title">A — Client Overview</div>
    <div class="section-body">
        <table class="field-table">
            <tr>
                <td class="field-label">Full Name</td>
                <td class="field-value">{{ $client->full_name ?? '—' }}</td>
                <td class="field-label">UCI Number</td>
                <td class="field-value mono">{{ $client->uci ?? '—' }}</td>
            </tr>
            <tr>
                <td class="field-label">Date of Birth</td>
                <td class="field-value">{{ $dobStr }}</td>
                <td class="field-label">Gender</td>
                <td class="field-value">{{ $genderLabel }}</td>
            </tr>
            <tr>
                <td class="field-label">SHA Number</td>
                <td class="field-value mono">{{ $client->sha_number ?? '—' }}</td>
                <td class="field-label">NCPWD Number</td>
                <td class="field-value mono">{{ $client->ncpwd_number ?? '—' }}</td>
            </tr>
            <tr>
                <td class="field-label">Branch</td>
                <td class="field-value">{{ $visit->branch->name ?? '—' }}</td>
                <td class="field-label">Visit Date</td>
                <td class="field-value">{{ $visit->created_at?->format('d M Y') ?? '—' }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- ══ SECTION B — ID & CONTACT ════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">B — ID &amp; Contact</div>
    <div class="section-body">
        <table class="field-table">
            <tr>
                <td class="field-label">National ID</td>
                <td class="field-value mono">{{ $client->national_id ?? '—' }}</td>
                <td class="field-label">Birth Certificate</td>
                <td class="field-value mono">{{ $client->birth_certificate_number ?? '—' }}</td>
            </tr>
            <tr>
                <td class="field-label">Phone (Primary)</td>
                <td class="field-value mono">{{ $client->phone_primary ?? '—' }}</td>
                <td class="field-label">Phone (Secondary)</td>
                <td class="field-value mono">{{ $client->phone_secondary ?? '—' }}</td>
            </tr>
            <tr>
                <td class="field-label">Email</td>
                <td class="field-value">{{ $client->email ?? '—' }}</td>
                <td class="field-label">SMS Consent</td>
                <td class="field-value">{{ $client->consent_to_sms ? 'Yes' : 'No' }}</td>
            </tr>
            <tr>
                <td class="field-label">County</td>
                <td class="field-value">{{ $client->county?->name ?? '—' }}</td>
                <td class="field-label">Sub-County / Ward</td>
                <td class="field-value">
                    {{ $client->subCounty?->name ?? '—' }}
                    @if($client->ward) / {{ $client->ward->name }} @endif
                </td>
            </tr>
            <tr>
                <td class="field-label">Address</td>
                <td class="field-value" colspan="3">
                    {{ $client->primary_address ?? '—' }}
                    @if($client->landmark) &nbsp;({{ $client->landmark }}) @endif
                </td>
            </tr>
            <tr>
                <td class="field-label">Verification Mode</td>
                <td class="field-value">{{ $labels['verification_mode'][$intake->verification_mode ?? ''] ?? ($intake->verification_mode ?? '—') }}</td>
                <td class="field-label">Verification Notes</td>
                <td class="field-value">{{ $intake->verification_notes ?? '—' }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- ══ SECTION C — DISABILITY & NCPWD ═════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">C — Disability &amp; NCPWD</div>
    <div class="section-body">
        @if($disability)
            @php
                $disCategories = collect($disability->disability_categories ?? [])
                    ->map(fn($v) => $labels['disability_categories'][$v] ?? $v)
                    ->join(', ');
                $atDevices = collect($disability->assistive_technology ?? [])
                    ->join(', ');
            @endphp
            <table class="field-table">
                <tr>
                    <td class="field-label">Disability Known</td>
                    <td class="field-value">{{ $disability->is_disability_known ? 'Yes' : 'No' }}</td>
                    <td class="field-label">Onset</td>
                    <td class="field-value">{{ $labels['onset'][$disability->onset ?? ''] ?? ($disability->onset ?? '—') }}</td>
                </tr>
                <tr>
                    <td class="field-label">Disability Categories</td>
                    <td class="field-value" colspan="3">
                        @if($disCategories) {{ $disCategories }}
                        @else <span class="empty">—</span> @endif
                    </td>
                </tr>
                <tr>
                    <td class="field-label">Level of Functioning</td>
                    <td class="field-value">{{ $labels['level_of_functioning'][$disability->level_of_functioning ?? ''] ?? ($disability->level_of_functioning ?? '—') }}</td>
                    <td class="field-label">NCPWD Registered</td>
                    <td class="field-value">{{ $labels['ncpwd_registered'][$disability->ncpwd_registered ?? ''] ?? ($disability->ncpwd_registered ?? '—') }}</td>
                </tr>
                <tr>
                    <td class="field-label">NCPWD Verification</td>
                    <td class="field-value">{{ $labels['ncpwd_verification_status'][$disability->ncpwd_verification_status ?? ''] ?? ($disability->ncpwd_verification_status ?? '—') }}</td>
                    <td class="field-label">Current AT Devices</td>
                    <td class="field-value">{{ $atDevices ?: '—' }}</td>
                </tr>
                @if($disability->disability_notes)
                <tr>
                    <td class="field-label">Notes</td>
                    <td class="field-value" colspan="3">{{ $disability->disability_notes }}</td>
                </tr>
                @endif
            </table>
        @else
            <div style="padding:8pt 10pt;color:#9ca3af;font-style:italic;font-size:8.5pt;">No disability record captured.</div>
        @endif
    </div>
</div>

{{-- ══ SECTION D — SOCIO-DEMOGRAPHICS ══════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">D — Socio-Demographics</div>
    <div class="section-body">
        @if($socio)
            @php
                $supportSources = collect((array)($socio->source_of_support ?? []))
                    ->map(fn($v) => $labels['source_of_support'][$v] ?? $v)
                    ->join(', ');
                $caregiverRaw = $socio->primary_caregiver ?? null;
                $caregiverIsOther = str_starts_with($caregiverRaw ?? '', 'other: ');
                $caregiverLabel = $caregiverIsOther
                    ? 'Other: ' . substr($caregiverRaw, 7)
                    : ($labels['primary_caregiver'][$caregiverRaw ?? ''] ?? ($caregiverRaw ?? '—'));
                $langRaw = $socio->primary_language ?? null;
                $langIsOther = str_starts_with($langRaw ?? '', 'other: ');
                $langLabel = $langIsOther
                    ? 'Other: ' . substr($langRaw, 7)
                    : ($labels['primary_language'][$langRaw ?? ''] ?? ($langRaw ?? '—'));
            @endphp
            <table class="field-table">
                <tr>
                    <td class="field-label">Marital Status</td>
                    <td class="field-value">
                        {{ $labels['marital_status'][$socio->marital_status ?? ''] ?? ($socio->marital_status ?? '—') }}
                        @if($socio->marital_status_other) ({{ $socio->marital_status_other }}) @endif
                    </td>
                    <td class="field-label">Living Arrangement</td>
                    <td class="field-value">
                        {{ $labels['living_arrangement'][$socio->living_arrangement ?? ''] ?? ($socio->living_arrangement ?? '—') }}
                        @if($socio->living_arrangement_other) ({{ $socio->living_arrangement_other }}) @endif
                    </td>
                </tr>
                <tr>
                    <td class="field-label">Household Size</td>
                    <td class="field-value">{{ $socio->household_size ?? '—' }}</td>
                    <td class="field-label">Primary Caregiver</td>
                    <td class="field-value">{{ $caregiverLabel }}</td>
                </tr>
                <tr>
                    <td class="field-label">Primary Language</td>
                    <td class="field-value">{{ $langLabel }}</td>
                    <td class="field-label">Other Languages</td>
                    <td class="field-value">
                        @php $otherLangs = $socio->other_languages ?? []; @endphp
                        {{ is_array($otherLangs) ? implode(', ', $otherLangs) : ($otherLangs ?: '—') }}
                    </td>
                </tr>
                <tr>
                    <td class="field-label">Source of Support</td>
                    <td class="field-value" colspan="3">{{ $supportSources ?: '—' }}
                        @if($socio->other_support_source) ({{ $socio->other_support_source }}) @endif
                    </td>
                </tr>
                <tr>
                    <td class="field-label">School Enrolled</td>
                    <td class="field-value">{{ $socio->school_enrolled ? 'Yes' : 'No' }}</td>
                    <td class="field-label">Home Accessibility</td>
                    <td class="field-value">{{ $socio->accessibility_at_home ? 'Yes' : 'No' }}</td>
                </tr>
                @if($socio->socio_notes)
                <tr>
                    <td class="field-label">Notes</td>
                    <td class="field-value" colspan="3">{{ $socio->socio_notes }}</td>
                </tr>
                @endif
            </table>
        @else
            <div style="padding:8pt 10pt;color:#9ca3af;font-style:italic;font-size:8.5pt;">No socio-demographic record captured.</div>
        @endif
    </div>
</div>

{{-- ══ SECTION E — MEDICAL HISTORY ═════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">E — Medical History</div>
    <div class="section-body">
        @if($med)
            @php
                $conditions = collect($med->medical_conditions ?? [])
                    ->map(fn($v) => is_array($v) ? implode(', ',$v) : $v)->join(', ');
                $allergies = collect($med->allergies ?? [])
                    ->map(fn($v) => is_array($v) ? ($v['allergen'] ?? '') . (($v['reaction'] ?? '') ? ' (' . $v['reaction'] . ')' : '') : $v)
                    ->filter()->join(', ');
                $prevAt = $med->assistive_devices_history ?? [];
                $peri   = $med->perinatal_history ?? [];
                $imm    = $med->immunization_records ?? [];
                $feeding = $med->feeding_history ?? [];
            @endphp

            {{-- E1 Medical --}}
            <div class="sub-heading">Medical Conditions &amp; Medications</div>
            <table class="field-table">
                <tr>
                    <td class="field-label">Medical Conditions</td>
                    <td class="field-value" colspan="3">{{ $conditions ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="field-label">Current Medications</td>
                    <td class="field-value" colspan="3">{{ $med->current_medications ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="field-label">Surgical History</td>
                    <td class="field-value" colspan="3">{{ $med->surgical_history ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="field-label">Family Medical History</td>
                    <td class="field-value">{{ $med->family_medical_history ?? '—' }}</td>
                    <td class="field-label">Immunization Status</td>
                    <td class="field-value">{{ $med->immunization_status ? ucfirst($med->immunization_status) : '—' }}</td>
                </tr>
                <tr>
                    <td class="field-label">Allergies</td>
                    <td class="field-value" colspan="3">{{ $allergies ?: '—' }}</td>
                </tr>
                @if($intake->family_history)
                <tr>
                    <td class="field-label">Family History Notes</td>
                    <td class="field-value" colspan="3">{{ $intake->family_history }}</td>
                </tr>
                @endif
            </table>

            @if(!empty($peri))
                <div class="sub-heading">Perinatal History</div>
                <table class="field-table">
                    <tr>
                        <td class="field-label">Place of Birth</td>
                        <td class="field-value">{{ $peri['place_of_birth'] ?? '—' }}</td>
                        <td class="field-label">Mode of Delivery</td>
                        <td class="field-value">{{ $peri['mode_of_delivery'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="field-label">Gestation (weeks)</td>
                        <td class="field-value">{{ $peri['gestation_weeks'] ?? '—' }}</td>
                        <td class="field-label">Birth Weight (kg)</td>
                        <td class="field-value">{{ $peri['birth_weight_kg'] ?? '—' }}</td>
                    </tr>
                    @if(!empty($peri['pregnancy_complications']))
                    <tr>
                        <td class="field-label">Pregnancy Complications</td>
                        <td class="field-value" colspan="3">{{ implode(', ', (array)($peri['pregnancy_complications'])) }}</td>
                    </tr>
                    @endif
                    @if(!empty($peri['neonatal_care']))
                    <tr>
                        <td class="field-label">Neonatal Care</td>
                        <td class="field-value" colspan="3">{{ implode(', ', (array)($peri['neonatal_care'])) }}</td>
                    </tr>
                    @endif
                    @if(!empty($peri['developmental_concerns']))
                    <tr>
                        <td class="field-label">Developmental Concerns</td>
                        <td class="field-value" colspan="3">{{ implode(', ', (array)($peri['developmental_concerns'])) }}</td>
                    </tr>
                    @endif
                    @if($med->developmental_concerns_notes)
                    <tr>
                        <td class="field-label">Dev. History Notes</td>
                        <td class="field-value" colspan="3">{{ $med->developmental_concerns_notes }}</td>
                    </tr>
                    @endif
                </table>
            @endif

            @if(!empty($imm))
                <div class="sub-heading">Immunization</div>
                <table class="field-table">
                    <tr>
                        <td class="field-label">EPI Status</td>
                        <td class="field-value">
                            @php $epiStatus = $imm['epi_status'] ?? []; @endphp
                            {{ is_array($epiStatus) ? implode(', ', $epiStatus) : ($epiStatus ?: '—') }}
                        </td>
                        <td class="field-label">EPI Card Seen</td>
                        <td class="field-value">{{ ucfirst($imm['epi_card_seen'] ?? '—') }}</td>
                    </tr>
                    @if(!empty($imm['missed_doses']))
                    <tr>
                        <td class="field-label">Missed Doses</td>
                        <td class="field-value">{{ $imm['missed_doses'] }}</td>
                        <td class="field-label">Which Doses</td>
                        <td class="field-value">{{ $imm['missed_doses_which'] ?? '—' }}</td>
                    </tr>
                    @endif
                </table>
            @endif

            @if(!empty($feeding))
                <div class="sub-heading">Feeding History</div>
                <table class="field-table">
                    <tr>
                        <td class="field-label">Feeding Method</td>
                        <td class="field-value">{{ $feeding['feeding_method'] ?? '—' }}</td>
                        <td class="field-label">Diet / Appetite</td>
                        <td class="field-value">{{ $feeding['diet_appetite'] ?? '—' }}</td>
                    </tr>
                    @if(!empty($feeding['foods_brief']))
                    <tr>
                        <td class="field-label">Foods (brief)</td>
                        <td class="field-value" colspan="3">{{ $feeding['foods_brief'] }}</td>
                    </tr>
                    @endif
                    @if(!empty($feeding['swallowing_concerns']))
                    <tr>
                        <td class="field-label">Swallowing Concerns</td>
                        <td class="field-value" colspan="3">
                            @php $sc = $feeding['swallowing_concerns'] ?? []; @endphp
                            {{ is_array($sc) ? implode(', ', $sc) : $sc }}
                        </td>
                    </tr>
                    @endif
                    @if(!empty($feeding['nutrition_notes']))
                    <tr>
                        <td class="field-label">Nutrition Notes</td>
                        <td class="field-value" colspan="3">{{ $feeding['nutrition_notes'] }}</td>
                    </tr>
                    @endif
                </table>
            @endif

        @else
            <div style="padding:8pt 10pt;color:#9ca3af;font-style:italic;font-size:8.5pt;">No medical history record captured.</div>
        @endif
    </div>
</div>

{{-- ══ SECTION F — EDUCATION & WORK ════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">F — Education &amp; Work</div>
    <div class="section-body">
        @if($edu)
            <table class="field-table">
                <tr>
                    <td class="field-label">Education Level</td>
                    <td class="field-value">{{ $labels['education_level'][$edu->education_level ?? ''] ?? ($edu->education_level ?? '—') }}</td>
                    <td class="field-label">Currently Enrolled</td>
                    <td class="field-value">{{ $edu->currently_enrolled ? 'Yes' : 'No' }}</td>
                </tr>
                @if($edu->currently_enrolled)
                <tr>
                    <td class="field-label">School Type</td>
                    <td class="field-value">{{ $labels['school_type'][$edu->school_type ?? ''] ?? ($edu->school_type ?? '—') }}</td>
                    <td class="field-label">School / Institution</td>
                    <td class="field-value">{{ $edu->school_name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="field-label">Grade / Level</td>
                    <td class="field-value">{{ $edu->grade_level ?? '—' }}</td>
                    <td class="field-label">Attendance Challenges</td>
                    <td class="field-value">
                        {{ $edu->attendance_challenges ? 'Yes' : 'No' }}
                        @if($edu->attendance_notes) — {{ $edu->attendance_notes }} @endif
                    </td>
                </tr>
                <tr>
                    <td class="field-label">Performance Concern</td>
                    <td class="field-value" colspan="3">
                        {{ $edu->performance_concern ? 'Yes' : 'No' }}
                        @if($edu->performance_notes) — {{ $edu->performance_notes }} @endif
                    </td>
                </tr>
                @endif
                <tr>
                    <td class="field-label">Employment Status</td>
                    <td class="field-value">
                        {{ $labels['employment_status'][$edu->employment_status ?? ''] ?? ($edu->employment_status ?? '—') }}
                        @if($edu->employment_status === 'other' && $edu->employment_status_other)
                            — {{ $edu->employment_status_other }}
                        @endif
                    </td>
                    <td class="field-label">Occupation / Role</td>
                    <td class="field-value">{{ $edu->occupation_type ?? '—' }}</td>
                </tr>
                @if($edu->education_notes)
                <tr>
                    <td class="field-label">Notes</td>
                    <td class="field-value" colspan="3">{{ $edu->education_notes }}</td>
                </tr>
                @endif
            </table>
        @else
            <div style="padding:8pt 10pt;color:#9ca3af;font-style:italic;font-size:8.5pt;">No education &amp; work record captured.</div>
        @endif
    </div>
</div>

{{-- ══ SECTION G — FUNCTIONAL SCREENING ═══════════════════════════════════ --}}
<div class="section">
    <div class="section-title">G — Functional Screening</div>
    <div class="section-body">
        @php
            $band    = $screeningScores['band'] ?? null;
            $answers = $screeningScores['answers'] ?? [];
            $overallSummary = $intake->functionalScreening?->overall_summary;
        @endphp

        @if($band)
            <div style="padding:5pt 8pt;background:#dbeafe;border-bottom:1pt solid #bfdbfe;font-size:8pt;color:#1e3a8a;">
                <strong>Age Band:</strong> {{ strtoupper(str_replace('_',' ',$band)) }}
                &nbsp;·&nbsp;
                <strong>Age at assessment:</strong>
                @if($client->date_of_birth)
                    @php $months = (int)\Carbon\Carbon::parse($client->date_of_birth)->diffInMonths(now()); @endphp
                    {{ floor($months/12) }} yrs {{ $months%12 }} mo
                @else
                    —
                @endif
            </div>

            @foreach($answers as $domain => $questions)
                @php
                    $domainLabel = \App\Filament\Resources\IntakeAssessmentResource::screeningDomainLabel($band, $domain);
                    $notes = $questions['_notes'] ?? null;
                    $domainAnswers = array_filter($questions, fn($k) => $k !== '_notes', ARRAY_FILTER_USE_KEY);
                @endphp
                @if(!empty($domainAnswers))
                <div class="sub-heading">{{ $domainLabel }}</div>
                <table class="field-table">
                    @foreach($domainAnswers as $qKey => $answer)
                        @if($answer !== null && $answer !== '')
                        @php
                            $questionText = \App\Filament\Resources\IntakeAssessmentResource::screeningQuestionText($band, $domain, $qKey);
                        @endphp
                        <tr>
                            <td class="field-label" style="width:55%;">{{ $questionText }}</td>
                            <td class="field-value">
                                @if(is_array($answer)) {{ implode(', ', $answer) }}
                                @elseif(is_bool($answer)) {{ $answer ? 'Yes' : 'No' }}
                                @else {{ $answer }} @endif
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    @if($notes)
                    <tr>
                        <td class="field-label" style="width:55%;">Notes</td>
                        <td class="field-value">{{ $notes }}</td>
                    </tr>
                    @endif
                </table>
                @endif
            @endforeach

            @if($overallSummary)
            <div class="sub-heading">Overall Summary</div>
            <div style="padding:6pt 8pt;font-size:8.5pt;">{{ $overallSummary }}</div>
            @endif

        @else
            <div style="padding:8pt 10pt;color:#9ca3af;font-style:italic;font-size:8.5pt;">Functional screening not completed.</div>
        @endif
    </div>
</div>

{{-- ══ SECTION H — PRESENTING CONCERN ══════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">H — Presenting Concern</div>
    <div class="section-body">
        @php
            $refSources = collect((array)($sr['referral_source'] ?? []))
                ->map(fn($v) => $labels['referral_source'][$v] ?? $v)
                ->join(', ');
        @endphp
        <table class="field-table">
            <tr>
                <td class="field-label">Referral Source</td>
                <td class="field-value" colspan="3">{{ $refSources ?: '—' }}</td>
            </tr>
            @if(!empty($sr['referral_source_other']))
            <tr>
                <td class="field-label">Referral — Specify</td>
                <td class="field-value" colspan="3">{{ $sr['referral_source_other'] }}</td>
            </tr>
            @endif
            <tr>
                <td class="field-label">Referring Person / Institution</td>
                <td class="field-value" colspan="3">{{ $sr['referral_contact'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="field-label">Reason for Visit</td>
                <td class="field-value" colspan="3">{{ $intake->reason_for_visit ?? '—' }}</td>
            </tr>
            @if($intake->current_concerns)
            <tr>
                <td class="field-label">Current Concerns &amp; Needs</td>
                <td class="field-value" colspan="3">{{ $intake->current_concerns }}</td>
            </tr>
            @endif
            @if($intake->previous_interventions)
            <tr>
                <td class="field-label">Previous Interventions</td>
                <td class="field-value" colspan="3">{{ $intake->previous_interventions }}</td>
            </tr>
            @endif
        </table>
    </div>
</div>

{{-- ══ SECTION I — SERVICE PLAN ════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">I — Service Plan</div>
    <div class="section-body">
        @php
            $primaryService = $services[$sr['primary_service_id'] ?? 0] ?? null;
            $otherServiceIds = array_diff((array)($sr['service_ids'] ?? []), [$sr['primary_service_id'] ?? null]);
            $otherServices = $services->only($otherServiceIds);
            $serviceCategories = collect((array)($sr['service_categories'] ?? []))->join(', ');
        @endphp
        <table class="field-table">
            <tr>
                <td class="field-label">Primary Service</td>
                <td class="field-value" colspan="3">{{ $primaryService?->name ?? '—' }}</td>
            </tr>
            @if($otherServices->isNotEmpty())
            <tr>
                <td class="field-label">Additional Services</td>
                <td class="field-value" colspan="3">{{ $otherServices->pluck('name')->join(', ') }}</td>
            </tr>
            @endif
            @if($serviceCategories)
            <tr>
                <td class="field-label">Service Categories</td>
                <td class="field-value" colspan="3">{{ $serviceCategories }}</td>
            </tr>
            @endif
            <tr>
                <td class="field-label">Priority Level</td>
                <td class="field-value">{{ $intake->priority_level ?? '—' }}</td>
                <td class="field-label">Visit Priority</td>
                <td class="field-value">{{ ucfirst($visit->priority ?? '—') }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- ══ SECTION J — PAYMENT PATHWAY ════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">J — Payment Pathway</div>
    <div class="section-body">
        @php
            $paymentMethod = $sr['payment_method'] ?? null;
            $paymentLabel  = $labels['payment_method'][$paymentMethod ?? ''] ?? ($paymentMethod ?? '—');
        @endphp
        <table class="field-table">
            <tr>
                <td class="field-label">Expected Payment Method</td>
                <td class="field-value">{{ $paymentLabel }}</td>
                <td class="field-label">SHA Enrolled</td>
                <td class="field-value">{{ isset($sr['sha_enrolled']) ? ($sr['sha_enrolled'] ? 'Yes' : 'No') : '—' }}</td>
            </tr>
            <tr>
                <td class="field-label">NCPWD Covered</td>
                <td class="field-value">{{ isset($sr['ncpwd_covered']) ? ($sr['ncpwd_covered'] ? 'Yes' : 'No') : '—' }}</td>
                <td class="field-label">Private Insurance</td>
                <td class="field-value">{{ isset($sr['has_insurance']) ? ($sr['has_insurance'] ? 'Yes' : 'No') : '—' }}</td>
            </tr>
            @if(!empty($sr['payment_notes']))
            <tr>
                <td class="field-label">Payment Notes</td>
                <td class="field-value" colspan="3">{{ $sr['payment_notes'] }}</td>
            </tr>
            @endif
        </table>
    </div>
</div>

{{-- ══ SECTION K — DEFERRAL ════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">K — Deferral</div>
    <div class="section-body">
        @php $isDeferred = $visit->status === 'deferred'; @endphp
        @if($isDeferred)
            <table class="field-table">
                <tr>
                    <td class="field-label">Status</td>
                    <td class="field-value"><span class="badge badge-amber">DEFERRED</span></td>
                    <td class="field-label">Deferral Reason</td>
                    <td class="field-value">{{ $labels['deferral_reason'][$visit->deferral_reason ?? ''] ?? ($visit->deferral_reason ?? '—') }}</td>
                </tr>
                <tr>
                    <td class="field-label">Deferral Notes</td>
                    <td class="field-value" colspan="3">{{ $visit->deferral_notes ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="field-label">Next Appointment</td>
                    <td class="field-value" colspan="3">
                        @if($visit->next_appointment_date)
                            {{ \Carbon\Carbon::parse($visit->next_appointment_date)->format('d M Y') }}
                        @else —
                        @endif
                    </td>
                </tr>
            </table>
        @else
            <div style="padding:6pt 8pt;font-size:8.5pt;color:#374151;">
                Client not deferred — proceeding with assessment on this visit.
            </div>
        @endif
    </div>
</div>

{{-- ══ SECTION L — SUMMARY & FINALIZE ════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">L — Summary &amp; Finalize</div>
    <div class="section-body">
        <table class="field-table">
            @if($intake->assessment_summary)
            <tr>
                <td class="field-label">Assessment Summary</td>
                <td class="field-value" colspan="3">{{ $intake->assessment_summary }}</td>
            </tr>
            @endif
            @if($intake->recommendations)
            <tr>
                <td class="field-label">Recommendations</td>
                <td class="field-value" colspan="3">{{ $intake->recommendations }}</td>
            </tr>
            @endif
            <tr>
                <td class="field-label">Priority Level</td>
                <td class="field-value">{{ $intake->priority_level ?? '—' }}</td>
                <td class="field-label">Data Verified</td>
                <td class="field-value">{{ $intake->data_verified ? 'Yes' : 'No' }}</td>
            </tr>
            <tr>
                <td class="field-label">Finalization Status</td>
                <td class="field-value" colspan="3">
                    @if($intake->is_finalized)
                        <span class="badge badge-green">Finalized on {{ $intake->finalized_at?->format('d M Y H:i') }}</span>
                        @if($intake->assessedBy) &nbsp; by {{ $intake->assessedBy->name }} @endif
                    @else
                        <span class="badge badge-amber">Not yet finalized</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>
</div>

{{-- ══ SIGNATURE BLOCK ══════════════════════════════════════════════════════ --}}
<div class="sig-block">
    <table class="sig-table">
        <tr>
            <td>
                <div class="sig-line">Intake Officer / Assessed By</div>
            </td>
            <td>
                <div class="sig-line">Supervisor / Reviewer</div>
            </td>
            <td>
                <div class="sig-line">Date</div>
            </td>
        </tr>
    </table>
</div>

{{-- ══ FOOTER ═══════════════════════════════════════════════════════════════ --}}
<div class="page-footer">
    KISE HMIS &nbsp;·&nbsp; Intake Assessment Report &nbsp;·&nbsp;
    Client: {{ $client->uci ?? '—' }} &nbsp;·&nbsp;
    Visit: {{ $visit->visit_number ?? '—' }} &nbsp;·&nbsp;
    Generated: {{ $printedAt->format('d M Y H:i') }}
    &nbsp;·&nbsp; CONFIDENTIAL — For clinical use only
</div>

</body>
</html>
