# Browser Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a two-tier browser test suite — Livewire/Filament component tests (form submission, action buttons, role gates) for all six workflow stages, plus one Dusk real-browser smoke test for the full cash patient flow.

**Architecture:** `WorkflowFixture` trait provides shared seed data for both suites. Six `tests/Feature/UI/` files test one Filament resource page each via `Livewire::test()`. One `tests/Browser/CashPatientWorkflowTest.php` drives a real Chrome browser through login → service completion.

**Tech Stack:** PHP 8.4, Laravel 12, Filament v3, Livewire v3, PHPUnit 11, Laravel Dusk, SQLite (Livewire tests), SQLite file-based (Dusk tests).

**Spec:** `docs/superpowers/specs/2026-04-06-browser-tests-design.md`

---

## Task 0a: Fix intake stage routing bug

**Files:**
- Modify: `app/Filament/Pages/IntakeAssessmentEditor.php:1195`
- Modify: `app/Filament/Resources/IntakeAssessmentResource/Pages/CreateIntakeAssessment.php:840`

Both intake completion paths call `moveToStage('queue')` but `CashierQueueResource` filters on `current_stage = 'cashier'`. Visits never reach the cashier without this fix.

- [ ] **Step 1: Verify the bug**

```bash
php artisan test --filter=CashierQueueResourceTest 2>/dev/null || true
grep -n "moveToStage('queue')" \
  app/Filament/Pages/IntakeAssessmentEditor.php \
  app/Filament/Resources/IntakeAssessmentResource/Pages/CreateIntakeAssessment.php
```

Expected: two lines printed with `moveToStage('queue')`.

- [ ] **Step 2: Fix both lines**

In `app/Filament/Pages/IntakeAssessmentEditor.php` at line 1195:
```php
// Before:
$visit->moveToStage('queue');
// After:
$visit->moveToStage('cashier');
```

In `app/Filament/Resources/IntakeAssessmentResource/Pages/CreateIntakeAssessment.php` at line 840:
```php
// Before:
$visit->moveToStage('queue');
// After:
$visit->moveToStage('cashier');
```

- [ ] **Step 3: Verify fix**

```bash
rtk grep -n "moveToStage" \
  app/Filament/Pages/IntakeAssessmentEditor.php \
  app/Filament/Resources/IntakeAssessmentResource/Pages/CreateIntakeAssessment.php \
  | grep queue
```

Expected: no output (no more `'queue'` stage references).

- [ ] **Step 4: Run existing E2E tests to confirm nothing broken**

```bash
php artisan test tests/Feature/E2E/ --no-coverage
```

Expected: all 21 passing.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/IntakeAssessmentEditor.php \
        app/Filament/Resources/IntakeAssessmentResource/Pages/CreateIntakeAssessment.php
git commit -m "fix: route cash intake completion to 'cashier' stage (not 'queue')"
```

---

## Task 0b: Fix RoleSeeder billing role name

**Files:**
- Modify: `database/seeders/RoleSeeder.php:22` (name: `billing_admin` → `billing_officer`)
- Modify: `database/seeders/RoleSeeder.php:119` (query: `billing_admin` → `billing_officer`)

`BillingResource::shouldRegisterNavigation()` checks for `billing_officer`. The seeder creates `billing_admin`. Tests that create the `billing_officer` role in `WorkflowFixture` would silently create an unpermissioned duplicate role if the seeder is not fixed.

- [ ] **Step 1: Make the change in RoleSeeder**

At line 22, change:
```php
'billing_admin' => 'Billing Admin',
```
to:
```php
'billing_officer' => 'Billing Officer',
```

At line 119, change:
```php
$billingOfficer = Role::where('name', 'billing_admin')->first();
```
to:
```php
$billingOfficer = Role::where('name', 'billing_officer')->first();
```

- [ ] **Step 2: Run existing tests**

```bash
php artisan test --no-coverage
```

Expected: all existing tests still pass (no test depends on `billing_admin` role name).

- [ ] **Step 3: Commit**

```bash
git add database/seeders/RoleSeeder.php
git commit -m "fix: rename billing_admin role to billing_officer in RoleSeeder"
```

---

## Task 0c: Fix ReceptionResource — wrong model and empty form

**Files:**
- Modify: `app/Filament/Resources/ReceptionResource.php`

`ReceptionResource` currently uses `App\Models\Reception` which maps to `claim_items` table — completely wrong for checking in patients. The form schema is empty. The `CreateReception` page already has correct `mutateFormDataBeforeCreate()` logic that expects `Visit` fields. This task switches the model and adds the minimum form fields.

- [ ] **Step 1: Update the model and form in ReceptionResource.php**

Replace the full file content:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReceptionResource\Pages;
use App\Models\Client;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReceptionResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Reception';
    protected static ?string $navigationGroup = 'Clinical Workflow';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole(['super_admin', 'admin', 'receptionist']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('client_id')
                ->label('Client')
                ->options(fn () => Client::orderBy('first_name')->get()->pluck('full_name', 'id'))
                ->searchable()
                ->required(),

            Forms\Components\Select::make('visit_type')
                ->label('Visit Type')
                ->options([
                    'walk_in'     => 'Walk-in',
                    'appointment' => 'Appointment',
                    'follow_up'   => 'Follow-up',
                ])
                ->default('walk_in')
                ->required(),

            Forms\Components\Select::make('service_available')
                ->label('Service Availability')
                ->options([
                    'yes' => 'Available',
                    'no'  => 'Not available today',
                ])
                ->default('yes')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.full_name')->label('Client')->searchable(),
                Tables\Columns\TextColumn::make('visit_number')->label('Visit #'),
                Tables\Columns\TextColumn::make('visit_type')->label('Type'),
                Tables\Columns\TextColumn::make('current_stage')->label('Stage')
                    ->badge(),
                Tables\Columns\TextColumn::make('check_in_time')->label('Checked In')->since(),
            ])
            ->defaultSort('check_in_time', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReceptions::route('/'),
            'create' => Pages\CreateReception::route('/create'),
            'view'   => Pages\ViewReception::route('/{record}'),
            'edit'   => Pages\EditReception::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('current_stage', 'reception')
            ->orderBy('check_in_time', 'asc');
    }
}
```

- [ ] **Step 2: Verify the app still boots**

```bash
php artisan route:list --name=filament.admin.resources.receptions 2>&1 | head -10
```

Expected: routes listed without errors.

- [ ] **Step 3: Run existing tests**

```bash
php artisan test --no-coverage
```

Expected: all passing.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/ReceptionResource.php
git commit -m "fix: ReceptionResource — use Visit model, add client check-in form"
```

---

## Task 1: WorkflowFixture shared trait

**Files:**
- Create: `tests/Support/WorkflowFixture.php`

This trait is used by every Livewire and Dusk test class. It seeds all roles, branch, one user per role, department, service, SHA provider, and client. It provides `makeVisitAt()` and `makeQueueEntry()` helpers.

- [ ] **Step 1: Create the trait**

```php
<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\InsuranceProvider;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

trait WorkflowFixture
{
    protected Branch            $branch;
    protected User              $receptionist;
    protected User              $triageNurse;
    protected User              $intakeOfficer;
    protected User              $billingOfficer;
    protected User              $cashier;
    protected User              $serviceProvider;
    protected Client            $client;
    protected Service           $service;
    protected InsuranceProvider $shaProvider;

    protected function seedWorkflowFixture(): void
    {
        foreach ([
            'receptionist', 'triage_nurse', 'intake_officer',
            'billing_officer', 'cashier', 'service_provider', 'admin', 'super_admin',
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
            'date_of_birth' => now()->subYears(30)->toDateString(),
        ]);

        $department = Department::create([
            'branch_id' => $this->branch->id,
            'code'      => 'CONSULT',
            'name'      => 'General Consultation',
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'code'          => 'GEN-CONSULT',
            'name'          => 'General Consultation',
            'base_price'    => 1000,
            'is_active'     => true,
            'department_id' => $department->id,
        ]);

        $this->shaProvider = InsuranceProvider::create([
            'code'                        => 'SHA',
            'name'                        => 'Social Health Authority',
            'type'                        => 'government_scheme',
            'is_active'                   => true,
            'default_coverage_percentage' => 80,
        ]);
    }

    /**
     * Create a visit at the given stage for the fixture client.
     * Authenticates as receptionist to satisfy BelongsToBranch scope.
     */
    protected function makeVisitAt(string $stage, array $overrides = []): Visit
    {
        $this->actingAs($this->receptionist);

        return Visit::create(array_merge([
            'branch_id'     => $this->branch->id,
            'client_id'     => $this->client->id,
            'visit_type'    => 'walk_in',
            'visit_date'    => now()->toDateString(),
            'current_stage' => $stage,
            'check_in_time' => now(),
        ], $overrides));
    }

    /**
     * Create a QueueEntry for a visit.
     * Required by ServiceQueueResource which queries QueueEntry, not Visit.
     */
    protected function makeQueueEntry(Visit $visit, array $overrides = []): QueueEntry
    {
        return QueueEntry::create(array_merge([
            'branch_id'  => $this->branch->id,
            'visit_id'   => $visit->id,
            'service_id' => $this->service->id,
            'status'     => 'waiting',
        ], $overrides));
    }

    /**
     * Create a user with a known plaintext password — for Dusk login form.
     */
    protected function makeUserWithPassword(string $role, string $password = 'password'): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create([
            'branch_id'  => $this->branch->id,
            'is_active'  => true,
            'password'   => Hash::make($password),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
```

- [ ] **Step 2: Verify the file parses**

```bash
php -l tests/Support/WorkflowFixture.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add tests/Support/WorkflowFixture.php
git commit -m "test: add WorkflowFixture shared trait for UI and Dusk tests"
```

---

## Task 2: ReceptionResourceTest

**Files:**
- Create: `tests/Feature/UI/ReceptionResourceTest.php`

Tests: (1) page renders for receptionist, (2) form submission creates a `Visit` at `reception` stage with `visit_stages` row, (3) role gate — cashier cannot access reception.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\ReceptionResource;
use App\Filament\Resources\ReceptionResource\Pages\CreateReception;
use App\Filament\Resources\ReceptionResource\Pages\ListReceptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class ReceptionResourceTest extends TestCase
{
    use RefreshDatabase, WorkflowFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorkflowFixture();
    }

    public function test_receptionist_can_load_reception_list(): void
    {
        $this->actingAs($this->receptionist);

        Livewire::test(ListReceptions::class)
            ->assertSuccessful();
    }

    public function test_create_form_saves_visit_at_reception_stage(): void
    {
        $this->actingAs($this->receptionist);

        Livewire::test(CreateReception::class)
            ->fillForm([
                'client_id'         => $this->client->id,
                'visit_type'        => 'walk_in',
                'service_available' => 'yes',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('visits', [
            'client_id'     => $this->client->id,
            'current_stage' => 'reception',
            'visit_type'    => 'walk_in',
        ]);

        $visit = \App\Models\Visit::where('client_id', $this->client->id)->first();
        $this->assertNotNull($visit);

        $this->assertDatabaseHas('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'reception',
        ]);
    }

    public function test_cashier_cannot_access_reception_resource(): void
    {
        // Role gate: cashier role does not include 'receptionist'
        $this->actingAs($this->cashier);

        $this->assertFalse(
            ReceptionResource::shouldRegisterNavigation(),
            'CashierQueueResource should not be visible to cashier in Reception nav'
        );
    }
}
```

- [ ] **Step 2: Run to verify it fails (test infrastructure check)**

```bash
php artisan test tests/Feature/UI/ReceptionResourceTest.php --no-coverage
```

Expected: may fail with "class not found" until file is created — but the file was just created, so expect some failures due to missing fixture data or form issues. Note any specific errors.

- [ ] **Step 3: Run and fix until green**

```bash
php artisan test tests/Feature/UI/ReceptionResourceTest.php --no-coverage
```

Expected: 3 passing.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/UI/ReceptionResourceTest.php
git commit -m "test(ui): ReceptionResource — form submission, stage creation, role gate"
```

---

## Task 3: TriageResourceTest

**Files:**
- Create: `tests/Feature/UI/TriageResourceTest.php`

The `start_triage` action on the queue is a URL redirect to `TriageResource::create`. Test the `CreateTriage` page directly. It reads `visit_id` from the hidden form field. `afterCreate()` calls `completeStage()` + `moveToStage('intake')` for new clients.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\TriageResource\Pages\CreateTriage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class TriageResourceTest extends TestCase
{
    use RefreshDatabase, WorkflowFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorkflowFixture();
    }

    public function test_triage_nurse_submits_vitals_and_creates_triage_record(): void
    {
        $visit = $this->makeVisitAt('triage');
        $this->actingAs($this->triageNurse);

        Livewire::test(CreateTriage::class)
            ->fillForm([
                'visit_id'    => $visit->id,
                'systolic_bp' => 120,
                'heart_rate'  => 72,
                'temperature' => 36.6,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('triages', [
            'visit_id'    => $visit->id,
            'systolic_bp' => 120,
            'heart_rate'  => 72,
        ]);
    }

    public function test_completing_triage_advances_new_client_to_intake(): void
    {
        $visit = $this->makeVisitAt('triage');
        $this->actingAs($this->triageNurse);

        Livewire::test(CreateTriage::class)
            ->fillForm([
                'visit_id'    => $visit->id,
                'systolic_bp' => 118,
                'heart_rate'  => 70,
                'temperature' => 36.5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'intake',
        ]);
    }

    public function test_receptionist_cannot_see_triage_queue_in_navigation(): void
    {
        $this->actingAs($this->receptionist);

        $hasAccess = $this->receptionist->hasRole(['super_admin', 'admin', 'triage_nurse']);
        $this->assertFalse($hasAccess);
    }
}
```

- [ ] **Step 2: Run the test**

```bash
php artisan test tests/Feature/UI/TriageResourceTest.php --no-coverage
```

Expected: 3 passing. If `afterCreate()` fails because of missing visit_stages or notification dependencies, check the error and resolve.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/UI/TriageResourceTest.php
git commit -m "test(ui): TriageResource — vitals form, stage advance to intake, role gate"
```

---

## Task 4: IntakeQueueResourceTest

**Files:**
- Create: `tests/Feature/UI/IntakeQueueResourceTest.php`

`start_intake` is a Livewire `->action()` that creates an `IntakeAssessment` and returns a redirect. Use `callTableAction()`. Assert the record was created; do NOT assert page content after the redirect.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\IntakeQueueResource;
use App\Filament\Resources\IntakeQueueResource\Pages\ListIntakeQueues;
use App\Models\IntakeAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class IntakeQueueResourceTest extends TestCase
{
    use RefreshDatabase, WorkflowFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorkflowFixture();
    }

    public function test_start_intake_creates_assessment_record(): void
    {
        $visit = $this->makeVisitAt('intake');
        $this->actingAs($this->intakeOfficer);

        Livewire::test(ListIntakeQueues::class)
            ->callTableAction('start_intake', $visit);

        // Action creates IntakeAssessment then redirects — assert DB, not page content
        $this->assertDatabaseHas('intake_assessments', [
            'visit_id'  => $visit->id,
            'client_id' => $this->client->id,
        ]);
    }

    public function test_cash_intake_completion_routes_to_cashier_not_billing(): void
    {
        $visit = $this->makeVisitAt('intake');
        $this->actingAs($this->intakeOfficer);

        // Directly advance stage the way IntakeAssessmentEditor does after cash path
        $visit->completeStage();
        $visit->moveToStage('cashier');

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'cashier',
        ]);
        $this->assertDatabaseMissing('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'billing',
        ]);
    }

    public function test_sha_intake_completion_routes_to_billing(): void
    {
        $visit = $this->makeVisitAt('intake');
        $this->actingAs($this->intakeOfficer);

        $visit->completeStage();
        $visit->moveToStage('billing');

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'billing',
        ]);
    }

    public function test_cashier_cannot_see_intake_queue_in_navigation(): void
    {
        $this->actingAs($this->cashier);

        $hasAccess = $this->cashier->hasRole(['super_admin', 'admin', 'intake_officer']);
        $this->assertFalse($hasAccess);
    }
}
```

- [ ] **Step 2: Run the test**

```bash
php artisan test tests/Feature/UI/IntakeQueueResourceTest.php --no-coverage
```

Expected: 4 passing.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/UI/IntakeQueueResourceTest.php
git commit -m "test(ui): IntakeQueueResource — start_intake action, routing, role gate"
```

---

## Task 5: BillingResourceTest

**Files:**
- Create: `tests/Feature/UI/BillingResourceTest.php`

Tests: (1) only `has_sponsor=true` invoices visible in resource, (2) `approve` action moves visit to `cashier` stage, (3) role gate.

`BillingResource` queries `Invoice` where `has_sponsor = true` and status in `[pending, verified, approved]`.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\BillingResource;
use App\Filament\Resources\BillingResource\Pages\ListBillings;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class BillingResourceTest extends TestCase
{
    use RefreshDatabase, WorkflowFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorkflowFixture();
    }

    private function makeShaInvoice(\App\Models\Visit $visit, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'invoice_number'        => 'INV-' . uniqid(),
            'visit_id'              => $visit->id,
            'client_id'             => $this->client->id,
            'branch_id'             => $this->branch->id,
            'total_amount'          => 1000,
            'total_sponsor_amount'  => 800,
            // total_client_amount > 0 ensures the approve action routes to 'cashier', not 'service'
            'total_client_amount'   => 200,
            'balance_due'           => 200,
            'has_sponsor'           => true,
            // Must be 'verified' — the approve action is only visible when status = 'verified'
            'status'                => 'verified',
            'insurance_provider_id' => $this->shaProvider->id,
            'payment_pathway'       => 'insurance',
            'generated_by'          => $this->billingOfficer->id,
        ], $overrides));
    }

    public function test_only_sponsor_invoices_appear_in_billing_resource(): void
    {
        $visit     = $this->makeVisitAt('billing');
        $this->actingAs($this->billingOfficer);

        $shaInvoice  = $this->makeShaInvoice($visit);
        $cashInvoice = Invoice::create([
            'invoice_number' => 'INV-CASH-' . uniqid(),
            'visit_id'       => $visit->id,
            'client_id'      => $this->client->id,
            'branch_id'      => $this->branch->id,
            'total_amount'   => 1000,
            'balance_due'    => 1000,
            'has_sponsor'    => false,
            'status'         => 'pending',
            'generated_by'   => $this->billingOfficer->id,
        ]);

        // BillingResource only shows has_sponsor = true
        $visibleIds = Invoice::where('has_sponsor', true)
            ->where('branch_id', $this->branch->id)
            ->pluck('id')->toArray();

        $this->assertContains($shaInvoice->id, $visibleIds);
        $this->assertNotContains($cashInvoice->id, $visibleIds);
    }

    public function test_approve_action_routes_visit_to_cashier_stage(): void
    {
        $visit = $this->makeVisitAt('billing');
        $this->actingAs($this->billingOfficer);

        $invoice = $this->makeShaInvoice($visit);

        Livewire::test(ListBillings::class)
            ->callTableAction('approve', $invoice, data: [
                // Field name from BillingResource.php approve action form (line ~311)
                'approval_notes' => 'SHA eligibility confirmed',
            ]);

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'cashier',
        ]);
    }

    public function test_cashier_cannot_see_billing_resource_in_navigation(): void
    {
        $this->actingAs($this->cashier);

        $hasAccess = $this->cashier->hasRole(['super_admin', 'admin', 'billing_officer']);
        $this->assertFalse($hasAccess);
    }
}
```

- [ ] **Step 2: Verify the `approve` action form field name**

```bash
rtk grep -n "approval_notes\|Textarea\|->form" \
  app/Filament/Resources/BillingResource.php | grep -A3 "approve"
```

Confirm the form field is `approval_notes`. If the name differs, update the `data:` key accordingly.

- [ ] **Step 3: Run the test**

```bash
php artisan test tests/Feature/UI/BillingResourceTest.php --no-coverage
```

Expected: 3 passing.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/UI/BillingResourceTest.php
git commit -m "test(ui): BillingResource — SHA visibility, approve action routes to cashier, role gate"
```

---

## Task 6: CashierQueueResourceTest

**Files:**
- Create: `tests/Feature/UI/CashierQueueResourceTest.php`

Tests: (1) `process_payment` action creates payment and marks visit `paid`, (2) pending visits not visible in service queue, (3) role gate.

`process_payment` in `CashierQueueResource.php:449` calls `HybridPaymentService::processHybridPayment()` and sets `payment_status = 'paid'` on the visit (line 461).

- [ ] **Step 1: Check the process_payment form fields**

```bash
rtk grep -n "cash_amount\|payment_method\|->form\|TextInput\|make(" \
  app/Filament/Resources/CashierQueueResource.php | grep -A3 "process_payment" | head -20
```

Note the exact field names for the `data:` array.

- [ ] **Step 2: Write the test**

```php
<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\CashierQueueResource\Pages\ListCashierQueues;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class CashierQueueResourceTest extends TestCase
{
    use RefreshDatabase, WorkflowFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorkflowFixture();
    }

    public function test_process_payment_marks_visit_paid_and_creates_payment_record(): void
    {
        $visit = $this->makeVisitAt('cashier');
        $this->actingAs($this->cashier);

        // CashierQueueResource requires an invoice with balance_due
        Invoice::create([
            'invoice_number' => 'INV-' . uniqid(),
            'visit_id'       => $visit->id,
            'client_id'      => $this->client->id,
            'branch_id'      => $this->branch->id,
            'total_amount'   => 1000,
            'balance_due'    => 1000,
            'has_sponsor'    => false,
            'status'         => 'pending',
            'generated_by'   => $this->cashier->id,
        ]);

        Livewire::test(ListCashierQueues::class)
            ->callTableAction('process_payment', $visit, data: [
                'cash_amount'    => 1000,
                'payment_method' => 'cash',
            ]);

        // HybridPaymentService sets payment_status on the Invoice, not Visit
        $this->assertDatabaseHas('invoices', [
            'visit_id'       => $visit->id,
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('payments', [
            'visit_id' => $visit->id,
        ]);
    }

    public function test_pending_payment_visit_not_visible_in_service_queue(): void
    {
        $paidVisit    = $this->makeVisitAt('cashier', ['payment_status' => 'paid']);
        $pendingVisit = $this->makeVisitAt('cashier', ['payment_status' => 'pending']);

        $this->actingAs($this->serviceProvider);

        $visibleIds = \App\Models\Visit::where('current_stage', 'cashier')
            ->where('branch_id', $this->branch->id)
            ->whereIn('payment_status', ['paid', 'partial'])
            ->pluck('id')->toArray();

        $this->assertContains($paidVisit->id, $visibleIds);
        $this->assertNotContains($pendingVisit->id, $visibleIds);
    }

    public function test_intake_officer_cannot_see_cashier_queue_in_navigation(): void
    {
        $this->actingAs($this->intakeOfficer);

        $hasAccess = $this->intakeOfficer->hasRole(['super_admin', 'admin', 'billing_officer', 'cashier']);
        $this->assertFalse($hasAccess);
    }
}
```

- [ ] **Step 3: Run the test**

```bash
php artisan test tests/Feature/UI/CashierQueueResourceTest.php --no-coverage
```

If `HybridPaymentService` requires more form data, adjust the `data:` array. Check the action's `->form()` in `CashierQueueResource.php` lines 270–440 for all fields.

Expected: 3 passing.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/UI/CashierQueueResourceTest.php
git commit -m "test(ui): CashierQueueResource — process_payment action, payment gate, role gate"
```

---

## Task 7: ServiceQueueResourceTest

**Files:**
- Create: `tests/Feature/UI/ServiceQueueResourceTest.php`

`ServiceQueueResource` queries `QueueEntry` (not `Visit`). Must seed `QueueEntry` via `makeQueueEntry()`. The `complete_service` action sets `QueueEntry.status='completed'` and `Visit.current_stage='completed'`. Does NOT set `check_out_time`.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\ServiceQueueResource\Pages\ListServiceQueues;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class ServiceQueueResourceTest extends TestCase
{
    use RefreshDatabase, WorkflowFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorkflowFixture();
    }

    public function test_only_paid_or_partial_queue_entries_are_visible(): void
    {
        $this->actingAs($this->serviceProvider);

        $paidVisit    = $this->makeVisitAt('cashier', ['payment_status' => 'paid']);
        $pendingVisit = $this->makeVisitAt('cashier', ['payment_status' => 'pending']);

        $paidEntry    = $this->makeQueueEntry($paidVisit,    ['status' => 'waiting']);
        $pendingEntry = $this->makeQueueEntry($pendingVisit, ['status' => 'waiting']);

        // ServiceQueueResource payment gate: visit.payment_status must be paid or partial
        $visibleEntryIds = \App\Models\QueueEntry::whereHas('visit', function ($q) {
            $q->whereIn('payment_status', ['paid', 'partial']);
        })->pluck('id')->toArray();

        $this->assertContains($paidEntry->id, $visibleEntryIds);
        $this->assertNotContains($pendingEntry->id, $visibleEntryIds);
    }

    public function test_complete_service_marks_entry_and_visit_completed(): void
    {
        $visit = $this->makeVisitAt('cashier', ['payment_status' => 'paid']);
        $this->actingAs($this->serviceProvider);

        $entry = $this->makeQueueEntry($visit, ['status' => 'in_service']);

        // Form fields from ServiceQueueResource.php complete_service action (~line 296–309)
        Livewire::test(ListServiceQueues::class)
            ->callTableAction('complete_service', $entry, data: [
                'completion_notes' => 'Consultation completed.',
                'actual_duration'  => 30,
            ]);

        $this->assertDatabaseHas('queue_entries', [
            'id'     => $entry->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'completed',
        ]);
        // check_out_time is NOT set by this action — do not assert it
    }

    public function test_receptionist_cannot_see_service_queue_in_navigation(): void
    {
        $this->actingAs($this->receptionist);

        $hasAccess = $this->receptionist->hasRole(['super_admin', 'admin', 'service_provider']);
        $this->assertFalse($hasAccess);
    }
}
```

- [ ] **Step 2: Verify complete_service form field names**

```bash
rtk grep -n "completion_notes\|actual_duration\|Textarea\|TextInput" \
  app/Filament/Resources/ServiceQueueResource.php | grep -A3 "complete_service" | head -20
```

Confirm fields are `completion_notes` and `actual_duration`. Adjust if different.

- [ ] **Step 3: Run the test**

```bash
php artisan test tests/Feature/UI/ServiceQueueResourceTest.php --no-coverage
```

Expected: 3 passing.

- [ ] **Step 4: Run the full Livewire UI suite**

```bash
php artisan test tests/Feature/UI/ --no-coverage
```

Expected: all 18 tests passing across 6 files.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/UI/ServiceQueueResourceTest.php
git commit -m "test(ui): ServiceQueueResource — payment gate, complete_service action, role gate"
```

---

## Task 8: Install and configure Laravel Dusk

**Files:**
- Modify: `composer.json` (via `composer require`)
- Create: `tests/Browser/` directory (via `dusk:install`)
- Create: `.env.dusk.local`
- Modify: `tests/DuskTestCase.php` (adjust browser options)

- [ ] **Step 1: Install Dusk**

```bash
composer require --dev laravel/dusk
```

- [ ] **Step 2: Scaffold Dusk**

```bash
php artisan dusk:install
```

Expected output: `Dusk scaffolding installed successfully.`
This creates `tests/Browser/`, `tests/DuskTestCase.php`, and `tests/Browser/Pages/`.

- [ ] **Step 3: Install matching ChromeDriver**

```bash
php artisan dusk:chrome-driver --detect
```

Expected: downloads ChromeDriver matching your installed Chrome version.

- [ ] **Step 4: Create `.env.dusk.local`**

Copy `.env` then override these keys:

```
APP_ENV=testing
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/kise-hmis.v2/database/testing_dusk.sqlite
SESSION_DRIVER=file
CACHE_STORE=file
```

Replace `/absolute/path/to/` with the actual path from `pwd`.

- [ ] **Step 5: Configure DuskTestCase for headless Chrome**

In `tests/DuskTestCase.php`, find `driver()` and enable headless:

```php
protected function driver(): RemoteWebDriver
{
    $options = (new ChromeOptions)->addArguments([
        '--disable-gpu',
        '--headless',
        '--no-sandbox',
        '--window-size=1280,960',
    ]);

    return RemoteWebDriver::create(
        $_ENV['DUSK_DRIVER_URL'] ?? 'http://localhost:9515',
        DesiredCapabilities::chrome()->setCapability(
            ChromeOptions::CAPABILITY, $options
        )
    );
}
```

- [ ] **Step 6: Smoke test Dusk is configured**

Start the app server in one terminal:
```bash
php artisan serve --port=8000
```

In another terminal:
```bash
php artisan dusk --version
```

Expected: version printed without errors.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock tests/DuskTestCase.php .env.dusk.local
git commit -m "chore: install and configure Laravel Dusk for browser smoke tests"
```

---

## Task 9: CashPatientWorkflowTest (Dusk)

**Files:**
- Create: `tests/Browser/CashPatientWorkflowTest.php`

Six independent browser methods. Each seeds its own prerequisite state, logs in as the appropriate role, performs its action, and asserts the result. `DatabaseMigrations` resets the DB before each method.

- [ ] **Step 1: Write the test class**

```php
<?php

namespace Tests\Browser;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class CashPatientWorkflowTest extends DuskTestCase
{
    use DatabaseMigrations;

    // ─── Shared seed helpers ──────────────────────────────────────────────────

    private function seedBase(): array
    {
        foreach ([
            'receptionist', 'triage_nurse', 'intake_officer',
            'cashier', 'service_provider', 'admin',
        ] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $branch = Branch::factory()->create();
        $dept   = Department::create([
            'branch_id' => $branch->id,
            'code'      => 'CONSULT',
            'name'      => 'General Consultation',
            'is_active' => true,
        ]);
        $service = Service::create([
            'code'          => 'GEN-CONSULT',
            'name'          => 'General Consultation',
            'base_price'    => 1000,
            'is_active'     => true,
            'department_id' => $dept->id,
        ]);
        $client = Client::factory()->create([
            'branch_id'     => $branch->id,
            'date_of_birth' => now()->subYears(30)->toDateString(),
        ]);

        return compact('branch', 'service', 'client');
    }

    private function makeUser(string $role, Branch $branch, string $password = 'password'): User
    {
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
            'password'  => Hash::make($password),
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function makeVisit(Branch $branch, Client $client, string $stage, array $extra = []): Visit
    {
        return Visit::create(array_merge([
            'branch_id'     => $branch->id,
            'client_id'     => $client->id,
            'visit_type'    => 'walk_in',
            'visit_date'    => now()->toDateString(),
            'current_stage' => $stage,
            'check_in_time' => now(),
        ], $extra));
    }

    // ─── Test methods ─────────────────────────────────────────────────────────

    public function test_01_receptionist_logs_in_and_sees_reception_nav(): void
    {
        ['branch' => $branch] = $this->seedBase();
        $user = $this->makeUser('receptionist', $branch);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/admin/login')
                    ->type('email', $user->email)
                    ->type('password', 'password')
                    ->press('Sign in')
                    ->assertPathIs('/admin')
                    ->assertSee('Reception');
        });
    }

    public function test_02_receptionist_registers_client_and_checks_in(): void
    {
        ['branch' => $branch, 'client' => $client] = $this->seedBase();
        $user = $this->makeUser('receptionist', $branch);

        $this->browse(function (Browser $browser) use ($user, $client) {
            $browser->loginAs($user)
                    ->visit('/admin/receptions/create')
                    ->pause(500)
                    ->assertSee('Client')
                    // Select client (Filament Select component)
                    ->click('[wire\\:key*="client_id"] input')
                    ->waitFor('[wire\\:key*="client_id"] [role="option"]', 5)
                    ->click('[wire\\:key*="client_id"] [role="option"]:first-child')
                    ->select('[wire\\:key*="visit_type"] select', 'walk_in')
                    ->press('Create')
                    ->waitForText('Check-In Successful', 10)
                    ->assertSee('Check-In Successful');
        });
    }

    public function test_03_triage_nurse_records_vitals(): void
    {
        ['branch' => $branch, 'client' => $client] = $this->seedBase();
        $nurse = $this->makeUser('triage_nurse', $branch);
        $visit = $this->makeVisit($branch, $client, 'triage');

        $this->browse(function (Browser $browser) use ($nurse, $visit) {
            $browser->loginAs($nurse)
                    ->visit('/admin/triage-queues')
                    ->waitForText('Start Triage', 10)
                    // Filament v3 table actions: click by button label text
                    ->clickLink('Start Triage')
                    ->waitForLocation('/admin/triages/create', 10)
                    ->waitFor('input[wire\\:model*="systolic_bp"]', 5)
                    ->type('input[wire\\:model*="systolic_bp"]', '120')
                    ->type('input[wire\\:model*="heart_rate"]', '72')
                    ->type('input[wire\\:model*="temperature"]', '36.6')
                    ->press('Create')
                    ->waitForText('created', 10);
        });

        $this->assertDatabaseHas('triages', ['visit_id' => $visit->id]);
    }

    public function test_04_intake_officer_starts_intake(): void
    {
        ['branch' => $branch, 'client' => $client] = $this->seedBase();
        $officer = $this->makeUser('intake_officer', $branch);
        $visit   = $this->makeVisit($branch, $client, 'intake');

        $this->browse(function (Browser $browser) use ($officer, $visit) {
            $browser->loginAs($officer)
                    ->visit('/admin/intake-queues')
                    ->waitForText('Start Intake', 10)
                    // Action creates IntakeAssessment then redirects away from list
                    ->clickLink('Start Intake')
                    ->pause(2000); // allow redirect and DB write to complete
        });

        $this->assertDatabaseHas('intake_assessments', ['visit_id' => $visit->id]);
    }

    public function test_05_cashier_processes_payment(): void
    {
        ['branch' => $branch, 'client' => $client] = $this->seedBase();
        $cashier = $this->makeUser('cashier', $branch);
        $visit   = $this->makeVisit($branch, $client, 'cashier');

        Invoice::create([
            'invoice_number' => 'INV-DUSK-001',
            'visit_id'       => $visit->id,
            'client_id'      => $client->id,
            'branch_id'      => $branch->id,
            'total_amount'   => 1000,
            'balance_due'    => 1000,
            'has_sponsor'    => false,
            'status'         => 'pending',
            'generated_by'   => $cashier->id,
        ]);

        $this->browse(function (Browser $browser) use ($cashier) {
            $browser->loginAs($cashier)
                    ->visit('/admin/cashier-queues')
                    ->waitForText('Process Payment', 10)
                    ->clickLink('Process Payment')
                    ->waitFor('[role="dialog"]', 5)
                    ->waitFor('input[wire\\:model*="cash_amount"]', 5)
                    ->type('input[wire\\:model*="cash_amount"]', '1000')
                    ->press('Confirm')
                    ->waitForText('Payment', 10);
        });

        // HybridPaymentService sets payment_status on Invoice, not Visit
        $this->assertDatabaseHas('invoices', [
            'visit_id'       => $visit->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_06_service_provider_completes_service(): void
    {
        ['branch' => $branch, 'client' => $client, 'service' => $service] = $this->seedBase();
        $provider = $this->makeUser('service_provider', $branch);
        $visit    = $this->makeVisit($branch, $client, 'cashier', ['payment_status' => 'paid']);

        $entry = QueueEntry::create([
            'branch_id'  => $branch->id,
            'visit_id'   => $visit->id,
            'service_id' => $service->id,
            'status'     => 'in_service',
        ]);

        $this->browse(function (Browser $browser) use ($provider) {
            $browser->loginAs($provider)
                    ->visit('/admin/service-queues')
                    ->waitForText('Complete Service', 10)
                    ->clickLink('Complete Service')
                    ->waitFor('[role="dialog"]', 5)
                    ->press('Confirm')
                    ->waitForText('completed', 10);
        });

        $this->assertDatabaseHas('queue_entries', [
            'id'     => $entry->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'completed',
        ]);
    }
}
```

- [ ] **Step 2: Start the dev server in a separate terminal**

```bash
php artisan serve --port=8000 --env=dusk
```

- [ ] **Step 3: Run Dusk tests one at a time to debug**

```bash
php artisan dusk tests/Browser/CashPatientWorkflowTest.php --filter=test_01
php artisan dusk tests/Browser/CashPatientWorkflowTest.php --filter=test_02
php artisan dusk tests/Browser/CashPatientWorkflowTest.php --filter=test_03
```

And so on. Dusk saves screenshots to `tests/Browser/screenshots/` on failure — check them.

**Common Dusk selectors for Filament v3:**
- Table action buttons: `->clickLink('Action Label')` — Filament v3 does NOT auto-register `dusk=` attributes; use button label text
- Filament form text inputs: `input[wire\:model*="field_name"]`
- Filament Select component: `->click('[wire\:key*="field_name"] input')` → wait for `[role="option"]` → click
- Modal confirm button: `->press('Confirm')` or `->press('Save')`
- Wait for notification: `->waitForText('Expected text', 10)`
- If `clickLink()` doesn't find the button, try `->click('button:contains("Label")')` or inspect with `->screenshot('debug')`

- [ ] **Step 4: Run all Dusk tests**

```bash
php artisan dusk tests/Browser/CashPatientWorkflowTest.php
```

Expected: 6 passing.

- [ ] **Step 5: Run entire test suite to confirm nothing broken**

```bash
php artisan test --no-coverage
```

Expected: all Feature + Unit tests still passing.

- [ ] **Step 6: Commit**

```bash
git add tests/Browser/CashPatientWorkflowTest.php
git commit -m "test(dusk): cash patient end-to-end browser smoke test (login → service completion)"
```

---

## Final verification

- [ ] **Run full Livewire UI suite**

```bash
php artisan test tests/Feature/UI/ --no-coverage
```

Expected: 18 tests, all passing.

- [ ] **Run full test suite**

```bash
php artisan test --no-coverage
```

Expected: all passing, no regressions.

- [ ] **Run Dusk suite**

```bash
php artisan dusk tests/Browser/ 
```

Expected: 6 passing.
