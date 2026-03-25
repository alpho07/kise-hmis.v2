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

The existing `IntakeAssessmentResource` and its edit page remain untouched. The new editor is linked from the resource table via an action button.

---

## URL & Routing

- Editor URL: `/admin/intake-assessment-editor?intakeId={id}`
- Registered as a Filament Page in `AdminPanelProvider` (auto-discovered)
- `IntakeAssessmentResource` table gains an **"Open Editor"** action that links to this URL

---

## Section → Model Mapping

| Section | Label | Target Model |
|---|---|---|
| A | Client Identification | `Client` (read-only display) |
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

---

## Autosave Mechanism

1. Fields use `->live(debounce: 1000)` (1-second debounce after last keystroke) or `wire:model.blur` for selects/toggles.
2. Livewire `updated($name, $value)` lifecycle hook fires after the debounce.
3. `saveSectionData(string $section)` is called — it finds or creates the target model and saves only the fields for that section.
4. `section_status` JSON is updated to `in_progress` on first touch, `complete` when all required fields for that section are non-null.
5. A `$savingSection` property drives the "Saving..." / "Saved ✓" indicator in the content header.

---

## Page Class — Key Properties & Methods

```php
class IntakeAssessmentEditor extends Page
{
    // URL param
    public int $intakeId;

    // Navigation state
    public string $activeSection = 'A';

    // Completion map loaded from DB
    public array $sectionStatus = [];

    // Saving indicator
    public ?string $savingSection = null;

    // Loaded models (set in mount)
    public IntakeAssessment $intake;
    public Client $client;

    // Livewire form data bags (one per section)
    public array $sectionData = [];

    public function mount(int $intakeId): void;
    public function switchSection(string $section): void;
    public function saveSectionData(string $section): void;
    public function updated(string $name, mixed $value): void;
    public function finalize(): void;  // Section L only — triggers visit routing
    public function getProgressProperty(): int;  // 0–12 complete count
    public function getSectionStatusColorProperty(): array;  // sidebar dot colors
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
- Only the active section's Filament form renders
- Inactive sections are not in the DOM (`@if $activeSection === 'X'`)

**Footer nav**: "← Previous" and "Next →" buttons step through sections linearly.

**Section L only**: Shows a green **"Finalize Assessment"** button that runs `finalize()` — this triggers the visit routing logic (currently in `afterCreate()`).

---

## Finalization Logic

The `finalize()` method on the Page class contains the logic currently in `CreateIntakeAssessment::afterCreate()`:
- Creates/updates `AssessmentAutoReferral` records
- Updates `Visit::current_stage` to `billing`
- Creates invoice line items
- Sends Filament notification on success

`finalize()` only runs if all 12 sections have status `complete`. If any section is incomplete, a Filament danger notification lists the missing sections.

---

## Error Handling

- If `saveSectionData()` throws, catch the exception, show a Filament danger notification "Autosave failed — please try again", and set `savingSection = null`.
- If `mount()` cannot find the `IntakeAssessment`, abort with a 404.
- Network interruptions during save: the field value is already in Livewire state; the next blur event will retry the save.

---

## Out of Scope

- Real-time multi-user collaboration (two users editing same intake simultaneously)
- Offline support
- Modifying the existing `IntakeAssessmentResource` table/list pages
- Any changes to the `AssessmentFormSchema` / `DynamicFormBuilder` dynamic form system

---

## Success Criteria

1. Editor loads in under 2 seconds for any intake record
2. Field changes save to DB within 1.5 seconds of blur
3. Sidebar completion dots update without a page reload
4. Section L "Finalize" button only activates when all 12 sections are complete
5. Existing `IntakeAssessmentResource` list page and edit page continue to work unchanged
