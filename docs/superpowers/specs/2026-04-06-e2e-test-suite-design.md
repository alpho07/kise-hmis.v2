# E2E Test Suite Design — KISE HMIS v2

**Date:** 2026-04-06
**Approach:** Option 2 — Chained Fixture E2E Suite (PHPUnit feature tests, no browser)
**Scenarios:** Cash walk-in patient, SHA insurance patient

---

## Context

The system manages patients through a 7-stage clinical workflow:

```
Reception → Triage → Intake → Billing (sponsor only) → Queue → Service → Completed
```

The cashier actor works in the `queue` stage — that is the stage name in the `current_stage`
enum: `reception | triage | intake | billing | queue | service | completed | deferred`.

SHA routing: intake officer advances the visit to `billing` (not `queue`). An invoice is
created by the billing officer, then the cashier collects the client copay and marks payment done.

Cash routing: intake officer advances the visit directly to `queue`. A minimal invoice is still
created (required FK for the payment row), then the cashier records full payment.

---

## File Structure

```
tests/Feature/E2E/
    EndToEndCashPatientTest.php      — walk-in, cash payment path
    EndToEndShaPatientTest.php       — walk-in, SHA insurance payment path
```

---

## Ground-Truth Column Names (verified against migrations)

| Table | Correct column | Wrong name to avoid |
|---|---|---|
| `triages` | `systolic_bp` | ~~`blood_pressure_systolic`~~ |
| `triages` | `heart_rate` | ~~`pulse_rate`~~ |
| `payments` | `amount` | ~~`amount_paid`~~ |
| `invoices` | `covered_amount` | ~~`total_sponsor_amount`~~ |
| `invoices` | `balance_due` | ~~`total_client_amount`~~ |
| `invoices` | `total_amount` | — |
| `intake_assessments` | (no payment_method column) | ~~`payment_method`~~ |

SHA vs cash routing is determined by which stage the intake officer moves the visit to
(`billing` vs `queue`) — not by a field on `intake_assessments`.

`has_sponsor`, `total_sponsor_amount`, `total_client_amount` appear in the Invoice model's
`$fillable` and `$casts` but are NOT in any migration. DB assertions must use the migrated
columns (`covered_amount`, `balance_due`). Model-level computed values (`$invoice->has_sponsor`)
may be asserted via `assertGreaterThan(0, $invoice->covered_amount)` instead.

---

## Shared Fixture Strategy

Both test classes use `RefreshDatabase`. Each test method gets a fully fresh DB rebuilt by
`setUp()`. The fixture creates:

- 1 `Branch`
- 7 role-users (each with `branch_id`, `is_active=true`): `receptionist`, `triage_nurse`,
  `intake_officer`, `billing_officer`, `cashier`, `service_provider`, `admin`
- 1 `Client` (adult, `date_of_birth` = 30 years ago)
- 1 `InsuranceProvider` (`code='SHA'`, `is_active=true`, `default_coverage_percentage=80`)
- 1 `Service` (`base_price=1000`, `is_active=true`)

All roles created with `Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])`.
Role name for billing: `billing_officer` (matches `DatabaseSeeder::seedRolesAndPermissions`).

### Required column values for FK-constrained tables

**Invoice** — required non-nullable columns:
- `invoice_number` — generate with `'INV-TEST-' . uniqid()`
- `visit_id`, `client_id`, `branch_id` — from fixture
- `generated_by` — billing_officer or receptionist user id

**Payment** — required non-nullable columns:
- `payment_number` — generate with `'PAY-TEST-' . uniqid()`
- `invoice_id` — must reference an existing invoice (create one first, even in cash path)
- `visit_id`, `client_id`, `branch_id`, `received_by` — from fixture

---

## Stage Transition Convention

The application calls `$visit->completeStage()` before advancing with `$visit->moveToStage()`.
Every test method that advances a stage MUST mirror this:

```php
$visit->completeStage();       // marks current stage done
$visit->moveToStage('next');   // advances current_stage, writes new visit_stages row
```

---

## Timestamp Assertions

Use model-level null checks, not raw datetime values in `assertDatabaseHas`:

```php
// Correct
$this->assertNotNull($visit->fresh()->check_in_time);

// Avoid — breaks on SQLite due to timezone formatting
$this->assertDatabaseHas('visits', ['check_in_time' => now()->toDateTimeString()]);
```

---

## Scenario A — Cash Walk-In Patient

**File:** `tests/Feature/E2E/EndToEndCashPatientTest.php`
**Test count:** 10 methods

---

### `test_01_client_checks_in_at_reception`
**Actor:** receptionist

**Action:** Create Visit (`visit_type=walk_in`, `current_stage=reception`, `check_in_time=now()`)

**Asserts:**
- `assertDatabaseHas('visits', ['client_id' => $client->id, 'current_stage' => 'reception', 'payment_status' => 'pending'])`
- `assertMatchesRegularExpression('/^VST-\d{8}-\d{4}$/', $visit->visit_number)`
- `assertNotNull($visit->fresh()->check_in_time)`

---

### `test_02_reception_stage_history_recorded`
**Actor:** receptionist

**Action:** Create Visit only; do NOT call `moveToStage` in this method.

**Asserts:**
- `assertDatabaseHas('visit_stages', ['visit_id' => $visit->id, 'stage' => 'reception'])`

---

### `test_03_reception_clears_to_triage`
**Actor:** receptionist

**Action:** Create Visit; then `$visit->completeStage(); $visit->moveToStage('triage')`

**Asserts:**
- `assertDatabaseHas('visits', ['id' => $visit->id, 'current_stage' => 'triage'])`
- `assertDatabaseHas('visit_stages', ['visit_id' => $visit->id, 'stage' => 'triage'])`

---

### `test_04_triage_nurse_records_vitals`
**Actor:** triage_nurse

**Action:** Create Triage record:
```php
Triage::create([
    'visit_id'   => $visit->id,
    'client_id'  => $client->id,
    'branch_id'  => $branch->id,
    'systolic_bp'  => 120,
    'heart_rate'   => 72,
    'temperature'  => 36.6,
    'triaged_by' => $triageNurse->id,
]);
```

**Asserts:**
- `assertDatabaseHas('triages', ['visit_id' => $visit->id, 'systolic_bp' => 120, 'heart_rate' => 72])`

---

### `test_05_triage_clears_to_intake_in_fifo_order`
**Actor:** triage_nurse

**Action:**
1. Create 3 visits with staggered `check_in_time` (T-10min, T-5min, now)
2. Call `$visit->completeStage(); $visit->moveToStage('intake')` on all three

**Asserts:**
- `current_stage=intake` for all three
- `Visit::where('current_stage', 'intake')->orderBy('check_in_time')->first()->id` equals the earliest visit's id (FIFO)
- *(Uses Eloquent model query directly — not the Filament resource class — to avoid Filament panel context requirement)*

---

### `test_06_intake_assessment_created`
**Actor:** intake_officer

**Action:** Create IntakeAssessment:
```php
IntakeAssessment::create([
    'visit_id'   => $visit->id,
    'client_id'  => $client->id,
    'branch_id'  => $branch->id,
    'assessed_by' => $intakeOfficer->id,
    'verification_mode' => 'new_client',
]);
```

**Asserts:**
- `assertDatabaseHas('intake_assessments', ['visit_id' => $visit->id])`
- `assertDatabaseHas('visits', ['id' => $visit->id, 'current_stage' => 'intake'])` — assessment does not auto-advance stage

---

### `test_07_cash_routes_to_queue_not_billing`
**Actor:** intake_officer

**Action:** `$visit->completeStage(); $visit->moveToStage('queue')`

Also create a second Branch and a visit on that branch at `current_stage=queue`.

**Asserts:**
- `assertDatabaseHas('visits', ['id' => $visit->id, 'current_stage' => 'queue'])`
- `assertDatabaseMissing('visit_stages', ['visit_id' => $visit->id, 'stage' => 'billing'])`
- `Visit::where('current_stage', 'queue')->where('branch_id', $branch->id)->pluck('id')` contains `$visit->id` but NOT the second branch visit's id (branch isolation)

---

### `test_08_cashier_creates_invoice_and_records_payment`
**Actor:** cashier

**Action:**
1. Create Invoice:
```php
Invoice::create([
    'invoice_number' => 'INV-TEST-' . uniqid(),
    'visit_id'       => $visit->id,
    'client_id'      => $client->id,
    'branch_id'      => $branch->id,
    'total_amount'   => 1000,
    'balance_due'    => 1000,
    'generated_by'   => $cashier->id,
]);
```
2. Create Payment:
```php
Payment::create([
    'payment_number' => 'PAY-TEST-' . uniqid(),
    'invoice_id'     => $invoice->id,
    'visit_id'       => $visit->id,
    'client_id'      => $client->id,
    'branch_id'      => $branch->id,
    'payment_method' => 'cash',
    'amount'         => 1000,
    'received_by'    => $cashier->id,
]);
```
3. Update visit: `$visit->update(['payment_status' => 'paid', 'payment_verified_at' => now()])`

**Asserts:**
- `assertDatabaseHas('payments', ['visit_id' => $visit->id, 'payment_method' => 'cash'])`
- `assertDatabaseHas('visits', ['id' => $visit->id, 'payment_status' => 'paid'])`
- `assertNotNull($visit->fresh()->payment_verified_at)`

---

### `test_09_payment_gate_controls_service_queue_visibility`
**Actor:** service_provider (reads queue)

**Action:** Create two visits in `current_stage=queue`:
- Visit A: `payment_status=paid`
- Visit B: `payment_status=pending`

Query: `Visit::where('current_stage', 'queue')->whereIn('payment_status', ['paid', 'partial'])->pluck('id')`

**Asserts:**
- Result contains Visit A's id
- Result does NOT contain Visit B's id

*(Tests the model-layer query expression used by ServiceQueueResource — distinct from
`ServiceQueuePaymentGateTest` which tests Filament action visibility logic.)*

---

### `test_10_service_delivered_and_visit_completed`
**Actor:** service_provider

**Action:** `$visit->completeStage(); $visit->moveToStage('completed')`; then
`$visit->update(['check_out_time' => now()])`

**Asserts:**
- `assertDatabaseHas('visits', ['id' => $visit->id, 'current_stage' => 'completed'])`
- `assertNotNull($visit->fresh()->check_out_time)`
- `Visit::where('current_stage', 'queue')->pluck('id')` does NOT contain `$visit->id`

---

## Scenario B — SHA Insurance Patient

**File:** `tests/Feature/E2E/EndToEndShaPatientTest.php`
**Test count:** 8 methods

---

### `test_01_client_checks_in_at_reception`
**Actor:** receptionist

**Action:** Create Visit (`visit_type=walk_in`, `current_stage=reception`)

**Asserts:**
- `assertDatabaseHas('visits', ['current_stage' => 'reception', 'payment_status' => 'pending'])`
- `assertDatabaseHas('visit_stages', ['visit_id' => $visit->id, 'stage' => 'reception'])`
- `assertNotNull($visit->fresh()->check_in_time)`

---

### `test_02_reception_clears_to_triage_with_stage_history`
**Actor:** receptionist

**Action:** `$visit->completeStage(); $visit->moveToStage('triage')`

**Asserts:**
- `assertDatabaseHas('visits', ['id' => $visit->id, 'current_stage' => 'triage'])`
- `assertDatabaseHas('visit_stages', ['visit_id' => $visit->id, 'stage' => 'triage'])`

---

### `test_03_triage_records_vitals_and_clears_to_intake`
**Actor:** triage_nurse

**Action:** Create Triage (`systolic_bp=120`, `heart_rate=72`, `triaged_by=$triageNurse->id`);
then `$visit->completeStage(); $visit->moveToStage('intake')`

**Asserts:**
- `assertDatabaseHas('triages', ['visit_id' => $visit->id, 'systolic_bp' => 120])`
- `assertDatabaseHas('visits', ['id' => $visit->id, 'current_stage' => 'intake'])`

---

### `test_04_intake_assessment_created`
**Actor:** intake_officer

**Action:** Create IntakeAssessment (`visit_id`, `client_id`, `branch_id`, `assessed_by=$intakeOfficer->id`,
`verification_mode='new_client'`)

**Asserts:**
- `assertDatabaseHas('intake_assessments', ['visit_id' => $visit->id])`
- `assertDatabaseHas('visits', ['id' => $visit->id, 'current_stage' => 'intake'])` — assessment does not auto-advance

---

### `test_05_sha_routes_to_billing_not_queue`
**Actor:** intake_officer

**Action:** `$visit->completeStage(); $visit->moveToStage('billing')`

**Asserts:**
- `assertDatabaseHas('visits', ['id' => $visit->id, 'current_stage' => 'billing'])`
- `assertDatabaseHas('visit_stages', ['visit_id' => $visit->id, 'stage' => 'billing'])`
- `assertDatabaseMissing('visit_stages', ['visit_id' => $visit->id, 'stage' => 'queue'])`

---

### `test_06_billing_officer_creates_sponsor_invoice`
**Actor:** billing_officer

**Action:** Create Invoice:
```php
Invoice::create([
    'invoice_number' => 'INV-SHA-' . uniqid(),
    'visit_id'       => $visit->id,
    'client_id'      => $client->id,
    'branch_id'      => $branch->id,
    'total_amount'   => 1000,
    'covered_amount' => 800,   // 80% SHA coverage
    'balance_due'    => 200,   // 20% client copay
    'generated_by'   => $billingOfficer->id,
]);
```

**Asserts:**
- `assertDatabaseHas('invoices', ['visit_id' => $visit->id, 'total_amount' => 1000])`
- `$this->assertEquals(800, $invoice->fresh()->covered_amount)` — SHA covers 80%
- `$this->assertEquals(200, $invoice->fresh()->balance_due)` — client owes 20%
- `$this->assertGreaterThan(0, $invoice->fresh()->covered_amount)` — sponsor portion > 0

---

### `test_07_cashier_collects_copay_and_marks_visit_paid`
**Actor:** cashier

**Action:** (Invoice from test context already created in setUp for this test method)

```php
Payment::create([
    'payment_number' => 'PAY-SHA-' . uniqid(),
    'invoice_id'     => $invoice->id,
    'visit_id'       => $visit->id,
    'client_id'      => $client->id,
    'branch_id'      => $branch->id,
    'payment_method' => 'cash',
    'amount'         => 200,
    'received_by'    => $cashier->id,
]);
$visit->update(['payment_status' => 'paid', 'payment_verified_at' => now()]);
```

Note: invoice is created in the same test method's arrange block (not carried over from test_06,
since `RefreshDatabase` wipes between methods).

**Asserts:**
- `assertDatabaseHas('payments', ['visit_id' => $visit->id, 'amount' => 200])`
- `assertDatabaseHas('visits', ['id' => $visit->id, 'payment_status' => 'paid'])`
- `assertNotNull($visit->fresh()->payment_verified_at)`

---

### `test_08_service_delivered_and_visit_completed`
**Actor:** service_provider

**Action:** `$visit->completeStage(); $visit->moveToStage('completed')`; then
`$visit->update(['check_out_time' => now()])`

**Asserts:**
- `assertDatabaseHas('visits', ['id' => $visit->id, 'current_stage' => 'completed'])`
- `assertNotNull($visit->fresh()->check_out_time)`

---

## Test Count Summary

| Suite | Methods |
|---|---|
| `EndToEndCashPatientTest` | 10 |
| `EndToEndShaPatientTest` | 8 |
| **Total** | **18** |

---

## Out of Scope (covered by existing tests)

| Concern | Existing test |
|---|---|
| Livewire form interactions | `IntakeAssessmentEditorTest`, `IntakeEditorSectionSaveTest` |
| NCPWD age restriction (≤17) | `NcpwdAgeRestrictionTest` |
| Insurance coverage math | `InsuranceCoverageCalculationTest` |
| Role-based navigation visibility | `RoleBasedAccessTest` |
| Client registration / UCI format | `ClientRegistrationTest` |
| Filament action visibility (payment gate) | `ServiceQueuePaymentGateTest` |
| Cash/sponsor stage routing (model layer) | `VisitWorkflowTest` |

---

## Success Criteria

- All 18 test methods pass with `RefreshDatabase` on both SQLite (CI) and MySQL (production)
- No raw datetime values in `assertDatabaseHas` — use `assertNotNull($model->fresh()->column)`
- Every stage advancement calls `completeStage()` before `moveToStage()`
- Invoice and Payment rows always include required FK columns (`invoice_id`, `payment_number`, etc.)
- Only migrated column names are referenced in assertions (see Ground-Truth table above)
- Failure output names the exact stage, actor, and scenario that broke
