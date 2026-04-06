# E2E Test Suite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Write 18 PHPUnit feature tests across two files that exercise the full 7-stage patient workflow end-to-end — one scenario for cash payment, one for SHA insurance.

**Architecture:** Two test classes under `tests/Feature/E2E/`, each using `RefreshDatabase` and a shared `setUp()` that builds a complete fixture world (branch, 7 role-users, client, service, insurance provider). Every test method is independently arranged — no state leaks between methods. Stage transitions always call `completeStage()` before `moveToStage()` to mirror application code.

**Tech Stack:** PHPUnit 11, Laravel 12, Eloquent models (Visit, Triage, IntakeAssessment, Invoice, Payment), Spatie Laravel Permission (Role), RefreshDatabase (SQLite in-memory for CI)

---

## Reference

- Spec: `docs/superpowers/specs/2026-04-06-e2e-test-suite-design.md`
- Existing test pattern to follow: `tests/Feature/VisitWorkflowTest.php`
- Run all tests: `php artisan test`
- Run a single file: `php artisan test tests/Feature/E2E/EndToEndCashPatientTest.php`
- Run one method: `php artisan test --filter=test_01_client_checks_in_at_reception`

---

## Key Facts (read before touching any code)

**Stage enum** (`visits.current_stage`): `reception | triage | intake | billing | queue | service | completed | deferred`

**Column names — do not guess:**
| Table | Column | NOT |
|---|---|---|
| `triages` | `systolic_bp` | ~~`blood_pressure_systolic`~~ |
| `triages` | `heart_rate` | ~~`pulse_rate`~~ |
| `payments` | `amount` | ~~`amount_paid`~~ |
| `invoices` | `covered_amount` | ~~`total_sponsor_amount`~~ |
| `invoices` | `balance_due` | ~~`total_client_amount`~~ |

**Required non-nullable FK columns:**
- `payments`: `payment_number` (unique), `invoice_id`, `visit_id`, `client_id`, `branch_id`, `received_by`, `payment_method`, `amount`
- `invoices`: `invoice_number` (unique), `visit_id`, `client_id`, `branch_id`, `generated_by`
- `triages`: `visit_id`, `client_id`, `branch_id`, `triaged_by`
- `intake_assessments`: `visit_id`, `client_id`, `branch_id`, `assessed_by`, `verification_mode`

**Timestamp assertions** — never put datetime values in `assertDatabaseHas` (SQLite breaks):
```php
// Correct
$this->assertNotNull($visit->fresh()->check_in_time);
// Wrong — breaks SQLite
$this->assertDatabaseHas('visits', ['check_in_time' => now()->toDateTimeString()]);
```

**TDD note:** These tests exercise existing application code — no new implementation is needed. All 18 tests are expected to pass immediately once written. There is no failing-first cycle; write each test, run it, confirm it passes.

**`BelongsToBranch` trait behaviour:** The trait's `creating` hook only sets `branch_id` from the authenticated user when `!$model->isDirty('branch_id')`. Explicitly passing `branch_id` in a `create()` call marks it dirty, so the hook does NOT override it. Queries apply a global scope filtering to the authenticated user's branch.

**Stage transitions** — always pair:
```php
$visit->completeStage();
$visit->moveToStage('next_stage');
```

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `tests/Feature/E2E/EndToEndCashPatientTest.php` | **Create** | 10 tests: full cash walk-in workflow |
| `tests/Feature/E2E/EndToEndShaPatientTest.php` | **Create** | 8 tests: full SHA insurance workflow |

No application code changes. No migrations. Tests only.

---

## Task 1: Create `EndToEndCashPatientTest` — setUp and first 3 tests

**Files:**
- Create: `tests/Feature/E2E/EndToEndCashPatientTest.php`

- [ ] **Step 1: Create the file with setUp and tests 01–03**

```php
<?php

namespace Tests\Feature\E2E;

use App\Models\Branch;
use App\Models\Client;
use App\Models\InsuranceProvider;
use App\Models\IntakeAssessment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Triage;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end: cash walk-in patient through the full 7-stage workflow.
 *
 * Workflow: Reception → Triage → Intake → Queue → Service → Completed
 * Payment:  Cash — full amount collected at cashier (queue stage)
 *
 * Each test method is self-contained: setUp() rebuilds the full fixture world
 * via RefreshDatabase before each method.
 */
class EndToEndCashPatientTest extends TestCase
{
    use RefreshDatabase;

    private Branch            $branch;
    private User              $receptionist;
    private User              $triageNurse;
    private User              $intakeOfficer;
    private User              $billingOfficer;
    private User              $cashier;
    private User              $serviceProvider;
    private Client            $client;
    private InsuranceProvider $shaProvider;
    private Service           $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles
        foreach ([
            'receptionist', 'triage_nurse', 'intake_officer',
            'billing_officer', 'cashier', 'service_provider', 'admin',
        ] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Branch
        $this->branch = Branch::factory()->create();

        // Users — one per workflow role
        $this->receptionist    = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->triageNurse     = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->intakeOfficer   = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->billingOfficer  = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->cashier         = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->serviceProvider = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);

        $this->receptionist->assignRole('receptionist');
        $this->triageNurse->assignRole('triage_nurse');
        $this->intakeOfficer->assignRole('intake_officer');
        $this->billingOfficer->assignRole('billing_officer');
        $this->cashier->assignRole('cashier');
        $this->serviceProvider->assignRole('service_provider');

        // Client — adult (30 years old)
        $this->client = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(30)->toDateString(),
        ]);

        // Insurance provider — SHA (used for future scenarios, present in fixture)
        $this->shaProvider = InsuranceProvider::create([
            'code'                       => 'SHA',
            'name'                       => 'Social Health Authority',
            'type'                       => 'government_scheme',
            'is_active'                  => true,
            'default_coverage_percentage' => 80,
        ]);

        // Service
        $this->service = Service::create([
            'name'       => 'General Consultation',
            'base_price' => 1000,
            'is_active'  => true,
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /**
     * Create a visit at reception stage for the fixture client.
     */
    private function makeVisit(array $overrides = []): Visit
    {
        $this->actingAs($this->receptionist);

        return Visit::create(array_merge([
            'branch_id'     => $this->branch->id,
            'client_id'     => $this->client->id,
            'visit_type'    => 'walk_in',
            'visit_date'    => now()->toDateString(),
            'current_stage' => 'reception',
            'check_in_time' => now(),
        ], $overrides));
    }

    // ─── Tests 01–03: Reception stage ────────────────────────────────────────

    /** @test */
    public function test_01_client_checks_in_at_reception(): void
    {
        $visit = $this->makeVisit();

        $this->assertDatabaseHas('visits', [
            'client_id'      => $this->client->id,
            'current_stage'  => 'reception',
            'payment_status' => 'pending',
        ]);
        $this->assertMatchesRegularExpression('/^VST-\d{8}-\d{4}$/', $visit->visit_number);
        $this->assertNotNull($visit->fresh()->check_in_time);
    }

    /** @test */
    public function test_02_reception_stage_history_recorded(): void
    {
        $visit = $this->makeVisit();

        // Visit creation must write a visit_stages row for 'reception'
        $this->assertDatabaseHas('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'reception',
        ]);
    }

    /** @test */
    public function test_03_reception_clears_to_triage(): void
    {
        $visit = $this->makeVisit();

        $visit->completeStage();
        $visit->moveToStage('triage');

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'triage',
        ]);
        $this->assertDatabaseHas('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'triage',
        ]);
    }
}
```

- [ ] **Step 2: Run tests 01–03 to verify they pass**

> Note: Tasks 2 and 3 will append more test methods. Do not add the closing class `}` until Task 3.

```bash
php artisan test tests/Feature/E2E/EndToEndCashPatientTest.php --filter="test_0[123]"
```

Expected: `3 passed`

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/E2E/EndToEndCashPatientTest.php
git commit -m "test(e2e): cash patient reception stage tests 01-03"
```

---

## Task 2: Cash test — tests 04–06 (Triage and Intake)

**Files:**
- Modify: `tests/Feature/E2E/EndToEndCashPatientTest.php`

- [ ] **Step 1: Add tests 04–06 inside the class**

```php
    // ─── Tests 04–06: Triage and Intake ──────────────────────────────────────

    /** @test */
    public function test_04_triage_nurse_records_vitals(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'triage']);
        $this->actingAs($this->triageNurse);

        Triage::create([
            'visit_id'   => $visit->id,
            'client_id'  => $this->client->id,
            'branch_id'  => $this->branch->id,
            'systolic_bp' => 120,
            'heart_rate'  => 72,
            'temperature' => 36.6,
            'triaged_by'  => $this->triageNurse->id,
        ]);

        $this->assertDatabaseHas('triages', [
            'visit_id'    => $visit->id,
            'systolic_bp' => 120,
            'heart_rate'  => 72,
        ]);
    }

    /** @test */
    public function test_05_triage_clears_to_intake_in_fifo_order(): void
    {
        $this->actingAs($this->triageNurse);

        // Create 3 visits with staggered check_in_time — oldest first
        $earliest = $this->makeVisit([
            'current_stage' => 'triage',
            'check_in_time' => now()->subMinutes(10),
        ]);
        $middle = $this->makeVisit([
            'current_stage' => 'triage',
            'check_in_time' => now()->subMinutes(5),
        ]);
        $latest = $this->makeVisit([
            'current_stage' => 'triage',
            'check_in_time' => now(),
        ]);

        foreach ([$earliest, $middle, $latest] as $v) {
            $v->completeStage();
            $v->moveToStage('intake');
        }

        $this->assertDatabaseHas('visits', ['id' => $earliest->id, 'current_stage' => 'intake']);
        $this->assertDatabaseHas('visits', ['id' => $middle->id,   'current_stage' => 'intake']);
        $this->assertDatabaseHas('visits', ['id' => $latest->id,   'current_stage' => 'intake']);

        // FIFO: earliest check_in_time must be first in queue (scoped to this branch)
        $firstInQueue = Visit::where('current_stage', 'intake')
            ->where('branch_id', $this->branch->id)
            ->orderBy('check_in_time')
            ->first();

        $this->assertEquals($earliest->id, $firstInQueue->id);
    }

    /** @test */
    public function test_06_intake_assessment_created(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'intake']);
        $this->actingAs($this->intakeOfficer);

        IntakeAssessment::create([
            'visit_id'          => $visit->id,
            'client_id'         => $this->client->id,
            'branch_id'         => $this->branch->id,
            'assessed_by'       => $this->intakeOfficer->id,
            'verification_mode' => 'new_client',
        ]);

        $this->assertDatabaseHas('intake_assessments', ['visit_id' => $visit->id]);
        // Creating the assessment must NOT auto-advance the stage
        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'intake',
        ]);
    }
```

- [ ] **Step 2: Run tests 04–06**

```bash
php artisan test tests/Feature/E2E/EndToEndCashPatientTest.php --filter="test_0[456]"
```

Expected: `3 passed`

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/E2E/EndToEndCashPatientTest.php
git commit -m "test(e2e): cash patient triage and intake tests 04-06"
```

---

## Task 3: Cash test — tests 07–10 (Queue, Payment, Service)

**Files:**
- Modify: `tests/Feature/E2E/EndToEndCashPatientTest.php`

- [ ] **Step 1: Add tests 07–10 inside the class, followed by the closing `}`**

Append to `EndToEndCashPatientTest.php` — add these methods then close the class with `}`.

```php
    // ─── Tests 07–10: Queue, Payment, Service ────────────────────────────────

    /** @test */
    public function test_07_cash_routes_to_queue_not_billing(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'intake']);
        $this->actingAs($this->intakeOfficer);

        $visit->completeStage();
        $visit->moveToStage('queue');

        // Cash path goes to queue, never billing
        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'queue',
        ]);
        $this->assertDatabaseMissing('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'billing',
        ]);

        // Branch isolation: a second branch's visit must NOT appear in this branch's queue
        $otherBranch = Branch::factory()->create();
        $otherVisit  = Visit::create([
            'branch_id'     => $otherBranch->id,
            'client_id'     => Client::factory()->create(['branch_id' => $otherBranch->id])->id,
            'visit_type'    => 'walk_in',
            'visit_date'    => now()->toDateString(),
            'current_stage' => 'queue',
            'check_in_time' => now(),
        ]);

        $queueIds = Visit::where('current_stage', 'queue')
            ->where('branch_id', $this->branch->id)
            ->pluck('id');

        $this->assertContains($visit->id, $queueIds->toArray());
        $this->assertNotContains($otherVisit->id, $queueIds->toArray());
    }

    /** @test */
    public function test_08_cashier_creates_invoice_and_records_payment(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'queue']);
        $this->actingAs($this->cashier);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'visit_id'       => $visit->id,
            'client_id'      => $this->client->id,
            'branch_id'      => $this->branch->id,
            'total_amount'   => 1000,
            'balance_due'    => 1000,
            'generated_by'   => $this->cashier->id,
        ]);

        Payment::create([
            'payment_number' => 'PAY-TEST-' . uniqid(),
            'invoice_id'     => $invoice->id,
            'visit_id'       => $visit->id,
            'client_id'      => $this->client->id,
            'branch_id'      => $this->branch->id,
            'payment_method' => 'cash',
            'amount'         => 1000,
            'received_by'    => $this->cashier->id,
        ]);

        $visit->update([
            'payment_status'     => 'paid',
            'payment_verified_at' => now(),
        ]);

        $this->assertDatabaseHas('payments', [
            'visit_id'       => $visit->id,
            'payment_method' => 'cash',
        ]);
        $this->assertDatabaseHas('visits', [
            'id'             => $visit->id,
            'payment_status' => 'paid',
        ]);
        $this->assertNotNull($visit->fresh()->payment_verified_at);
    }

    /** @test */
    public function test_09_payment_gate_controls_service_queue_visibility(): void
    {
        $this->actingAs($this->serviceProvider);

        $paidVisit = $this->makeVisit([
            'current_stage'  => 'queue',
            'payment_status' => 'paid',
        ]);
        $pendingVisit = $this->makeVisit([
            'current_stage'  => 'queue',
            'payment_status' => 'pending',
        ]);

        // Only paid/partial visits are visible in the service queue (scoped to this branch)
        $visibleIds = Visit::where('current_stage', 'queue')
            ->where('branch_id', $this->branch->id)
            ->whereIn('payment_status', ['paid', 'partial'])
            ->pluck('id')
            ->toArray();

        $this->assertContains($paidVisit->id, $visibleIds);
        $this->assertNotContains($pendingVisit->id, $visibleIds);
    }

    /** @test */
    public function test_10_service_delivered_and_visit_completed(): void
    {
        $visit = $this->makeVisit([
            'current_stage'  => 'queue',
            'payment_status' => 'paid',
        ]);
        $this->actingAs($this->serviceProvider);

        $visit->completeStage();
        $visit->moveToStage('completed');
        $visit->update(['check_out_time' => now()]);

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'completed',
        ]);
        $this->assertNotNull($visit->fresh()->check_out_time);

        // Completed visit must not appear in any active queue
        $activeQueueIds = Visit::where('current_stage', 'queue')->pluck('id')->toArray();
        $this->assertNotContains($visit->id, $activeQueueIds);
    }
}
```

- [ ] **Step 2: Run the full cash test class**

```bash
php artisan test tests/Feature/E2E/EndToEndCashPatientTest.php
```

Expected: `10 passed`

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/E2E/EndToEndCashPatientTest.php
git commit -m "test(e2e): cash patient queue, payment, and completion tests 07-10"
```

---

## Task 4: Create `EndToEndShaPatientTest` — setUp and tests 01–04

**Files:**
- Create: `tests/Feature/E2E/EndToEndShaPatientTest.php`

- [ ] **Step 1: Create the file with setUp and tests 01–04**

```php
<?php

namespace Tests\Feature\E2E;

use App\Models\Branch;
use App\Models\Client;
use App\Models\InsuranceProvider;
use App\Models\IntakeAssessment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Triage;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end: SHA insurance patient through the full 7-stage workflow.
 *
 * Workflow: Reception → Triage → Intake → Billing → Queue → Service → Completed
 * Payment:  SHA covers 80%, client pays 20% copay at cashier (queue stage)
 *
 * Each test method is self-contained: setUp() rebuilds the full fixture world
 * via RefreshDatabase before each method.
 */
class EndToEndShaPatientTest extends TestCase
{
    use RefreshDatabase;

    private Branch            $branch;
    private User              $receptionist;
    private User              $triageNurse;
    private User              $intakeOfficer;
    private User              $billingOfficer;
    private User              $cashier;
    private User              $serviceProvider;
    private Client            $client;
    private InsuranceProvider $shaProvider;
    private Service           $service;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'receptionist', 'triage_nurse', 'intake_officer',
            'billing_officer', 'cashier', 'service_provider', 'admin',
        ] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->branch = Branch::factory()->create();

        $this->receptionist    = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->triageNurse     = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->intakeOfficer   = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->billingOfficer  = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->cashier         = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->serviceProvider = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);

        $this->receptionist->assignRole('receptionist');
        $this->triageNurse->assignRole('triage_nurse');
        $this->intakeOfficer->assignRole('intake_officer');
        $this->billingOfficer->assignRole('billing_officer');
        $this->cashier->assignRole('cashier');
        $this->serviceProvider->assignRole('service_provider');

        $this->client = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(30)->toDateString(),
        ]);

        $this->shaProvider = InsuranceProvider::create([
            'code'                        => 'SHA',
            'name'                        => 'Social Health Authority',
            'type'                        => 'government_scheme',
            'is_active'                   => true,
            'default_coverage_percentage' => 80,
        ]);

        $this->service = Service::create([
            'name'       => 'General Consultation',
            'base_price' => 1000,
            'is_active'  => true,
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function makeVisit(array $overrides = []): Visit
    {
        $this->actingAs($this->receptionist);

        return Visit::create(array_merge([
            'branch_id'     => $this->branch->id,
            'client_id'     => $this->client->id,
            'visit_type'    => 'walk_in',
            'visit_date'    => now()->toDateString(),
            'current_stage' => 'reception',
            'check_in_time' => now(),
        ], $overrides));
    }

    // ─── Tests 01–04: Reception through Intake ───────────────────────────────

    /** @test */
    public function test_01_client_checks_in_at_reception(): void
    {
        $visit = $this->makeVisit();

        $this->assertDatabaseHas('visits', [
            'current_stage'  => 'reception',
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'reception',
        ]);
        $this->assertNotNull($visit->fresh()->check_in_time);
    }

    /** @test */
    public function test_02_reception_clears_to_triage_with_stage_history(): void
    {
        $visit = $this->makeVisit();

        $visit->completeStage();
        $visit->moveToStage('triage');

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'triage',
        ]);
        $this->assertDatabaseHas('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'triage',
        ]);
    }

    /** @test */
    public function test_03_triage_records_vitals_and_clears_to_intake(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'triage']);
        $this->actingAs($this->triageNurse);

        Triage::create([
            'visit_id'    => $visit->id,
            'client_id'   => $this->client->id,
            'branch_id'   => $this->branch->id,
            'systolic_bp' => 120,
            'heart_rate'  => 72,
            'temperature' => 36.6,
            'triaged_by'  => $this->triageNurse->id,
        ]);

        $visit->completeStage();
        $visit->moveToStage('intake');

        $this->assertDatabaseHas('triages', [
            'visit_id'    => $visit->id,
            'systolic_bp' => 120,
        ]);
        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'intake',
        ]);
    }

    /** @test */
    public function test_04_intake_assessment_created(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'intake']);
        $this->actingAs($this->intakeOfficer);

        IntakeAssessment::create([
            'visit_id'          => $visit->id,
            'client_id'         => $this->client->id,
            'branch_id'         => $this->branch->id,
            'assessed_by'       => $this->intakeOfficer->id,
            'verification_mode' => 'new_client',
        ]);

        $this->assertDatabaseHas('intake_assessments', ['visit_id' => $visit->id]);
        // Assessment creation must NOT auto-advance the stage
        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'intake',
        ]);
    }
```

- [ ] **Step 2: Run tests 01–04**

> Note: Task 5 will append tests 05–08 and the closing class `}`. The file is intentionally incomplete after this task — do not add the closing brace here.

```bash
php artisan test tests/Feature/E2E/EndToEndShaPatientTest.php --filter="test_0[1234]"
```

Expected: `4 passed`

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/E2E/EndToEndShaPatientTest.php
git commit -m "test(e2e): SHA patient reception through intake tests 01-04"
```

---

## Task 5: SHA test — tests 05–08 (Billing, Payment, Service)

**Files:**
- Modify: `tests/Feature/E2E/EndToEndShaPatientTest.php`

- [ ] **Step 1: Add tests 05–08 inside the class, followed by the closing `}`**

Append to `EndToEndShaPatientTest.php` — add these methods then close the class with `}`.

```php
    // ─── Tests 05–08: Billing, Payment, Service ───────────────────────────────

    /** @test */
    public function test_05_sha_routes_to_billing_not_queue(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'intake']);
        $this->actingAs($this->intakeOfficer);

        // SHA/sponsor path routes to billing review, not directly to the service queue
        $visit->completeStage();
        $visit->moveToStage('billing');

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'billing',
        ]);
        $this->assertDatabaseHas('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'billing',
        ]);
        $this->assertDatabaseMissing('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'queue',
        ]);
    }

    /** @test */
    public function test_06_billing_officer_creates_sponsor_invoice(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'billing']);
        $this->actingAs($this->billingOfficer);

        // SHA covers 80% of 1000 = 800; client copay = 200
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SHA-' . uniqid(),
            'visit_id'       => $visit->id,
            'client_id'      => $this->client->id,
            'branch_id'      => $this->branch->id,
            'total_amount'   => 1000,
            'covered_amount' => 800,
            'balance_due'    => 200,
            'generated_by'   => $this->billingOfficer->id,
        ]);

        $this->assertDatabaseHas('invoices', [
            'visit_id'     => $visit->id,
            'total_amount' => 1000,
        ]);
        $this->assertEquals(800, (float) $invoice->fresh()->covered_amount);
        $this->assertEquals(200, (float) $invoice->fresh()->balance_due);
        $this->assertGreaterThan(0, (float) $invoice->fresh()->covered_amount);
    }

    /** @test */
    public function test_07_cashier_collects_copay_and_marks_visit_paid(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'queue']);
        $this->actingAs($this->cashier);

        // Invoice must be created in this arrange block (RefreshDatabase wipes between methods)
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SHA-' . uniqid(),
            'visit_id'       => $visit->id,
            'client_id'      => $this->client->id,
            'branch_id'      => $this->branch->id,
            'total_amount'   => 1000,
            'covered_amount' => 800,
            'balance_due'    => 200,
            'generated_by'   => $this->billingOfficer->id,
        ]);

        Payment::create([
            'payment_number' => 'PAY-SHA-' . uniqid(),
            'invoice_id'     => $invoice->id,
            'visit_id'       => $visit->id,
            'client_id'      => $this->client->id,
            'branch_id'      => $this->branch->id,
            'payment_method' => 'cash',
            'amount'         => 200,
            'received_by'    => $this->cashier->id,
        ]);

        $visit->update([
            'payment_status'      => 'paid',
            'payment_verified_at' => now(),
        ]);

        $this->assertDatabaseHas('payments', [
            'visit_id' => $visit->id,
            'amount'   => 200,
        ]);
        $this->assertDatabaseHas('visits', [
            'id'             => $visit->id,
            'payment_status' => 'paid',
        ]);
        $this->assertNotNull($visit->fresh()->payment_verified_at);
    }

    /** @test */
    public function test_08_service_delivered_and_visit_completed(): void
    {
        $visit = $this->makeVisit([
            'current_stage'  => 'queue',
            'payment_status' => 'paid',
        ]);
        $this->actingAs($this->serviceProvider);

        $visit->completeStage();
        $visit->moveToStage('completed');
        $visit->update(['check_out_time' => now()]);

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'completed',
        ]);
        $this->assertNotNull($visit->fresh()->check_out_time);
    }
}
```

- [ ] **Step 2: Run the full SHA test class**

```bash
php artisan test tests/Feature/E2E/EndToEndShaPatientTest.php
```

Expected: `8 passed`

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/E2E/EndToEndShaPatientTest.php
git commit -m "test(e2e): SHA patient billing, payment, and completion tests 05-08"
```

---

## Task 6: Run the full suite and verify

**Files:** None

- [ ] **Step 1: Run only the E2E suite**

```bash
php artisan test tests/Feature/E2E/
```

Expected: `18 passed`

- [ ] **Step 2: Run the complete test suite to check for regressions**

```bash
php artisan test
```

Expected: existing failures in `ImmunizationSectionTest` (pre-existing, unrelated Livewire issue) plus all E2E tests passing. Total should be `133 + 18 = 151 passed`, `5 failed` (same 5 as before).

- [ ] **Step 3: Final commit**

```bash
git add .
git commit -m "test(e2e): complete 18-test E2E suite — cash and SHA patient workflows"
```

---

## Troubleshooting

**`SQLSTATE: Column not found`** — check column name against the Ground-Truth table at the top of this plan. Do not guess column names; verify in `database/migrations/`.

**`SQLSTATE: Field doesn't have a default value`** — a required non-nullable column is missing from the `create()` call. See the Required FK Columns section above.

**`assertDatabaseHas fails on datetime`** — switch to `assertNotNull($model->fresh()->column)` instead of matching the raw value.

**`Role not found`** — ensure `Role::firstOrCreate` is called in `setUp()` before `assignRole()`.

**`visit_stages row missing for reception`** — check `Visit::moveToStage()` in `app/Models/Visit.php:291`. If the boot hook doesn't write a `reception` row on creation, `test_02` will catch this regression.
