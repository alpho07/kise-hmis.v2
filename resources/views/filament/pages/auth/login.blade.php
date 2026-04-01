{{-- Single Livewire root element: all CSS lives inside this div --}}
<div class="kise-login-wrap">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* ── Strip Filament's simple-layout wrappers so we go full-screen ─────────── */
body, html { background: #282F3B !important; margin: 0 !important; padding: 0 !important; }
.fi-simple-layout  { display: block !important; background: transparent !important; padding: 0 !important; }
.fi-simple-main-ctn { display: block !important; padding: 0 !important; }
.fi-simple-main {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    min-height: 100vh !important;
    ring: none !important;
}

.kise-login-wrap * { box-sizing: border-box; }

.kise-login-wrap {
    display: flex;
    min-height: 100vh;
    font-family: 'Inter', system-ui, sans-serif;
    background: #282F3B;
}

/* ── Left info panel (75%) ──────────────────────────────────── */
.kise-info-panel {
    width: 75%;
    background: #282F3B;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 40px 52px;
}

/* Decorative circles */
.kise-info-panel::before {
    content: '';
    position: absolute;
    top: -100px;
    right: -100px;
    width: 420px;
    height: 420px;
    border-radius: 50%;
    background: rgba(41, 151, 46, 0.08);
    pointer-events: none;
}
.kise-info-panel::after {
    content: '';
    position: absolute;
    bottom: -80px;
    left: -80px;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: rgba(255, 193, 5, 0.05);
    pointer-events: none;
}
.kise-circle-mid {
    position: absolute;
    top: 45%;
    left: 38%;
    width: 600px;
    height: 600px;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.03);
    pointer-events: none;
}

/* Logo row */
.kise-brand-row {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 1;
}
.kise-brand-mark {
    width: 44px;
    height: 44px;
    background: #29972E;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 900;
    color: #fff;
    flex-shrink: 0;
    letter-spacing: -1px;
}
.kise-brand-text-wrap { line-height: 1; }
.kise-brand-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.02em;
}
.kise-brand-sub {
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.45);
    margin-top: 2px;
}

/* Hero block */
.kise-hero {
    position: relative;
    z-index: 1;
    margin: 36px 0 28px;
}
.kise-hero h1 {
    font-size: 2.2rem;
    font-weight: 800;
    line-height: 1.15;
    color: #ffffff;
    letter-spacing: -0.03em;
    margin-bottom: 12px;
}
.kise-hero h1 span { color: #29972E; }
.kise-hero p {
    font-size: 0.95rem;
    line-height: 1.7;
    color: rgba(255,255,255,0.55);
    max-width: 520px;
}

/* KPI grid */
.kise-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 24px;
    position: relative;
    z-index: 1;
}
.kise-kpi-card {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 10px;
    padding: 16px 14px;
    text-align: center;
    transition: background .15s;
}
.kise-kpi-card:hover { background: rgba(255,255,255,0.09); }
.kise-kpi-num {
    font-size: 1.8rem;
    font-weight: 800;
    color: #FFC105;
    line-height: 1;
    letter-spacing: -0.02em;
}
.kise-kpi-lbl {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.45);
    margin-top: 5px;
}

/* Pipeline stages */
.kise-pipeline-wrap {
    position: relative;
    z-index: 1;
    margin-bottom: 20px;
}
.kise-pipeline-label {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.35);
    margin-bottom: 8px;
}
.kise-pipeline-row {
    display: flex;
    align-items: center;
    gap: 4px;
}
.kise-stage-chip {
    flex: 1;
    text-align: center;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 7px 4px;
    border-radius: 5px;
    background: rgba(255,255,255,0.07);
    color: rgba(255,255,255,0.5);
    transition: background .15s;
}
.kise-stage-chip.hl {
    background: #29972E;
    color: #fff;
}
.kise-stage-chip.hl-y {
    background: rgba(255,193,5,0.15);
    color: #FFC105;
}
.kise-stage-arrow {
    color: rgba(255,255,255,0.2);
    font-size: 0.65rem;
    flex-shrink: 0;
}

/* About strip */
.kise-about-strip {
    position: relative;
    z-index: 1;
    border-top: 1px solid rgba(255,255,255,0.07);
    padding-top: 16px;
    display: flex;
    gap: 24px;
}
.kise-about-item { flex: 1; }
.kise-about-title {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.3);
    margin-bottom: 4px;
}
.kise-about-val {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.55);
    line-height: 1.55;
}

/* ── Right form panel (25%) ─────────────────────────────────── */
.kise-form-panel {
    width: 25%;
    min-width: 280px;
    background: #f3f4f6;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 40px 28px;
    position: relative;
}

.kise-form-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 28px;
}
.kise-form-logo-mark {
    width: 32px;
    height: 32px;
    background: #29972E;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 900;
    color: #fff;
}
.kise-form-logo-name {
    font-size: 0.85rem;
    font-weight: 800;
    color: #282F3B;
    letter-spacing: -0.01em;
}

.kise-form-heading {
    font-size: 1.3rem;
    font-weight: 800;
    color: #282F3B;
    letter-spacing: -0.02em;
    margin-bottom: 4px;
}
.kise-form-sub {
    font-size: 0.8rem;
    color: #9ca3af;
    margin-bottom: 28px;
}

/* Override Filament form field styles within login */
.kise-form-panel .fi-fo-field-wrp-label,
.kise-form-panel label {
    font-size: 0.72rem !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.06em !important;
    color: #6b7280 !important;
}
.kise-form-panel input[type="email"],
.kise-form-panel input[type="password"],
.kise-form-panel input[type="text"] {
    background: #ffffff !important;
    border-color: #d1d5db !important;
    border-radius: 7px !important;
    color: #282F3B !important;
    font-size: 0.88rem !important;
}
.kise-form-panel input:focus {
    border-color: #29972E !important;
    box-shadow: 0 0 0 3px rgba(41,151,46,0.12) !important;
}
.kise-form-panel .fi-btn-color-primary,
.kise-form-panel [type="submit"],
.kise-form-panel .fi-btn {
    background: #282F3B !important;
    border-color: #282F3B !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    letter-spacing: 0.04em !important;
    border-radius: 7px !important;
    width: 100% !important;
}
.kise-form-panel .fi-btn-color-primary:hover {
    background: #1a2030 !important;
    border-color: #1a2030 !important;
}

.kise-form-footer {
    margin-top: auto;
    padding-top: 24px;
    border-top: 1px solid #e5e7eb;
    text-align: center;
}
.kise-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #e4e6e4;
    color: #282F3B;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 20px;
    margin-bottom: 10px;
}
.kise-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #29972E;
}
.kise-copy {
    font-size: 0.68rem;
    color: #9ca3af;
}

/* responsive */
@media (max-width: 768px) {
    .kise-login-wrap { flex-direction: column; }
    .kise-info-panel { width: 100%; padding: 28px 24px; }
    .kise-form-panel { width: 100%; min-width: unset; padding: 28px 24px; }
    .kise-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .kise-hero h1 { font-size: 1.5rem; }
}
</style>

    {{-- ── Left: Info Panel ── --}}
    <div class="kise-info-panel">
        <div class="kise-circle-mid"></div>

        {{-- Brand --}}
        <div class="kise-brand-row">
            <div class="kise-brand-mark">K</div>
            <div class="kise-brand-text-wrap">
                <div class="kise-brand-name">KISE HMIS</div>
                <div class="kise-brand-sub">Health Management System</div>
            </div>
        </div>

        {{-- Hero --}}
        <div class="kise-hero">
            <h1>Kenya Institute<br>of <span>Special Education</span></h1>
            <p>Facilitating service provision for persons with disabilities and special needs through research, assessment and training — now digitised end-to-end.</p>
        </div>

        {{-- KPI Grid --}}
        <div class="kise-kpi-grid">
            <div class="kise-kpi-card">
                <div class="kise-kpi-num">4+</div>
                <div class="kise-kpi-lbl">Branches</div>
            </div>
            <div class="kise-kpi-card">
                <div class="kise-kpi-num">7</div>
                <div class="kise-kpi-lbl">Clinical Stages</div>
            </div>
            <div class="kise-kpi-card">
                <div class="kise-kpi-num">12+</div>
                <div class="kise-kpi-lbl">Staff Roles</div>
            </div>
            <div class="kise-kpi-card">
                <div class="kise-kpi-num">UCI</div>
                <div class="kise-kpi-lbl">Client ID System</div>
            </div>
        </div>

        {{-- Visit Pipeline --}}
        <div class="kise-pipeline-wrap">
            <div class="kise-pipeline-label">Visit Workflow Pipeline</div>
            <div class="kise-pipeline-row">
                <div class="kise-stage-chip hl">Reception</div>
                <div class="kise-stage-arrow">›</div>
                <div class="kise-stage-chip">Triage</div>
                <div class="kise-stage-arrow">›</div>
                <div class="kise-stage-chip">Intake</div>
                <div class="kise-stage-arrow">›</div>
                <div class="kise-stage-chip">Billing</div>
                <div class="kise-stage-arrow">›</div>
                <div class="kise-stage-chip">Payment</div>
                <div class="kise-stage-arrow">›</div>
                <div class="kise-stage-chip">Service</div>
                <div class="kise-stage-arrow">›</div>
                <div class="kise-stage-chip hl-y">Done</div>
            </div>
        </div>

        {{-- About Strip --}}
        <div class="kise-about-strip">
            <div class="kise-about-item">
                <div class="kise-about-title">Mission</div>
                <div class="kise-about-val">Facilitating service provision for persons with disabilities through research, assessment &amp; training.</div>
            </div>
            <div class="kise-about-item">
                <div class="kise-about-title">This System</div>
                <div class="kise-about-val">End-to-end digital workflow — reception, triage, clinical intake, billing, payment and service delivery.</div>
            </div>
            <div class="kise-about-item">
                <div class="kise-about-title">Security</div>
                <div class="kise-about-val">Role-based access control. Branch-scoped data isolation. Full audit trail on every record.</div>
            </div>
        </div>
    </div>

    {{-- ── Right: Login Form Panel ── --}}
    <div class="kise-form-panel">

        <div class="kise-form-logo">
            <div class="kise-form-logo-mark">K</div>
            <div class="kise-form-logo-name">KISE HMIS</div>
        </div>

        <div class="kise-form-heading">Welcome back</div>
        <div class="kise-form-sub">Sign in to your account</div>

        <x-filament-panels::form wire:submit="authenticate">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="true"
            />
        </x-filament-panels::form>

        <div class="kise-form-footer">
            <div class="kise-status-badge">
                <div class="kise-status-dot"></div>
                System Online
            </div>
            <div class="kise-copy">© {{ date('Y') }} Kenya Institute of Special Education</div>
        </div>

    </div>

</div>
{{-- /kise-login-wrap (Livewire root) --}}
