# Browser Tests Design — KISE HMIS v2

**Date:** 2026-04-06  
**Scope:** True browser/UI tests from login to workflow completion  
**Status:** Approved

---

## 1. Problem Statement

Existing E2E tests (`tests/Feature/E2E/`) bypass the UI entirely — they call `Model::create()` and `visit->moveToStage()` directly. They verify DB state but do not test:

- Filament form validation and hooks (`mutateFormDataBeforeCreate`, `afterSave`)
- Action `authorize()` callbacks (role gates)
- Real browser rendering, login flow, navigation
- That the correct next stage is reachable from each resource page

This spec defines a two-tier browser test suite that fills those gaps.

---

## 2. Approach

**Option 1 selected:** Livewire/Filament component tests for all business logic + one Dusk smoke test for the cash patient end-to-end browser flow.

### Why this approach

- `Livewire::test()` already established in this project (`ImmunizationSectionTest`, `IntakeAssessmentEditorTest`)
- Livewire tests run against SQLite in-memory — fast, no ChromeDriver dependency
- Dusk is kept thin (one path) to avoid maintenance burden
- Per-stage test isolation means failures are immediately pinpointed

---

## 3. File Structure

```
tests/
├── Support/
│   └── WorkflowFixture.php          # Shared trait: roles, branch, users, service
│
├── Feature/
│   └── UI/
│       ├── ReceptionResourceTest.php
│       ├── TriageResourceTest.php
│       ├── IntakeQueueResourceTest.php
│       ├── BillingResourceTest.php
│       ├── CashierQueueResourceTest.php
│       └── ServiceQueueResourceTest.php
│
└── Browser/
    └── CashPatientWorkflowTest.php
```

---

## 4. Shared Fixture — `WorkflowFixture` Trait

`tests/Support/WorkflowFixture.php` — used by both Livewire and Dusk test classes.

**Provides:**

| Property | Type | Value |
|---|---|---|
| `$branch` | Branch | factory-created |
| `$receptionist` | User | role: `receptionist` |
| `$triageNurse` | User | role: `triage_nurse` |
| `$intakeOfficer` | User | role: `intake_officer` |
| `$billingOfficer` | User | role: `billing_officer` |
| `$cashier` | User | role: `cashier` |
| `$serviceProvider` | User | role: `service_provider` |
| `$client` | Client | adult, same branch |
| `$service` | Service | `base_price=1000`, linked department |
| `$shaProvider` | InsuranceProvider | `type=government_scheme`, `default_coverage_percentage=80` |

**Methods:**

- `seedWorkflowFixture()` — called in `setUp()` of each test class
- `makeVisitAt(string $stage, array $overrides = []): Visit` — creates a visit at the given stage for the fixture client
- `makeUserWithPassword(string $role, string $password = 'password'): User` — creates a user with known plaintext password (needed by Dusk login form)

---

## 5. Livewire/Filament Component Tests

### 5.1 What each test covers

Each test file covers **three concerns** for its workflow stage:

1. **Form submission** — fill form fields, call save/create, assert DB state
2. **Stage-advancing action** — call the named Filament table action, assert `current_stage` advances
3. **Role gate** — wrong role gets HTTP 403 accessing the page

### 5.2 Per-file specification

#### `ReceptionResourceTest.php`
- Actor: `receptionist`
- Page: `CreateReception`
- **Form test:** Fill client fields (name, DOB, gender, contact) + visit fields → assert `clients` row + `visits` row at `current_stage=reception` + `visit_stages` row
- **Role gate:** `cashier` accessing `CreateReception` → 403

#### `TriageResourceTest.php`
- Actor: `triage_nurse`
- Page: `TriageQueueResource` list + triage edit/create form
- **Action test:** `start_triage` action on a visit at `triage` stage → assert `triages` row with vitals
- **Form test:** Fill vitals (`systolic_bp`, `heart_rate`, `temperature`, `weight`) → assert saved
- **Stage test:** Completing triage → visit moves to `intake`
- **Role gate:** `receptionist` accessing triage queue → 403

#### `IntakeQueueResourceTest.php`
- Actor: `intake_officer`
- Page: `ListIntakeQueues`
- **Action test:** `start_intake` on visit at `intake` → redirects to intake assessment form
- **Stage test:** Completing intake (cash path) → `current_stage=queue` (not `billing`)
- **Stage test:** Completing intake (SHA path) → `current_stage=billing`
- **Role gate:** `cashier` accessing intake queue → 403

#### `BillingResourceTest.php`
- Actor: `billing_officer`
- Page: `BillingResource` (SHA invoices only)
- **Form test:** Create invoice with `total_sponsor_amount=800`, `total_client_amount=200`, `has_sponsor=true`
- **Action test:** Approve invoice → visit moves to `queue`
- **Visibility test:** Only `has_sponsor=true` invoices appear in this resource
- **Role gate:** `cashier` accessing billing → 403

#### `CashierQueueResourceTest.php`
- Actor: `cashier`
- Page: `ListCashierQueues`
- **Action test:** `process_payment` on visit at `queue` → `Payment` record created, `payment_status=paid`
- **Gate test:** Visit with `payment_status=pending` is not visible in service queue after payment
- **Role gate:** `intake_officer` accessing cashier queue → 403

#### `ServiceQueueResourceTest.php`
- Actor: `service_provider`
- Page: `ListServiceQueues`
- **Visibility test:** Only visits with `payment_status` in `[paid, partial]` appear
- **Action test:** `complete_service` → visit moves to `completed`, `check_out_time` set
- **Role gate:** `receptionist` accessing service queue → 403

---

## 6. Dusk Real-Browser Smoke Test

### 6.1 Purpose

Prove the full cash patient workflow is navigable in a real browser. One test class, one shared DB state that progresses through all 6 stages.

### 6.2 Configuration

- **DB:** `.env.dusk.local` uses `DB_DATABASE=database/testing_dusk.sqlite` (file-based, not `:memory:`)
- **Trait:** `DatabaseMigrations` (runs once, state persists across browser methods)
- **Chrome:** Uses local Chrome install, headless mode optional

### 6.3 Test Methods

| # | Method | Actor | URL | Action | Assert |
|---|---|---|---|---|---|
| 01 | `test_01_receptionist_logs_in` | receptionist | `/admin/login` | Fill credentials, submit | Redirected to `/admin`, "Reception" nav visible |
| 02 | `test_02_register_and_check_in` | receptionist | `/admin/receptions/create` | Fill client form, Save | Success notification, visit in list |
| 03 | `test_03_triage_vitals` | triage_nurse | `/admin/triage-queues` | `start_triage`, fill vitals, Save | Visit gone from triage queue |
| 04 | `test_04_intake_assessment` | intake_officer | `/admin/intake-queues` | `start_intake`, fill form, Save | Visit gone from intake queue |
| 05 | `test_05_cashier_payment` | cashier | `/admin/cashier-queues` | `process_payment`, enter 1000, Confirm | Visit shows `paid` badge |
| 06 | `test_06_service_completion` | service_provider | `/admin/service-queues` | `complete_service`, confirm | Visit shows `completed`, absent from queue |

### 6.4 Session management

Each method calls `$this->browse(function (Browser $browser) { ... })`. Between methods the DB persists but browser sessions are fresh — each method logs in as the appropriate role at the start.

---

## 7. What This Does NOT Test

- The full 12-section intake form (covered by existing `IntakeEditorSectionSaveTest`)
- Functional screening scoring
- Insurance claim batch processing
- Reports & Analytics widgets

These are out of scope for this spec.

---

## 8. Dependencies / Prerequisites

- `composer require --dev laravel/dusk` — not yet installed
- `php artisan dusk:install` — scaffolds `tests/Browser/`, `DuskTestCase.php`, `.env.dusk.local`
- Local Chrome/Chromium must be installed
- `php artisan dusk:chrome-driver --detect` — installs matching ChromeDriver

---

## 9. Success Criteria

- All 6 Livewire test files pass in `php artisan test tests/Feature/UI/`
- `php artisan dusk tests/Browser/CashPatientWorkflowTest.php` completes with 6 passing methods
- No existing tests broken
- Each failing test points to exactly one stage/resource
