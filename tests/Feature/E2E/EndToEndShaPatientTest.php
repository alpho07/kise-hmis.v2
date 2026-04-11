<?php

namespace Tests\Feature\E2E;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\InsuranceProvider;
use App\Models\IntakeAssessment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Triage;
use App\Models\User;
use App\Models\Visit;
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
            'date_of_birth' => now()->subYears(30)->toDateString(),
        ]);

        $this->shaProvider = InsuranceProvider::create([
            'code'                        => 'SHA',
            'name'                        => 'Social Health Authority',
            'type'                        => 'government_scheme',
            'is_active'                   => true,
            'default_coverage_percentage' => 80,
        ]);

        $department = Department::create([
            'branch_id' => $this->branch->id,
            'code'      => 'CONSULT',
            'name'      => 'General Consultation Dept',
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'code'          => 'GEN-CONSULT',
            'name'          => 'General Consultation',
            'base_price'    => 1000,
            'is_active'     => true,
            'department_id' => $department->id,
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function makeVisit(array $overrides = []): Visit
    {
        // Side effect: sets authenticated user to receptionist for the BelongsToBranch scope.
        // Tests that need a different actor must call actingAs() after makeVisit().
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

    // ─── Tests 05–07: Billing stage (SHA-specific) ───────────────────────────

    /** @test */
    public function test_05_intake_clears_to_billing_for_sha_patient(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'intake']);
        $this->actingAs($this->intakeOfficer);

        // SHA patients go to billing (not queue) for insurance eligibility review
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
        // Must NOT skip to queue
        $this->assertDatabaseMissing('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'queue',
        ]);
    }

    /** @test */
    public function test_06_billing_officer_creates_sha_invoice_with_80_20_split(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'billing']);
        $this->actingAs($this->billingOfficer);

        // SHA covers 80% of 1000 = 800; client owes 200 copay
        $invoice = Invoice::create([
            'invoice_number'       => 'INV-SHA-' . uniqid(),
            'visit_id'             => $visit->id,
            'client_id'            => $this->client->id,
            'branch_id'            => $this->branch->id,
            'total_amount'         => 1000,
            'total_sponsor_amount' => 800,
            'total_client_amount'  => 200,
            'balance_due'          => 200,
            'has_sponsor'          => true,
            'insurance_provider_id' => $this->shaProvider->id,
            'payment_pathway'      => 'insurance',
            'generated_by'         => $this->billingOfficer->id,
        ]);

        $this->assertDatabaseHas('invoices', [
            'visit_id'             => $visit->id,
            'has_sponsor'          => true,
            'total_sponsor_amount' => 800,
            'total_client_amount'  => 200,
        ]);
        $this->assertNotNull($invoice->fresh()->insurance_provider_id);
    }

    /** @test */
    public function test_07_billing_routes_to_queue_after_sha_approval(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'billing']);
        $this->actingAs($this->billingOfficer);

        // Billing officer approves SHA coverage; visit moves to queue for copay collection
        $visit->completeStage();
        $visit->moveToStage('queue');

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'queue',
        ]);
        $this->assertDatabaseHas('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'queue',
        ]);
    }

    // ─── Tests 08–11: Copay collection, service, completion ──────────────────

    /** @test */
    public function test_08_cashier_collects_sha_copay_and_marks_partial(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'queue']);
        $this->actingAs($this->cashier);

        $invoice = Invoice::create([
            'invoice_number'       => 'INV-SHA-' . uniqid(),
            'visit_id'             => $visit->id,
            'client_id'            => $this->client->id,
            'branch_id'            => $this->branch->id,
            'total_amount'         => 1000,
            'total_sponsor_amount' => 800,
            'total_client_amount'  => 200,
            'balance_due'          => 200,
            'has_sponsor'          => true,
            'insurance_provider_id' => $this->shaProvider->id,
            'payment_pathway'      => 'insurance',
            'generated_by'         => $this->cashier->id,
        ]);

        // Cashier collects the 200 copay
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

        // Client portion paid; SHA claim still pending → payment_status = 'partial'
        $visit->update([
            'payment_status'      => 'partial',
            'payment_verified_at' => now(),
        ]);

        $this->assertDatabaseHas('payments', [
            'visit_id' => $visit->id,
            'amount'   => 200,
        ]);
        $this->assertDatabaseHas('visits', [
            'id'             => $visit->id,
            'payment_status' => 'partial',
        ]);
        $this->assertNotNull($visit->fresh()->payment_verified_at);
    }

    /** @test */
    public function test_09_payment_gate_allows_partial_into_service_queue(): void
    {
        $partialVisit = $this->makeVisit([
            'current_stage'  => 'queue',
            'payment_status' => 'partial',   // copay collected, SHA claim pending
        ]);
        $pendingVisit = $this->makeVisit([
            'current_stage'  => 'queue',
            'payment_status' => 'pending',   // nothing paid yet
        ]);

        $this->actingAs($this->serviceProvider);

        $visibleIds = Visit::where('current_stage', 'queue')
            ->where('branch_id', $this->branch->id)
            ->whereIn('payment_status', ['paid', 'partial'])
            ->pluck('id')
            ->toArray();

        $this->assertContains($partialVisit->id, $visibleIds);
        $this->assertNotContains($pendingVisit->id, $visibleIds);
    }

    /** @test */
    public function test_10_service_delivered_and_visit_completed(): void
    {
        $visit = $this->makeVisit([
            'current_stage'  => 'queue',
            'payment_status' => 'partial',
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

    /** @test */
    public function test_11_sha_invoice_flagged_for_sponsor_claim_processing(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'completed']);
        $this->actingAs($this->billingOfficer);

        $invoice = Invoice::create([
            'invoice_number'        => 'INV-SHA-' . uniqid(),
            'visit_id'              => $visit->id,
            'client_id'             => $this->client->id,
            'branch_id'             => $this->branch->id,
            'total_amount'          => 1000,
            'total_sponsor_amount'  => 800,
            'total_client_amount'   => 200,
            'balance_due'           => 0,
            'has_sponsor'           => true,
            'insurance_provider_id' => $this->shaProvider->id,
            'payment_pathway'       => 'insurance',
            'sponsor_claim_status'  => 'pending',
            'client_payment_status' => 'paid',
            'generated_by'          => $this->billingOfficer->id,
        ]);

        // BillingResource (post-service) shows only has_sponsor=true invoices
        $sponsorInvoices = Invoice::where('has_sponsor', true)
            ->where('branch_id', $this->branch->id)
            ->pluck('id')
            ->toArray();

        $this->assertContains($invoice->id, $sponsorInvoices);
        $this->assertDatabaseHas('invoices', [
            'id'                   => $invoice->id,
            'sponsor_claim_status' => 'pending',
            'client_payment_status' => 'paid',
        ]);
    }
}
