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
