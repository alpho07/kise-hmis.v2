# Browser Tests Design — KISE HMIS v2

**Date:** 2026-04-06  
**Scope:** True browser/UI tests from login to workflow completion  
**Status:** Under Review (iteration 3)

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
│   └── WorkflowFixture.php          # Shared trait: roles, branch, users, service, queue entries
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

> **Role name note:** The canonical billing role name used by `BillingResource::shouldRegisterNavigation()` is `billing_officer`. The `RoleSeeder` inconsistently uses `billing_admin` in some places — this must be resolved before the billing tests are written. All fixture code uses `billing_officer`.

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

- `seedWorkflowFixture()` — called in `setUp()` of each test class; seeds roles, branch, users, department, service, client
- `makeVisitAt(string $stage, array $overrides = []): Visit` — creates a visit at the given stage for the fixture client; authenticates as the appropriate role actor via `actingAs()` to satisfy `BelongsToBranch` scope
- `makeQueueEntry(Visit $visit, array $overrides = []): QueueEntry` — creates a `QueueEntry` row for a visit (required by `ServiceQueueResource` which queries `QueueEntry`, not `Visit` directly)
- `makeUserWithPassword(string $role, string $password = 'password'): User` — creates a user with a known plaintext password; used by Dusk only (Livewire tests use `actingAs()` and do not need plaintext credentials)

**Stage name reference** (authoritative values from resource `->where('current_stage', ...)` calls):

| Stage | String value |
|---|---|
| Reception | `reception` |
| Triage | `triage` |
| Intake | `intake` |
| Billing | `billing` |
| Cashier queue | `cashier` |
| Service | `service` |
| Completed | `completed` |

> **Stage mismatch — production bug to fix before tests:** `CashierQueueResource` correctly filters on `current_stage = 'cashier'`, but both intake completion paths in the live code call `moveToStage('queue')` — `IntakeAssessmentEditor.php:1195` and `CreateIntakeAssessment.php:840`. This means visits completed through intake never appear in the cashier queue. The implementation plan must fix these two lines to use `'cashier'` before cashier-stage tests can pass. The spec uses `'cashier'` throughout as the authoritative target value.

---

## 5. Livewire/Filament Component Tests

### 5.1 What each test covers

Each test file covers **three concerns** for its workflow stage:

1. **Form submission** — fill form fields, call save/create, assert DB state
2. **Stage-advancing action** — call the named Filament table action, assert `current_stage` advances
3. **Role gate** — wrong role gets HTTP 403 accessing the page

For Livewire tests, role switching is done via `actingAs($user)` before each `Livewire::test()` call. Passwords are irrelevant.

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
- **Action test:** `start_intake` on visit at `intake` stage — this action creates an `IntakeAssessment` record and returns a redirect. Because `Livewire::test()` does not follow redirects, the assertion must be: (a) `assertDatabaseHas('intake_assessments', ['visit_id' => $visit->id])` confirming the record was created, and (b) assert that the Livewire response has a redirect component (via `->assertRedirect()` or checking the dispatch). Do NOT assert page content after the action.
- **Stage test:** Completing intake (cash path) → `current_stage=cashier` (not `billing`)
- **Stage test:** Completing intake (SHA path) → `current_stage=billing`
- **Role gate:** `cashier` accessing intake queue → 403

#### `BillingResourceTest.php`
- Actor: `billing_officer` (canonical role name — see role name note in §4)
- Page: `BillingResource` (SHA invoices only — `has_sponsor=true`)
- **Form test:** Create invoice with `total_sponsor_amount=800`, `total_client_amount=200`, `has_sponsor=true`
- **Action test:** `approve` action (action name: `'approve'`) → visit moves to `cashier` stage
- **Visibility test:** Only `has_sponsor=true` invoices appear in this resource
- **Role gate:** `cashier` accessing billing → 403

#### `CashierQueueResourceTest.php`
- Actor: `cashier`
- Page: `ListCashierQueues`
- **Prerequisite:** Seed visit at `current_stage=cashier`
- **Action test:** `process_payment` on visit at `cashier` stage → `HybridPaymentService::processHybridPayment()` is called; assert `assertDatabaseHas('visits', ['id' => $visit->id, 'payment_status' => 'paid'])` (the action directly sets this at line 461 of `CashierQueueResource.php`) and `assertDatabaseHas('payments', ['visit_id' => $visit->id])`
- **Gate test:** Visit with `payment_status=pending` is not visible in service queue after payment
- **Role gate:** `intake_officer` accessing cashier queue → 403

#### `ServiceQueueResourceTest.php`
- Actor: `service_provider`
- Page: `ListServiceQueues`
- **Model note:** `ServiceQueueResource` queries `QueueEntry` (not `Visit` directly). Tests must seed both a `Visit` and a corresponding `QueueEntry` via `makeQueueEntry()`.
- **Visibility test:** `QueueEntry` whose `visit.payment_status` is in `[paid, partial]` appears; `pending` does not
- **Action test:** `complete_service` → `QueueEntry.status = 'completed'`, `Visit.current_stage = 'completed'`. Note: `check_out_time` is NOT set by this action — do not assert it. The action calls `$visit->completeStage()` + `$visit->moveToStage('completed')` and sets `QueueEntry.status = 'completed'` and `QueueEntry.service_status = 'completed'`.
- **Role gate:** `receptionist` accessing service queue → 403

---

## 6. Dusk Real-Browser Smoke Test

### 6.1 Purpose

Prove the full cash patient workflow is navigable in a real browser. Six independent browser methods, each seeding its own prerequisite DB state, each logging in as the appropriate role.

### 6.2 Configuration

- **DB:** `.env.dusk.local` uses `DB_DATABASE=database/testing_dusk.sqlite` (file-based, not `:memory:`, so the browser session store works)
- **State model:** Each Dusk test method is **fully independent** — it seeds its own prerequisite visit state at the required stage, then performs its browser action. This avoids shared mutable state across methods and makes individual failures debuggable. The class uses `use DatabaseMigrations;` which resets the DB before each method (same semantics as `RefreshDatabase`, but runs real migrations rather than wrapping in a transaction).
- **Chrome:** Uses local Chrome install; headless mode is optional

### 6.3 Test Methods

| # | Method | Actor | Prerequisite seeded | URL | Browser action | Assert |
|---|---|---|---|---|---|---|
| 01 | `test_01_receptionist_logs_in` | receptionist | user only | `/admin/login` | Fill email+password, submit | Redirected to `/admin`, "Reception" nav visible |
| 02 | `test_02_register_and_check_in` | receptionist | none | `/admin/receptions/create` | Fill client form, Save | Success notification, visit in list |
| 03 | `test_03_triage_vitals` | triage_nurse | visit at `triage` | `/admin/triage-queues` | `start_triage`, fill vitals, Save | Visit row disappears from triage queue |
| 04 | `test_04_intake_assessment` | intake_officer | visit at `intake` | `/admin/intake-queues` | `start_intake`, fill minimal form, Save | Visit row disappears from intake queue |
| 05 | `test_05_cashier_payment` | cashier | visit at `cashier` | `/admin/cashier-queues` | `process_payment`, enter 1000, Confirm | Visit shows `paid` badge |
| 06 | `test_06_service_completion` | service_provider | visit at `cashier` with `payment_status=paid` + QueueEntry | `/admin/service-queues` | `complete_service`, confirm | Visit shows `completed`, absent from queue |

### 6.4 Session management

Each method calls `$this->browse(function (Browser $browser) { ... })`. Each method begins with a fresh browser session. Seeding is done in PHP before `$this->browse()` is called. Each method logs in as the appropriate role using `makeUserWithPassword()` credentials.

---

## 7. What This Does NOT Test

- The full 12-section intake form (covered by existing `IntakeEditorSectionSaveTest`)
- Functional screening scoring
- Insurance claim batch processing
- Reports & Analytics widgets

These are out of scope for this spec.

---

## 8. Dependencies / Prerequisites

The following must be completed **in order** before tests are written:

1. **Fix intake stage routing bug** — change `moveToStage('queue')` to `moveToStage('cashier')` in `IntakeAssessmentEditor.php:1195` and `CreateIntakeAssessment.php:840`. Without this, visits never reach `CashierQueueResource`.
2. **Fix RoleSeeder** — update `RoleSeeder.php:22` from `billing_admin` to `billing_officer` (and update the query at line 119). `WorkflowFixture` creates `billing_officer`; if the seeder creates `billing_admin`, tests will use an unpermissioned duplicate role.
3. **Install Laravel Dusk** — `composer require --dev laravel/dusk`
4. **Scaffold Dusk** — `php artisan dusk:install` (creates `tests/Browser/`, `DuskTestCase.php`, `.env.dusk.local`)
5. **Install ChromeDriver** — `php artisan dusk:chrome-driver --detect` (requires local Chrome/Chromium)

---

## 9. Success Criteria

- All 6 Livewire test files pass in `php artisan test tests/Feature/UI/`
- `php artisan dusk tests/Browser/CashPatientWorkflowTest.php` completes with 6 passing methods
- No existing tests broken
- Each failing test points to exactly one stage/resource
