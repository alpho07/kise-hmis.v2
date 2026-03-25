# Intake Assessment Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the monolithic 2449-line IntakeAssessment form with a sidebar-navigated, per-section autosaving Filament Page that renders one section at a time and tracks A–L completion in a `section_status` JSON column.

**Architecture:** A custom `Filament\Pages\Page` (`IntakeAssessmentEditor`) uses `HasForms` + `InteractsWithForms` with 11 named Filament forms (one per section B–L), each bound to `statePath('sectionData.X')`. Section A is a read-only client display. Autosave is triggered by an Alpine.js event-capture div that fires `$wire.saveSectionData(section)` on any input blur/change with a 1-second debounce. Section schemas are extracted from `IntakeAssessmentResource` as static methods. `finalize()` runs invoice creation and visit routing from already-persisted related model data.

**Tech Stack:** Laravel 12, Filament v3, Livewire v3 (`#[Url]`), Alpine.js (event capture + debounce), Tailwind CSS

**Spec:** `docs/superpowers/specs/2026-03-25-intake-assessment-editor-design.md`

**Section label note:** The existing `IntakeAssessmentResource` form has sections labeled B–L (B = ID & Contact through L = Summary). Section A in the editor is a new read-only client overview with no corresponding form in the resource. The spec's abstract A–L mapping is adapted here to the actual resource structure: plan A = spec A (client display), plan B = spec B (contact/ID), plan C = disability, plan D = socio-demo, plan E = medical history, plan F = education, plan G = functional screening, plan H = presenting concern, plan I = service plan, plan J = payment, plan K = deferral, plan L = summary. The spec's required-fields appendix is adapted to match these plan section labels.

---

## File Map

| Action | File | Responsibility |
|---|---|---|
| Create | `app/Filament/Pages/IntakeAssessmentEditor.php` | Page class: mount, section switch, 11 Filament forms, save methods, finalize |
| Create | `resources/views/filament/pages/intake-assessment-editor.blade.php` | Sidebar + header + content area + Alpine autosave wiring |
| Create | `database/migrations/2026_03_25_add_section_status_to_intake_assessments.php` | Add `section_status`, `is_finalized`, `finalized_at` columns |
| Modify | `app/Filament/Resources/IntakeAssessmentResource.php` | Extract section schemas as static methods; fix age resolution |
| Modify | `app/Filament/Resources/IntakeQueueResource.php` | Update `continue_intake` + `start_intake` URLs to editor |
| Create | `tests/Feature/IntakeAssessmentEditorTest.php` | Feature tests for mount, autosave, finalize |

---

## Task 1: Migration — add `section_status` columns

**Files:**
- Create: `database/migrations/2026_03_25_add_section_status_to_intake_assessments.php`
- Modify: `app/Models/IntakeAssessment.php`

- [ ] **Step 1: Write migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('intake_assessments', function (Blueprint $table) {
            $table->json('section_status')->nullable()->after('assessed_at');
            $table->boolean('is_finalized')->default(false)->after('section_status');
            $table->timestamp('finalized_at')->nullable()->after('is_finalized');
        });
    }
    public function down(): void
    {
        Schema::table('intake_assessments', function (Blueprint $table) {
            $table->dropColumn(['section_status', 'is_finalized', 'finalized_at']);
        });
    }
};
```

- [ ] **Step 2: Update `IntakeAssessment` model**

Add to `$fillable`:
```php
'section_status', 'is_finalized', 'finalized_at',
```
Add to `$casts`:
```php
'section_status' => 'array',
'is_finalized'   => 'boolean',
'finalized_at'   => 'datetime',
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```
Expected: `2026_03_25_add_section_status_to_intake_assessments .... DONE`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_03_25_add_section_status_to_intake_assessments.php app/Models/IntakeAssessment.php
git commit -m "feat: add section_status, is_finalized, finalized_at to intake_assessments"
```

---

## Task 2: Extract section schemas from IntakeAssessmentResource

The `form()` method (~lines 732–2410) contains all sections B–L in one block. Extract each into a public static method so the editor page can call them independently.

**Files:**
- Modify: `app/Filament/Resources/IntakeAssessmentResource.php`

- [ ] **Step 1: Fix `resolveClientAgeMonths` to accept explicit visitId (avoids `request()` in editor context)**

Replace the existing method body:
```php
public static function resolveClientAgeMonths(Get $get, ?int $explicitVisitId = null): int
{
    $visitId = $explicitVisitId ?? ($get('visit_id') ?: request()->query('visit'));
    if (!$visitId) return 9999;
    if (!isset(self::$_ageCache[$visitId])) {
        $dob = Visit::with('client')->find($visitId)?->client?->date_of_birth;
        self::$_ageCache[$visitId] = $dob ? (int) Carbon::parse($dob)->diffInMonths(now()) : 9999;
    }
    return self::$_ageCache[$visitId];
}
```

- [ ] **Step 2: Add public static schema methods for each section (B through L)**

Add the following 11 methods to `IntakeAssessmentResource`. Each extracts its section's schema from `form()` and returns a plain PHP array. Start with Section B and verify the existing form still renders before moving to the next section.

```php
/** Returns the form field array for Section B (ID & Contact). */
public static function sectionBSchema(?int $visitId = null): array
{
    // Move the Forms\Components inside Section::make('B — Client Identification...')
    // from form() into this array. Replace all request()->query('visit') with ($visitId).
    // DO NOT include the outer Section::make() wrapper — return the ->schema([...]) contents.
    return [
        // ... (copy fields from existing Section B schema)
    ];
}

public static function sectionCSchema(?int $visitId = null): array { return [/* Section C fields */]; }
public static function sectionDSchema(?int $visitId = null): array { return [/* Section D fields */]; }
public static function sectionESchema(?int $visitId = null): array { return [/* Sections E1-E5 fields */]; }
public static function sectionFSchema(?int $visitId = null): array { return [/* Section F fields */]; }

/** Section G includes the age_band_banner placeholder + all buildBandSection() calls */
public static function sectionGSchema(?int $visitId = null): array
{
    return [
        Forms\Components\Hidden::make('visit_id')->default($visitId),
        // ... age_band_banner placeholder
        // ... array_map(fn($bk, $band) => self::buildBandSection($bk, $band), ...)
    ];
}

public static function sectionHSchema(?int $visitId = null): array { return [/* Section H fields */]; }
public static function sectionISchema(?int $visitId = null): array { return [/* Section I fields */]; }
public static function sectionJSchema(?int $visitId = null): array { return [/* Section J fields */]; }
public static function sectionKSchema(?int $visitId = null): array { return [/* Section K fields */]; }
public static function sectionLSchema(?int $visitId = null): array { return [/* Section L fields */]; }
```

**Key: add `Forms\Components\Hidden::make('visit_id')->default($visitId)` inside Section G schema.** This injects the visit ID into the form state so that `$get('visit_id')` resolves correctly inside the age_band_banner closure and `buildBandSection()`.

- [ ] **Step 3: Update `form()` to call these static methods**

Replace each section's inline schema with a call to the static method:
```php
Forms\Components\Section::make('B — Client Identification & Contact Details')
    ->schema(self::sectionBSchema((int) request()->query('visit'))),
// ... repeat for C through L
```

- [ ] **Step 4: Verify existing create form still works**

Navigate to `/admin/intake-assessments/create?visit=1` — form must render all sections.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/IntakeAssessmentResource.php
git commit -m "refactor: extract section schemas as static methods in IntakeAssessmentResource"
```

---

## Task 3: Page class scaffold

**Files:**
- Create: `app/Filament/Pages/IntakeAssessmentEditor.php`

- [ ] **Step 1: Write the complete page class**

```php
<?php

namespace App\Filament\Pages;

use App\Filament\Resources\IntakeAssessmentResource;
use App\Models\AssessmentAutoReferral;
use App\Models\Client;
use App\Models\ClientDisability;
use App\Models\ClientEducation;
use App\Models\ClientMedicalHistory;
use App\Models\ClientSocioDemographic;
use App\Models\Department;
use App\Models\FunctionalScreening;
use App\Models\IntakeAssessment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\Visit;
use Carbon\Carbon;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class IntakeAssessmentEditor extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.intake-assessment-editor';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Intake Assessment Editor';

    #[Url]
    public int $intakeId = 0;

    public string $activeSection = 'A';
    public array $sectionStatus  = [];
    public bool  $isSaving       = false;

    public ?IntakeAssessment $intake = null;
    public ?Client $client = null;

    /** One data bag per section — Filament forms bind to sectionData.X */
    public array $sectionData = [];

    protected array $sections = ['A','B','C','D','E','F','G','H','I','J','K','L'];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['intake_officer', 'admin', 'super_admin']) ?? false;
    }

    public function mount(): void
    {
        abort_unless($this->intakeId > 0, 404);
        abort_unless(static::canAccess(), 403);

        $this->intake = IntakeAssessment::with([
            'visit.branch', 'client.county', 'client.subCounty', 'functionalScreening',
        ])->findOrFail($this->intakeId);

        $this->client = $this->intake->client;

        $this->sectionStatus = array_merge(
            array_fill_keys($this->sections, 'incomplete'),
            $this->intake->section_status ?? []
        );

        // Section A always auto-completes when client is linked
        if ($this->intake->client_id) {
            $this->sectionStatus['A'] = 'complete';
        }

        $this->fillSectionData();
    }

    protected function fillSectionData(): void
    {
        $intake = $this->intake;
        $client = $this->client;
        $visit  = $intake->visit;
        $sr     = $intake->services_required ?? [];

        $this->sectionData['B'] = [
            'verification_mode'         => $intake->verification_mode,
            'verification_notes'        => $intake->verification_notes,
            'b_national_id'             => $client->national_id,
            'b_birth_certificate'       => $client->birth_certificate_number,
            'b_phone_primary'           => $client->phone_primary,
            'b_phone_secondary'         => $client->phone_secondary,
            'b_preferred_communication' => $client->preferred_communication,
            'b_consent_to_sms'          => $client->consent_to_sms,
            'b_sha_number'              => $client->sha_number,
            'b_ncpwd_number'            => $client->ncpwd_number,
            'b_county_id'               => $client->county_id,
            'b_sub_county_id'           => $client->sub_county_id,
            'b_ward_id'                 => $client->ward_id,
            'b_primary_address'         => $client->primary_address,
            'b_landmark'                => $client->landmark,
        ];

        $disability = ClientDisability::where('client_id', $client->id)->first();
        $this->sectionData['C'] = $disability ? [
            'dis_is_disability_known'   => $disability->is_disability_known,
            'dis_disability_categories' => $disability->disability_categories ?? [],
            'dis_onset'                 => $disability->onset,
            'dis_level_of_functioning'  => $disability->level_of_functioning,
            'e2_current_devices'        => $disability->assistive_technology ?? [],
            'dis_disability_notes'      => $disability->disability_notes,
        ] : [];

        $socio = ClientSocioDemographic::where('client_id', $client->id)->first();
        $this->sectionData['D'] = $socio ? [
            'socio_marital_status'        => $socio->marital_status,
            'socio_living_arrangement'    => $socio->living_arrangement,
            'socio_household_size'        => $socio->household_size,
            'socio_primary_caregiver'     => $socio->primary_caregiver,
            'socio_source_of_support'     => $socio->source_of_support ?? [],
            'socio_primary_language'      => $socio->primary_language,
            'socio_other_languages'       => $socio->other_languages[0] ?? null,
            'socio_accessibility_at_home' => $socio->accessibility_at_home,
            'socio_notes'                 => $socio->socio_notes,
        ] : [];

        $med = ClientMedicalHistory::where('client_id', $client->id)->first();
        $this->sectionData['E'] = $med ? [
            'med_medical_conditions'     => $med->medical_conditions ?? [],
            'med_current_medications'    => $med->current_medications,
            'med_surgical_history'       => $med->surgical_history,
            'med_family_medical_history' => $med->family_medical_history,
            'med_immunization_status'    => $med->immunization_status,
            'med_previous_assessments'   => $med->previous_assessments ?? [],
            'peri_developmental_concerns'=> $med->developmental_concerns ?? [],
        ] : [];

        $edu = ClientEducation::where('client_id', $client->id)->first();
        $this->sectionData['F'] = $edu ? [
            'edu_education_level'    => $edu->education_level,
            'edu_school_type'        => $edu->school_type,
            'edu_school_name'        => $edu->school_name,
            'edu_grade_level'        => $edu->grade_level,
            'edu_currently_enrolled' => $edu->currently_enrolled ? 'yes' : 'no',
            'edu_employment_status'  => $edu->employment_status,
            'edu_occupation_type'    => $edu->occupation_type,
        ] : [];

        $scores = $intake->functional_screening_scores ?? [];
        $this->sectionData['G'] = array_merge(
            ['visit_id' => $intake->visit_id, 'func_overall_summary' => $intake->functionalScreening?->overall_summary],
            $this->flattenScreeningAnswers($scores)
        );

        $this->sectionData['H'] = [
            'referral_source'        => $sr['referral_source'] ?? [],
            'referral_contact'       => $sr['referral_contact'] ?? null,
            'reason_for_visit'       => $intake->reason_for_visit,
            'current_concerns'       => $intake->current_concerns,
            'previous_interventions' => $intake->previous_interventions,
        ];

        $this->sectionData['I'] = [
            'visit_id'             => $intake->visit_id,
            'i_primary_service_id' => $sr['primary_service_id'] ?? null,
            'i_service_categories' => $sr['service_categories'] ?? [],
            'services_selected'    => $sr['service_ids'] ?? [],
            'priority_level'       => $intake->priority_level,
        ];

        $this->sectionData['J'] = [
            'expected_payment_method' => $sr['payment_method'] ?? null,
            'sha_enrolled'            => $sr['sha_enrolled'] ?? false,
            'ncpwd_covered'           => $sr['ncpwd_covered'] ?? false,
            'has_private_insurance'   => $sr['has_insurance'] ?? false,
            'payment_notes'           => $sr['payment_notes'] ?? null,
        ];

        $this->sectionData['K'] = [
            'defer_client'          => $visit->status === 'deferred',
            'deferral_reason'       => $visit->deferral_reason,
            'deferral_notes'        => $visit->deferral_notes,
            'next_appointment_date' => $visit->next_appointment_date,
        ];

        $this->sectionData['L'] = [
            'assessment_summary' => $intake->intake_summary,
            'recommendations'    => $intake->recommendations,
            'priority_level'     => $intake->priority_level,
            'data_verified'      => $intake->data_verified,
        ];
    }

    protected function flattenScreeningAnswers(array $scores): array
    {
        $flat = [];
        $band = $scores['band'] ?? null;
        if (!$band) return $flat;
        foreach ($scores['answers'] ?? [] as $domain => $questions) {
            foreach ($questions as $qKey => $answer) {
                $flat[$qKey === '_notes' ? "g_{$band}_{$domain}_notes" : "g_{$band}_{$domain}_{$qKey}"] = $answer;
            }
        }
        return $flat;
    }

    public function getForms(): array
    {
        return ['sectionBForm','sectionCForm','sectionDForm','sectionEForm','sectionFForm',
                'sectionGForm','sectionHForm','sectionIForm','sectionJForm','sectionKForm','sectionLForm'];
    }

    public function sectionBForm(Form $form): Form
    {
        return $form->statePath('sectionData.B')->schema(IntakeAssessmentResource::sectionBSchema($this->intake?->visit_id));
    }
    public function sectionCForm(Form $form): Form
    {
        return $form->statePath('sectionData.C')->schema(IntakeAssessmentResource::sectionCSchema($this->intake?->visit_id));
    }
    public function sectionDForm(Form $form): Form
    {
        return $form->statePath('sectionData.D')->schema(IntakeAssessmentResource::sectionDSchema($this->intake?->visit_id));
    }
    public function sectionEForm(Form $form): Form
    {
        return $form->statePath('sectionData.E')->schema(IntakeAssessmentResource::sectionESchema($this->intake?->visit_id));
    }
    public function sectionFForm(Form $form): Form
    {
        return $form->statePath('sectionData.F')->schema(IntakeAssessmentResource::sectionFSchema($this->intake?->visit_id));
    }
    public function sectionGForm(Form $form): Form
    {
        return $form->statePath('sectionData.G')->schema(IntakeAssessmentResource::sectionGSchema($this->intake?->visit_id));
    }
    public function sectionHForm(Form $form): Form
    {
        return $form->statePath('sectionData.H')->schema(IntakeAssessmentResource::sectionHSchema($this->intake?->visit_id));
    }
    public function sectionIForm(Form $form): Form
    {
        return $form->statePath('sectionData.I')->schema(IntakeAssessmentResource::sectionISchema($this->intake?->visit_id));
    }
    public function sectionJForm(Form $form): Form
    {
        return $form->statePath('sectionData.J')->schema(IntakeAssessmentResource::sectionJSchema($this->intake?->visit_id));
    }
    public function sectionKForm(Form $form): Form
    {
        return $form->statePath('sectionData.K')->schema(IntakeAssessmentResource::sectionKSchema($this->intake?->visit_id));
    }
    public function sectionLForm(Form $form): Form
    {
        return $form->statePath('sectionData.L')->schema(IntakeAssessmentResource::sectionLSchema($this->intake?->visit_id));
    }

    public function switchSection(string $section): void
    {
        $this->activeSection = $section;
    }

    public function prevSection(): void
    {
        $idx = array_search($this->activeSection, $this->sections);
        if ($idx > 0) $this->activeSection = $this->sections[$idx - 1];
    }

    public function nextSection(): void
    {
        $idx = array_search($this->activeSection, $this->sections);
        if ($idx < count($this->sections) - 1) $this->activeSection = $this->sections[$idx + 1];
    }

    /**
     * Called by Alpine.js capture div in the blade when any input blurs/changes.
     * Alpine wraps each section form with a 1s debounced listener that calls this method.
     */
    public function saveSectionData(string $section): void
    {
        if (!in_array($section, $this->sections) || $section === 'A') return;
        $this->isSaving = true;
        try {
            $method = 'saveSection' . $section;
            if (method_exists($this, $method)) {
                $this->$method($this->sectionData[$section] ?? []);
            }
            $this->updateSectionStatus($section);
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Autosave failed')
                ->body("Section {$section} could not be saved. Please try again.")
                ->send();
        } finally {
            $this->isSaving = false;
        }
    }

    protected function updateSectionStatus(string $section): void
    {
        $status = $this->computeSectionStatus($section);
        DB::transaction(function () use ($section, $status) {
            $this->intake->refresh();
            $updated = array_merge($this->intake->section_status ?? [], [$section => $status]);
            $this->intake->update(['section_status' => $updated]);
            $this->sectionStatus[$section] = $status;
        });
    }

    protected function computeSectionStatus(string $section): string
    {
        $data     = $this->sectionData[$section] ?? [];
        $required = $this->requiredFields()[$section] ?? [];
        if (empty($required)) return 'complete';
        foreach ($required as $field) {
            $val = $data[$field] ?? null;
            if ($val === null || $val === '' || $val === []) return 'in_progress';
        }
        return 'complete';
    }

    protected function requiredFields(): array
    {
        return [
            'A' => [],
            'B' => ['verification_mode'],
            'C' => ['dis_is_disability_known'],
            'D' => ['socio_marital_status', 'socio_primary_language'],
            'E' => ['med_medical_conditions'],
            'F' => ['edu_education_level'],
            'G' => ['func_overall_summary'],
            'H' => ['reason_for_visit'],
            'I' => ['i_primary_service_id'],
            'J' => ['expected_payment_method'],
            'K' => [],  // deferral is optional; auto-completes on first save
            'L' => ['assessment_summary'],
        ];
    }

    public function getProgressProperty(): int
    {
        return count(array_filter($this->sectionStatus, fn($s) => $s === 'complete'));
    }

    public function getSectionLabelProperty(): array
    {
        return [
            'A' => 'Client Overview',   'B' => 'ID & Contact',
            'C' => 'Disability',        'D' => 'Socio-Demographics',
            'E' => 'Medical History',   'F' => 'Education',
            'G' => 'Funct. Screening',  'H' => 'Presenting Concern',
            'I' => 'Service Plan',      'J' => 'Payment',
            'K' => 'Deferral',          'L' => 'Summary',
        ];
    }
}
```

- [ ] **Step 2: Verify PHP syntax**

```bash
php artisan route:list 2>&1 | head -5
```
Expected: no syntax errors. If errors appear, fix them before proceeding.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Pages/IntakeAssessmentEditor.php
git commit -m "feat: add IntakeAssessmentEditor page class"
```

---

## Task 4: Blade layout

**Files:**
- Create: `resources/views/filament/pages/intake-assessment-editor.blade.php`

- [ ] **Step 1: Write the blade template**

```blade
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
                                ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold border-l-3 border-indigo-600'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 border-l-3 border-transparent' }}">
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

                    {{-- Sections B-L: wrapped in Alpine debounce capture for autosave --}}
                    @php $sectionMap = ['B','C','D','E','F','G','H','I','J','K','L']; @endphp

                    @if($activeSection === 'A')
                        {{-- Section A: read-only client display --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            @foreach([
                                'Full Name'    => $this->client?->full_name,
                                'UCI'          => $this->client?->uci,
                                'Date of Birth'=> $this->client?->date_of_birth?->format('d M Y'),
                                'Gender'       => ucfirst($this->client?->gender ?? '—'),
                                'County'       => $this->client?->county?->name ?? '—',
                                'Visit Number' => $this->intake?->visit?->visit_number ?? '—',
                            ] as $label => $value)
                                <div class="flex gap-2">
                                    <span class="text-gray-500 w-28 flex-shrink-0">{{ $label }}:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $value ?? '—' }}</span>
                                </div>
                            @endforeach
                        </div>

                    @elseif(in_array($activeSection, $sectionMap))
                        {{--
                            Alpine.js captures blur and change events from ALL child inputs.
                            Debounce: 1000ms after last event fires $wire.saveSectionData(section).
                            This works regardless of Filament's internal form wiring.
                        --}}
                        <div
                            x-data="{ saveTimer: null }"
                            @blur.capture="clearTimeout(saveTimer); saveTimer = setTimeout(() => $wire.saveSectionData('{{ $activeSection }}'), 1000)"
                            @change.capture="clearTimeout(saveTimer); saveTimer = setTimeout(() => $wire.saveSectionData('{{ $activeSection }}'), 1000)"
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
                                    <x-filament::button
                                        wire:click="finalize"
                                        color="{{ $allComplete ? 'success' : 'gray' }}"
                                        :disabled="!$allComplete"
                                        size="xl"
                                        wire:loading.attr="disabled"
                                        wire:target="finalize"
                                    >
                                        <wire:loading wire:target="finalize">
                                            <x-filament::loading-indicator class="h-4 w-4 mr-2"/>
                                        </wire:loading>
                                        Finalize Assessment →
                                    </x-filament::button>
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
```

- [ ] **Step 2: Verify page loads**

Navigate to `/admin/intake-assessment-editor?intakeId=1` — page should render without errors.

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/pages/intake-assessment-editor.blade.php
git commit -m "feat: add intake assessment editor blade layout with Alpine autosave"
```

---

## Task 5: Section save methods B, C, D, F

**Files:**
- Modify: `app/Filament/Pages/IntakeAssessmentEditor.php`

- [ ] **Step 1: Add save methods to the page class**

```php
protected function saveSectionB(array $data): void
{
    $clientUpdates = array_filter([
        'national_id'              => $data['b_national_id']              ?? null,
        'birth_certificate_number' => $data['b_birth_certificate']        ?? null,
        'phone_primary'            => $data['b_phone_primary']            ?? null,
        'phone_secondary'          => $data['b_phone_secondary']          ?? null,
        'preferred_communication'  => $data['b_preferred_communication']  ?? null,
        'consent_to_sms'           => isset($data['b_consent_to_sms']) ? (bool) $data['b_consent_to_sms'] : null,
        'sha_number'               => $data['b_sha_number']               ?? null,
        'ncpwd_number'             => $data['b_ncpwd_number']             ?? null,
        'county_id'                => $data['b_county_id']                ?? null,
        'sub_county_id'            => $data['b_sub_county_id']            ?? null,
        'ward_id'                  => $data['b_ward_id']                  ?? null,
        'primary_address'          => $data['b_primary_address']          ?? null,
        'landmark'                 => $data['b_landmark']                 ?? null,
    ], fn($v) => $v !== null);

    if ($clientUpdates) $this->client->update($clientUpdates);

    $this->intake->update([
        'verification_mode'  => $data['verification_mode']  ?? null,
        'verification_notes' => $data['verification_notes'] ?? null,
    ]);
}

protected function saveSectionC(array $data): void
{
    if (empty($data['dis_is_disability_known'])) return;
    $atDevices = array_map(function ($device) {
        if (($device['device_type'] ?? null) === 'other' && !empty($device['device_type_other'])) {
            $device['device_type'] = 'other: ' . $device['device_type_other'];
        }
        if (($device['source'] ?? null) === 'other' && !empty($device['source_other'])) {
            $device['source'] = 'other: ' . $device['source_other'];
        }
        return $device;
    }, $data['e2_current_devices'] ?? []);

    ClientDisability::updateOrCreate(
        ['client_id' => $this->client->id],
        [
            'is_disability_known'        => true,
            'disability_categories'      => $data['dis_disability_categories'] ?? [],
            'onset'                      => $data['dis_onset']                 ?? null,
            'level_of_functioning'       => $data['dis_level_of_functioning']  ?? null,
            'assistive_technology'       => $atDevices,
            'assistive_technology_notes' => $data['dis_disability_notes']      ?? null,
            'disability_notes'           => $data['dis_disability_notes']      ?? null,
        ]
    );
    if (($data['dis_ncpwd_registered'] ?? null) === 'yes' && !empty($data['dis_ncpwd_number'])) {
        $this->client->update(['ncpwd_number' => $data['dis_ncpwd_number']]);
    }
}

protected function saveSectionD(array $data): void
{
    $maritalStatus = ($data['socio_marital_status'] ?? null) === 'other'
        ? 'other: ' . ($data['socio_marital_other'] ?? 'unspecified')
        : ($data['socio_marital_status'] ?? null);
    $primaryLanguage = ($data['socio_primary_language'] ?? null) === 'other'
        ? 'other: ' . ($data['socio_language_other'] ?? 'unspecified')
        : ($data['socio_primary_language'] ?? null);

    ClientSocioDemographic::updateOrCreate(
        ['client_id' => $this->client->id],
        [
            'marital_status'        => $maritalStatus,
            'living_arrangement'    => $data['socio_living_arrangement']    ?? null,
            'household_size'        => $data['socio_household_size']        ?? null,
            'primary_caregiver'     => $data['socio_primary_caregiver']     ?? null,
            'source_of_support'     => $data['socio_source_of_support']     ?? [],
            'primary_language'      => $primaryLanguage,
            'other_languages'       => $data['socio_other_languages'] ? [$data['socio_other_languages']] : [],
            'accessibility_at_home' => $data['socio_accessibility_at_home'] ?? null,
            'socio_notes'           => $data['socio_notes']                 ?? null,
        ]
    );
}

protected function saveSectionF(array $data): void
{
    $employmentStatus = $data['edu_employment_status'] ?? null;
    if ($employmentStatus === 'other' && !empty($data['edu_employment_status_other'])) {
        $employmentStatus = 'other: ' . $data['edu_employment_status_other'];
    }
    ClientEducation::updateOrCreate(
        ['client_id' => $this->client->id],
        [
            'education_level'       => $data['edu_education_level']       ?? null,
            'school_type'           => $data['edu_school_type']           ?? null,
            'school_name'           => $data['edu_school_name']           ?? null,
            'grade_level'           => $data['edu_grade_level']           ?? null,
            'currently_enrolled'    => ($data['edu_currently_enrolled']   ?? null) === 'yes',
            'attendance_challenges' => ($data['edu_attendance_challenges'] ?? null) === 'yes',
            'attendance_notes'      => $data['edu_attendance_notes']      ?? null,
            'performance_concern'   => ($data['edu_performance_concern']  ?? null) === 'yes',
            'performance_notes'     => $data['edu_performance_notes']     ?? null,
            'employment_status'     => $employmentStatus,
            'occupation_type'       => $data['edu_occupation_type']       ?? null,
        ]
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Pages/IntakeAssessmentEditor.php
git commit -m "feat: add section save methods B, C, D, F"
```

---

## Task 6: Section E save method (medical history)

- [ ] **Step 1: Add `saveSectionE()` to the page class**

```php
protected function saveSectionE(array $data): void
{
    $medConditions = $data['med_medical_conditions'] ?? [];
    if (!empty($data['med_conditions_other'])) {
        $medConditions[] = 'other: ' . $data['med_conditions_other'];
    }

    $prevAssessments = $data['med_previous_assessments'] ?? [];
    if (!empty($data['med_previous_assessments_other'])) {
        $prevAssessments[] = 'other: ' . $data['med_previous_assessments_other'];
    }

    $devConcerns = array_map(
        fn($v) => $v === 'other' && !empty($data['peri_developmental_concerns_other'])
            ? 'other: ' . $data['peri_developmental_concerns_other'] : $v,
        $data['peri_developmental_concerns'] ?? []
    );

    $feedingConcernsRaw = $data['feeding_swallowing_concerns'] ?? [];
    if (in_array('other', $feedingConcernsRaw, true) && !empty($data['feeding_swallowing_concerns_other'])) {
        $feedingConcernsRaw = array_filter($feedingConcernsRaw, fn($v) => $v !== 'other');
        $feedingConcernsRaw[] = 'other: ' . $data['feeding_swallowing_concerns_other'];
    }
    $feedingHistory = array_filter([
        'feeding_method'      => $data['feeding_method']          ?? null,
        'diet_appetite'       => $data['feeding_diet_appetite']   ?? null,
        'swallowing_concerns' => $feedingConcernsRaw ?: null,
        'growth_concern'      => $data['feeding_growth_concern']  ?? null,
        'nutrition_notes'     => $data['feeding_nutrition_notes'] ?? null,
    ], fn($v) => $v !== null && $v !== []);

    ClientMedicalHistory::updateOrCreate(
        ['client_id' => $this->client->id],
        [
            'medical_conditions'           => $medConditions ?: null,
            'current_medications'          => $data['med_current_medications']    ?? null,
            'surgical_history'             => $data['med_surgical_history']       ?? null,
            'family_medical_history'       => $data['med_family_medical_history'] ?? null,
            'immunization_status'          => $data['med_immunization_status']    ?? null,
            'feeding_history'              => $feedingHistory ?: null,
            'previous_assessments'         => $prevAssessments,
            'developmental_concerns'       => $devConcerns,
            'developmental_concerns_notes' => $data['developmental_history']      ?? null,
            'assistive_devices_history'    => $data['e2_previous_devices']        ?? [],
        ]
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Pages/IntakeAssessmentEditor.php
git commit -m "feat: add section E (medical history) save method"
```

---

## Task 7: Section G save method (functional screening)

**Important:** Do NOT create `AssessmentAutoReferral` records here. Referral creation is deferred to `finalize()` to prevent duplicates on every autosave.

- [ ] **Step 1: Add `saveSectionG()` to the page class**

```php
protected function saveSectionG(array $data): void
{
    // Resolve age directly from client DOB — avoids the Get $get dependency
    $client    = $this->client;
    $ageMonths = $client->date_of_birth
        ? (int) Carbon::parse($client->date_of_birth)->diffInMonths(now())
        : 9999;
    $bandKey = IntakeAssessmentResource::detectBandKey($ageMonths);

    // Rebuild screening answers from flat field names back into band→domain→question structure
    $screeningAnswers = [];
    $allQuestions = IntakeAssessmentResource::screeningQuestions();
    if (isset($allQuestions[$bandKey])) {
        foreach ($allQuestions[$bandKey]['domains'] as $domainKey => $domain) {
            foreach ($domain['questions'] as $qKey => $q) {
                $fieldName = "g_{$bandKey}_{$domainKey}_{$qKey}";
                $screeningAnswers[$domainKey][$qKey] = $data[$fieldName] ?? null;
            }
            $notesField = "g_{$bandKey}_{$domainKey}_notes";
            if (!empty($data[$notesField])) {
                $screeningAnswers[$domainKey]['_notes'] = $data[$notesField];
            }
        }
    }

    $scores = ['band' => $bandKey, 'age_months' => $ageMonths, 'answers' => $screeningAnswers];

    $this->intake->update(['functional_screening_scores' => $scores]);

    FunctionalScreening::updateOrCreate(
        ['intake_assessment_id' => $this->intake->id],
        [
            'client_id'         => $this->client->id,
            'age_band'          => $bandKey,
            'screening_answers' => $screeningAnswers,
            'overall_summary'   => $data['func_overall_summary'] ?? null,
        ]
    );
    // NOTE: Auto-referrals from functional screening are created only in finalize()
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Pages/IntakeAssessmentEditor.php
git commit -m "feat: add section G (functional screening) save method, defer referrals to finalize"
```

---

## Task 8: Sections H, I, J, K, L save methods

- [ ] **Step 1: Add remaining save methods**

```php
protected function saveSectionH(array $data): void
{
    $sources = $data['referral_source'] ?? [];
    if (in_array('other', $sources, true) && !empty($data['referral_source_other'])) {
        $sources = array_filter($sources, fn($v) => $v !== 'other');
        $sources[] = 'other: ' . $data['referral_source_other'];
    }
    $sr = $this->intake->services_required ?? [];
    $sr['referral_source']  = array_values($sources);
    $sr['referral_contact'] = $data['referral_contact'] ?? null;
    $this->intake->update([
        'reason_for_visit'       => $data['reason_for_visit']       ?? null,
        'current_concerns'       => $data['current_concerns']       ?? null,
        'previous_interventions' => $data['previous_interventions'] ?? null,
        'services_required'      => $sr,
    ]);
}

protected function saveSectionI(array $data): void
{
    $sr = $this->intake->services_required ?? [];
    $sr['primary_service_id'] = $data['i_primary_service_id'] ?? null;
    $sr['service_categories'] = $data['i_service_categories'] ?? [];
    $sr['service_ids']        = $data['services_selected']    ?? [];
    $this->intake->update([
        'services_required' => $sr,
        'priority_level'    => (int) ($data['priority_level'] ?? 3),
    ]);
}

protected function saveSectionJ(array $data): void
{
    $sr = $this->intake->services_required ?? [];
    $sr['payment_method'] = $data['expected_payment_method']   ?? null;
    $sr['sha_enrolled']   = (bool) ($data['sha_enrolled']      ?? false);
    $sr['ncpwd_covered']  = (bool) ($data['ncpwd_covered']     ?? false);
    $sr['has_insurance']  = (bool) ($data['has_private_insurance'] ?? false);
    $sr['payment_notes']  = $data['payment_notes']             ?? null;
    $this->intake->update(['services_required' => $sr]);
}

protected function saveSectionK(array $data): void
{
    if (!empty($data['defer_client'])) {
        $deferralReason = $data['deferral_reason'] ?? null;
        if ($deferralReason === 'other' && !empty($data['deferral_reason_other'])) {
            $deferralReason = 'other: ' . $data['deferral_reason_other'];
        }
        $this->intake->visit->update([
            'status'                => 'deferred',
            'next_appointment_date' => $data['next_appointment_date'] ?? null,
            'deferral_reason'       => $deferralReason,
            'deferral_notes'        => $data['deferral_notes']        ?? null,
        ]);
    }
    // Section K has no required fields — always auto-completes (updateSectionStatus called by saveSectionData)
}

protected function saveSectionL(array $data): void
{
    $this->intake->update([
        'intake_summary'  => $data['assessment_summary'] ?? null,
        'recommendations' => $data['recommendations']    ?? null,
        'priority_level'  => (int) ($data['priority_level'] ?? 3),
        'data_verified'   => (bool) ($data['data_verified'] ?? false),
    ]);
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Pages/IntakeAssessmentEditor.php
git commit -m "feat: add section save methods H, I, J, K, L"
```

---

## Task 9: finalize() method

- [ ] **Step 1: Add `finalize()` to the page class**

```php
public function finalize(): void
{
    // Guard: all sections complete
    $incomplete = array_keys(array_filter($this->sectionStatus, fn($s) => $s !== 'complete'));
    if (!empty($incomplete)) {
        Notification::make()->danger()
            ->title('Cannot finalize')
            ->body('Incomplete sections: ' . implode(', ', $incomplete))
            ->send();
        return;
    }

    // Guard: deferred visits cannot be finalized
    if ($this->intake->visit->status === 'deferred') {
        Notification::make()->warning()
            ->title('Visit is deferred')
            ->body('Resolve the deferral before finalizing.')
            ->send();
        return;
    }

    DB::transaction(function () {
        $intake  = $this->intake->refresh()->load(['visit.branch', 'functionalScreening']);
        $visit   = $intake->visit;
        $client  = $this->client;
        $sr      = $intake->services_required ?? [];
        $scores  = $intake->functional_screening_scores ?? [];

        // ── Auto-referrals from functional screening ──────────────────────────
        $bandKey          = $scores['band'] ?? null;
        $screeningAnswers = $scores['answers'] ?? [];

        if ($bandKey && isset(IntakeAssessmentResource::screeningQuestions()[$bandKey])) {
            $allQuestions = IntakeAssessmentResource::screeningQuestions();
            $bandDomains  = $allQuestions[$bandKey]['domains'];
            $domainSvcMap = IntakeAssessmentResource::screeningDomainServiceMap();
            $deptNames    = array_unique(array_column($domainSvcMap, 'department'));
            $deptIds      = Department::whereIn('name', $deptNames)->pluck('id', 'name')->all();
            $deptMinPrice = Service::where('is_active', true)
                ->whereIn('department_id', array_values($deptIds))
                ->selectRaw('department_id, MIN(base_price) as min_price')
                ->groupBy('department_id')
                ->pluck('min_price', 'department_id')
                ->all();
            $servicePricesByDept = [];
            foreach ($deptIds as $deptName => $deptId) {
                if (isset($deptMinPrice[$deptId])) $servicePricesByDept[$deptName] = $deptMinPrice[$deptId];
            }

            $createdSvcPoints = [];
            foreach ($bandDomains as $domainKey => $domain) {
                $route = $domainSvcMap[$domainKey] ?? null;
                if (!$route) continue;
                $spSlug = $route['service_point'];
                if (isset($createdSvcPoints[$spSlug])) continue;
                $domainAnswers = $screeningAnswers[$domainKey] ?? [];
                $flagged       = [];
                $highPriority  = false;
                foreach ($domain['questions'] as $qKey => $q) {
                    $answer = $domainAnswers[$qKey] ?? null;
                    if ($answer === $q['flag']) {
                        $flagged[] = $q['text'];
                        if (($q['priority'] ?? 'routine') === 'high') $highPriority = true;
                    }
                }
                if (empty($flagged)) continue;
                $createdSvcPoints[$spSlug] = true;
                AssessmentAutoReferral::create([
                    'form_response_id' => null,
                    'client_id'        => $client->id,
                    'visit_id'         => $visit->id,
                    'service_point'    => $spSlug,
                    'department'       => $route['department'],
                    'priority'         => $highPriority ? 'high' : 'routine',
                    'reason'           => "Functional screening — {$domain['label']} (band: {$bandKey}): " . implode('; ', $flagged),
                    'trigger_data'     => [
                        'domain' => $domainKey, 'band' => $bandKey, 'flagged_questions' => $flagged,
                        'estimated_cost' => $servicePricesByDept[$route['department']] ?? null,
                        'source' => 'intake_functional_screening',
                    ],
                    'status' => 'pending',
                ]);
            }
        }

        // ── Service Bookings — delete any from a prior failed finalize, then recreate ─
        ServiceBooking::where('visit_id', $visit->id)->where('source', 'intake')->delete();

        $primaryServiceId = $sr['primary_service_id'] ?? null;
        $allServiceIds    = array_unique(array_filter(array_merge(
            $primaryServiceId ? [$primaryServiceId] : [], $sr['service_ids'] ?? []
        )));
        foreach (Service::whereIn('id', $allServiceIds)->get() as $service) {
            $isPrimary = $service->id == $primaryServiceId;
            ServiceBooking::create([
                'visit_id'       => $visit->id,
                'client_id'      => $client->id,
                'service_id'     => $service->id,
                'department_id'  => $service->department_id,
                'booking_type'   => $isPrimary ? 'primary' : 'cross_posting',
                'booking_date'   => today(),
                'payment_status' => 'pending',
                'service_status' => 'scheduled',
                'priority_level' => $isPrimary ? 1 : ($intake->priority_level ?? 3),
                'priority'       => $isPrimary ? 'urgent' : match ((int) ($intake->priority_level ?? 3)) {
                    1 => 'urgent', 2 => 'high', default => 'routine',
                },
                'booked_by' => Auth::id(),
                'source'    => 'intake',
                'status'    => 'pending',
            ]);
        }

        // ── Invoice ───────────────────────────────────────────────────────────
        $paymentMethod = $sr['payment_method'] ?? 'cash';
        $hasSponsor    = in_array($paymentMethod, ['sha', 'ncpwd', 'insurance_private', 'waiver', 'combination'], true);
        $branchCode    = $visit->branch ? strtoupper(substr($visit->branch->name, 0, 3)) : 'HQ';
        $invYear       = now()->format('Y');
        $invMonth      = now()->format('m');
        $invSeq        = Invoice::whereYear('created_at', $invYear)->whereMonth('created_at', $invMonth)->count() + 1;
        $invoiceNumber = "{$branchCode}/INV/{$invYear}/{$invMonth}/" . str_pad($invSeq, 4, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'visit_id'             => $visit->id,
            'client_id'            => $client->id,
            'branch_id'            => $visit->branch_id,
            'invoice_number'       => $invoiceNumber,
            'payment_method'       => $paymentMethod,
            'has_sponsor'          => $hasSponsor,
            'status'               => 'pending',
            'issued_by'            => Auth::id(),
            'payment_notes'        => $sr['payment_notes'] ?? null,
            'total_amount'         => 0,
            'total_sponsor_amount' => 0,
            'total_client_amount'  => 0,
        ]);

        $clientRatio = match ($paymentMethod) {
            'sha' => 0.20, 'ncpwd' => 0.10, 'waiver' => 0.00,
            'insurance_private' => 0.30, 'combination' => 0.50, default => 1.00,
        };
        $total = $totalSponsor = $totalClient = 0.0;

        $bookings = ServiceBooking::where('visit_id', $visit->id)->where('source', 'intake')->with('service')->get();
        foreach ($bookings as $booking) {
            $base        = (float) ($booking->service?->base_price ?? 0);
            $clientPays  = round($base * $clientRatio, 2);
            $sponsorPays = round($base - $clientPays, 2);
            $item = InvoiceItem::create([
                'invoice_id'            => $invoice->id,
                'service_id'            => $booking->service_id,
                'department_id'         => $booking->department_id,
                'description'           => $booking->service?->name ?? 'Service',
                'quantity'              => 1,
                'unit_price'            => $base,
                'subtotal'              => $base,
                'sponsor_type'          => $hasSponsor ? $paymentMethod : null,
                'sponsor_percentage'    => $hasSponsor ? round((1 - $clientRatio) * 100, 2) : 0,
                'sponsor_amount'        => $sponsorPays,
                'client_amount'         => $clientPays,
                'client_payment_status' => 'pending',
                'sponsor_claim_status'  => $hasSponsor ? 'pending' : null,
            ]);
            $booking->update(['invoice_item_id' => $item->id]);
            $total += $base; $totalSponsor += $sponsorPays; $totalClient += $clientPays;
        }
        $invoice->update([
            'total_amount'         => $total,
            'total_sponsor_amount' => $totalSponsor,
            'total_client_amount'  => $totalClient,
            'has_sponsor'          => $totalSponsor > 0,
        ]);

        // ── Mark finalized ────────────────────────────────────────────────────
        $intake->update([
            'assessed_by'  => Auth::id(),
            'assessed_at'  => now(),
            'is_finalized' => true,
            'finalized_at' => now(),
        ]);

        // ── Route visit ───────────────────────────────────────────────────────
        $visit->completeStage();
        if ($hasSponsor) {
            $visit->moveToStage('billing');
            $routeLabel = 'Payment Admin (' . strtoupper($paymentMethod) . ')';
        } else {
            $visit->moveToStage('cashier');
            $routeLabel = 'Cashier — KES ' . number_format($totalClient, 2);
        }

        Notification::make()->success()
            ->title('Intake Finalized')
            ->body("{$client->full_name} → {$routeLabel}. Invoice #{$invoiceNumber} created.")
            ->send();
    });

    $this->redirect(route('filament.admin.resources.intake-queues.index'));
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Pages/IntakeAssessmentEditor.php
git commit -m "feat: add finalize() with invoice creation and visit routing"
```

---

## Task 10: Update IntakeQueueResource (both actions)

**Files:**
- Modify: `app/Filament/Resources/IntakeQueueResource.php`
- Modify: `app/Filament/Resources/IntakeAssessmentResource.php`

- [ ] **Step 1: Update `continue_intake` URL to point to editor**

In `IntakeQueueResource`, find the `continue_intake` action (~line 157) and change its URL closure:

```php
->url(function ($record) {
    $intake = IntakeAssessment::where('visit_id', $record->id)->first();
    return $intake
        ? route('filament.admin.pages.intake-assessment-editor', ['intakeId' => $intake->id])
        : '#';
}),
```

- [ ] **Step 2: Update `start_intake` action to redirect to editor after creation**

Find the `start_intake` action (~line 148). Currently it links to `intake-assessments.create`. Add an `after` redirect so that after the intake is created, it opens the editor. The cleanest way: change the action URL so it first hits the standard create page (which still exists and runs `afterCreate()` but we want to skip that) — actually the better approach is to keep `start_intake` pointing to the old create form for now, since the editor handles existing intakes only. The `start_intake` action creates a NEW intake via the resource create form; on success, the redirect in `CreateIntakeAssessment::getRedirectUrl()` sends to the intake-queues index. This can remain unchanged for now.

**Optional enhancement (mark as future):** After new intake is created via the old form, automatically open the editor. This requires modifying `CreateIntakeAssessment::getRedirectUrl()` to redirect to the editor. Mark this as a follow-up task — do not block on it.

- [ ] **Step 3: Add "Open Editor" action to IntakeAssessmentResource table**

In `IntakeAssessmentResource::table()`, add to `->actions([...])` before the existing actions:

```php
Tables\Actions\Action::make('open_editor')
    ->label('Open Editor')
    ->icon('heroicon-o-pencil-square')
    ->color('primary')
    ->url(fn(IntakeAssessment $record) => route(
        'filament.admin.pages.intake-assessment-editor',
        ['intakeId' => $record->id]
    )),
```

- [ ] **Step 4: Verify route name**

```bash
php artisan route:list | grep intake-assessment-editor
```
Note the exact route name. If it differs from `filament.admin.pages.intake-assessment-editor`, update both actions.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/IntakeQueueResource.php app/Filament/Resources/IntakeAssessmentResource.php
git commit -m "feat: update intake queue and resource table to link to new editor"
```

---

## Task 11: Tests

**Files:**
- Create: `tests/Feature/IntakeAssessmentEditorTest.php`

- [ ] **Step 1: Write feature tests**

```php
<?php

use App\Filament\Pages\IntakeAssessmentEditor;
use App\Models\Client;
use App\Models\IntakeAssessment;
use App\Models\User;
use App\Models\Visit;
use Livewire\Livewire;

beforeEach(function () {
    $this->intakeOfficer = User::factory()->create();
    $this->intakeOfficer->assignRole('intake_officer');
    $this->actingAs($this->intakeOfficer);
});

it('mounts with section A complete when client_id is set', function () {
    $intake = IntakeAssessment::factory()->create(['client_id' => Client::factory()->create()->id]);

    Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
        ->assertSet('sectionStatus.A', 'complete')
        ->assertSet('activeSection', 'A');
});

it('switches sections', function () {
    $intake = IntakeAssessment::factory()->create();

    Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
        ->call('switchSection', 'D')
        ->assertSet('activeSection', 'D');
});

it('autosave: saveSectionData updates the DB and section_status', function () {
    $intake = IntakeAssessment::factory()->create();

    Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
        ->set('sectionData.H.reason_for_visit', 'Speech delay concerns')
        ->call('saveSectionData', 'H');

    expect(IntakeAssessment::find($intake->id)->reason_for_visit)->toBe('Speech delay concerns');
});

it('autosave: section status becomes complete when required fields are filled', function () {
    $intake = IntakeAssessment::factory()->create();

    $component = Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
        ->set('sectionData.H.reason_for_visit', 'Speech delay')
        ->set('sectionData.H.referral_source', ['self'])
        ->call('saveSectionData', 'H');

    $component->assertSet('sectionStatus.H', 'complete');
});

it('blocks access for non-intake roles', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');
    $this->actingAs($receptionist);

    $intake = IntakeAssessment::factory()->create();

    Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
        ->assertForbidden();
});

it('returns 404 when intakeId is 0', function () {
    Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => 0])
        ->assertNotFound();
});

it('blocks finalize when sections are incomplete', function () {
    $intake = IntakeAssessment::factory()->create([
        'section_status' => array_fill_keys(['A','B','C','D','E','F','G','H','I','J','K','L'], 'incomplete'),
    ]);

    Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
        ->call('finalize')
        ->assertNotified(); // danger notification
});

it('blocks finalize for deferred visits', function () {
    $intake = IntakeAssessment::factory()
        ->for(Visit::factory()->state(['status' => 'deferred']))
        ->create([
            'section_status' => array_fill_keys(['A','B','C','D','E','F','G','H','I','J','K','L'], 'complete'),
        ]);

    Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
        ->call('finalize')
        ->assertNotified(); // warning notification
});
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --filter=IntakeAssessmentEditorTest
```
Expected: all pass. If factories are missing, create them with `php artisan make:factory IntakeAssessmentFactory`.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/IntakeAssessmentEditorTest.php
git commit -m "test: add IntakeAssessmentEditor feature tests including autosave"
```

---

## Task 12: Smoke test end-to-end

- [ ] **Step 1: Start dev server**

```bash
composer run dev
```

- [ ] **Step 2: Open editor for an existing intake record**

Navigate to `/admin/intake-queue` → click "Continue" on an intake-stage visit → confirm it opens `/admin/intake-assessment-editor?intakeId=X`.

- [ ] **Step 3: Verify header and sidebar**

- Client name, UCI, visit number visible in header
- Progress bar shows correct count (0–12)
- Sidebar shows A–L with status dots

- [ ] **Step 4: Verify autosave**

Navigate to Section H. Fill in "Reason for Visit". Click elsewhere (blur). Wait 1 second. Confirm "Saved ✓" indicator appears. Check DB:
```bash
php artisan tinker
>>> App\Models\IntakeAssessment::find(X)->reason_for_visit
```

- [ ] **Step 5: Verify sidebar completion state updates**

After filling required fields in Section H, sidebar dot should change from ○ to ✓ without page reload.

- [ ] **Step 6: Run Shield permission sync**

```bash
php artisan shield:generate --all
```

- [ ] **Step 7: Final commit if any fixes were needed**

```bash
git add -p
git commit -m "fix: smoke test corrections for intake assessment editor"
```

---

## Notes for Implementer

**Autosave mechanism:** The Alpine.js `@blur.capture` / `@change.capture` on the section wrapper div is guaranteed to fire regardless of Filament's internal form wiring. Filament file uploads have their own XHR mechanism — they do not trigger `blur`/`change` events in the same way as text inputs, so uploaded file paths are saved when the officer next blurs another field or navigates away. This is acceptable behavior.

**Task 2 is the largest task.** Move one section at a time. After moving each section into its static method, verify the existing create form (`/admin/intake-assessments/create?visit=1`) still renders correctly before moving the next section. If a section has `fn() => Visit::with('client')->find(request()->query('visit'))...` defaults, replace `request()->query('visit')` with `$visitId` in the extracted static method.

**Section G hidden visit_id field:** The `age_band_banner` placeholder closure inside Section G calls `$get('visit_id')`. Include `Forms\Components\Hidden::make('visit_id')->default($visitId)` as the first field in `sectionGSchema()` so this resolve correctly in the editor.

**Section K auto-complete:** Section K has no required fields and auto-completes on first `saveSectionData('K')` call. This is intentional — deferral is optional.

**Factories:** If `IntakeAssessment::factory()` does not exist, create it:
```bash
php artisan make:factory IntakeAssessmentFactory --model=IntakeAssessment
```
