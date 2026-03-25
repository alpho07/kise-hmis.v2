# Intake Assessment Editor — Design Spec

**Date:** 2026-03-25
**Status:** Approved

---

## Overview

Replace the single monolithic IntakeAssessment edit form (2449 lines, 12 sections) with a sidebar-navigated, autosaving editor page. The new editor renders one section at a time, saves field-by-field on blur, and tracks completion per section so intake officers always know where they stand.

---

## Goals

- Eliminate the performance problem of rendering a 12-section form at once
- Autosave data as the officer works (no "Save" button required mid-form)
- Show persistent progress: client header + sidebar completion state on every section
- Allow officers to jump to any section non-linearly
- Require all 12 sections before finalization (Section L triggers visit routing)

---

## Components & Files

| File | Purpose |
|---|---|
| `app/Filament/Pages/IntakeAssessmentEditor.php` | New custom Filament Page (extends `Filament\Pages\Page`) |
| `resources/views/filament/pages/intake-assessment-editor.blade.php` | Sidebar + content Blade layout |
| `database/migrations/YYYY_MM_DD_add_section_status_to_intake_assessments.php` | Adds `section_status` JSON column |
| `app/Filament/Resources/IntakeQueueResource.php` | Update `continue_intake` action to point to editor URL |

The existing `IntakeAssessmentResource` list/create/edit pages remain untouched except that the resource table gains an **"Open Editor"** action. The `IntakeQueueResource` `continue_intake` action is updated to open the editor instead of the old monolithic edit page.

---

## URL & Routing

- Editor URL: `/admin/intake-assessment-editor?intakeId={id}`
- Registered as a Filament Page (auto-discovered from `app/Filament/Pages/`)
- `$intakeId` property must carry the `#[Url]` attribute (Livewire v3) so the URL query param hydrates on direct navigation

---

## Authorization

The page overrides `canAccess()` to allow roles: `intake_officer`, `admin`, `super_admin`. All other roles receive a 403. This mirrors the role check on `IntakeAssessmentResource::shouldRegisterNavigation()`.

---

## Section → Model Mapping

| Section | Label | Target Model |
|---|---|---|
| A | Client Identification | `Client` (read-only display, auto-completes on mount) |
| B | Disability Profile | `ClientDisability` |
| C | Socio-Demographics | `ClientSocioDemographic` |
| D | Medical History | `ClientMedicalHistory` |
| E | Functional Screening | `FunctionalScreening` |
| F | Presenting Problem | `IntakeAssessment` (direct fields) |
| G | Clinical Observations | `IntakeAssessment` (direct fields) |
| H | Risk Assessment | `IntakeAssessment` (direct fields) |
| I | Service Plan | `IntakeAssessment` (direct fields) |
| J | Referrals | `AssessmentAutoReferral` |
| K | Education & Placement | `ClientEducation` |
| L | Summary & Finalization | `IntakeAssessment` + visit routing trigger |

**Section A auto-complete:** Section A is marked `complete` automatically during `mount()` when the `IntakeAssessment` record has a valid `client_id`. The officer cannot edit Section A — it is a read-only display of the linked client.

---

## Data Model Change

Add `section_status` JSON column to `intake_assessments`:

```json
{
  "A": "complete",
  "B": "in_progress",
  "C": "incomplete",
  ...
}
```

Valid values: `incomplete` (default), `in_progress` (visited but not all required fields filled), `complete`.

`section_status` updates use `DB::transaction` with a JSON merge to avoid last-write-wins races when two saves fire near-simultaneously:

```php
DB::transaction(function () use ($section, $status) {
    $this->intake->refresh();
    $updated = array_merge($this->intake->section_status ?? [], [$section => $status]);
    $this->intake->update(['section_status' => $updated]);
});
```

---

## Autosave Mechanism

Each section uses a dedicated Livewire public array property named `sectionData` keyed by section letter (e.g. `$sectionData['B']`). Fields bind with `wire:model.blur` (not `->live()`). This avoids Filament `statePath` conflicts.

Flow per save:
1. Officer leaves a field → Livewire fires `updatedSectionData(string $key, mixed $value)` where `$key` is `B.disability_type` etc.
2. The method extracts the section letter from the key prefix and calls `saveSectionData($section)`.
3. `saveSectionData()` reads `$this->sectionData[$section]`, runs `updateOrCreate()` on the target model, then calls `updateSectionStatus($section)`.
4. `updateSectionStatus()` checks required fields (see Appendix) and sets the section's status to `in_progress` or `complete`, using the DB::transaction merge strategy.
5. A `$savingSection` property drives the "Saving..." / "Saved ✓" indicator.

**No Filament `InteractsWithForms` is used for autosave sections.** Form fields are rendered as plain Blade/Alpine components with `wire:model.blur`. Section L uses a minimal Filament form for the finalize action only.

---

## Page Class — Key Properties & Methods

```php
class IntakeAssessmentEditor extends Page
{
    #[Url]
    public int $intakeId;

    public string $activeSection = 'A';
    public array $sectionStatus = [];    // loaded from DB on mount
    public ?string $savingSection = null;

    public IntakeAssessment $intake;
    public Client $client;

    // Flat data bags keyed by section letter, populated on mount from related models
    public array $sectionData = [];

    public function mount(int $intakeId): void;
    public function switchSection(string $section): void;
    public function updatedSectionData(string $key, mixed $value): void;
    public function saveSectionData(string $section): void;
    public function updateSectionStatus(string $section): void;
    public function finalize(): void;             // Section L only
    public function getProgressProperty(): int;   // count of 'complete' sections
    public static function canAccess(): bool;
}
```

---

## UI Layout

```
┌─────────────────────────────────────────────────────────────────┐
│  [Avatar] John Doe · UCI: KISE/A/000123/2026 · Visit: VST-001  │
│  ████████████░░░░░░░░  5/12 sections complete                   │
└─────────────────────────────────────────────────────────────────┘
┌──────────────┬──────────────────────────────────────────────────┐
│ A ✓ Client   │  Section B — Disability Profile          Saved ✓ │
│ B ▶ Disab.   │ ─────────────────────────────────────────────── │
│ C ○ Socio    │  [active section form fields]                    │
│ D ○ Medical  │                                                  │
│ E ○ Function │                                                  │
│ F ○ Present  │                                                  │
│ G ○ Clinical │                                                  │
│ H ○ Risk     │                                                  │
│ I ○ Service  │                                                  │
│ J ○ Referral │                                                  │
│ K ○ Educatn  │                                                  │
│ L ○ Summary  │                              [← Prev] [Next →]  │
└──────────────┴──────────────────────────────────────────────────┘
```

**Header** (sticky): client avatar, full name, UCI, visit number, progress bar showing X/12.

**Sidebar** (fixed ~200px wide):
- Lists sections A–L with short label
- Icons: ✓ (complete, green), ▶ (current, indigo), ○ (incomplete, gray)
- Clicking any item calls `wire:click="switchSection('X')"`

**Content area**:
- Section title + "Saved ✓" or "Saving..." badge (top-right)
- Only the active section renders; inactive sections are excluded from DOM (`@if ($activeSection === 'X')`)

**Footer nav**: "← Previous" and "Next →" buttons step through sections linearly.

**Section L only**: Shows a green **"Finalize Assessment"** button that runs `finalize()`. Button is disabled (with tooltip) if any section is not `complete`.

---

## Finalization Logic

`finalize()` reads data from already-persisted related models — not from a flat form state array. It does **not** duplicate the `afterCreate()` approach of pulling from `$data`. Instead:

1. Load `$intake->load(['client', 'functionalScreening', 'visit.serviceBookings'])`.
2. Guard: if any section status is not `complete`, dispatch a danger notification listing missing sections and return.
3. Guard: if `$intake->visit->status === 'deferred'`, block finalization with notification "Visit is deferred — resolve deferral before finalizing."
4. Retrieve referral records via `AssessmentAutoReferral::where('visit_id', $intake->visit_id)->get()`. Section J saves referrals with `visit_id = $intake->visit_id` during autosave; `finalize()` reads them back the same way. No relationship change on `IntakeAssessment` is needed.
5. Create invoice with line items from `$intake->services_required`.
6. Route visit based on payment method:
   - Sponsor methods (`sha`, `ncpwd`, `insurance_private`, `waiver`, `combination`) → `$intake->visit->moveToStage('billing')`
   - Cash / M-PESA → `$intake->visit->moveToStage('cashier')`
7. Mark `$intake->update(['finalized_at' => now(), 'assessed_by' => auth()->id()])`.
8. Send success notification and redirect to `IntakeQueueResource` index.

---

## Error Handling

- `saveSectionData()` throws → catch, show danger notification "Autosave failed — please try again", set `savingSection = null`.
- `mount()` cannot find `IntakeAssessment` → `abort(404)`.
- `finalize()` DB failure → wrap in `DB::transaction`, rollback on exception, show danger notification with exception message.
- Direct URL access without `intakeId` → `mount()` receives `intakeId = 0`, triggers 404.

---

## Out of Scope

- Real-time multi-user collaboration (two users editing same intake simultaneously)
- Offline support
- Modifying `IntakeAssessmentResource` create/edit pages (they remain as fallback)
- Any changes to the `AssessmentFormSchema` / `DynamicFormBuilder` dynamic form system

---

## Success Criteria

1. Editor loads in under 2 seconds for any intake record
2. Field changes save to DB within 1.5 seconds of blur
3. Sidebar completion dots update without a page reload
4. Section L "Finalize" button only activates when all 12 sections are complete
5. Existing `IntakeAssessmentResource` list/create/edit pages continue to work unchanged
6. `IntakeQueueResource` `continue_intake` action opens the new editor

---

## Appendix — Required Fields Per Section

Minimum fields that must be non-null for a section to reach `complete` status.

| Section | Required fields |
|---|---|
| A | Auto-complete on mount if `client_id` is set |
| B | `disability_category`, `disability_type`, `disability_onset` |
| C | `marital_status`, `education_level`, `employment_status` |
| D | `has_chronic_conditions`, `current_medications` (if has_chronic_conditions = true) |
| E | `developmental_milestone_met` + age-band sub-sections: E3 (perinatal) required only if client age ≤ 18; E4 (feeding) required only if client age ≤ 5; other E sub-sections always required |
| F | `presenting_complaint`, `history_present_illness` |
| G | `clinical_observations` |
| H | `risk_level` |
| I | `services_required`, `expected_payment_method` |
| J | At least one referral record exists (internal or external), OR `no_referral_required = true` |
| K | `school_type` (if client is school-age), OR `not_school_age = true` |
| L | `assessment_summary`, `assessment_type` |
