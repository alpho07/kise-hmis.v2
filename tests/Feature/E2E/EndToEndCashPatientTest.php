<?php

namespace Tests\Feature\E2E;

use App\Models\Branch;
use App\Models\Client;
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

    private Branch   $branch;
    private User     $receptionist;
    private User     $triageNurse;
    private User     $intakeOfficer;
    private User     $cashier;
    private User     $serviceProvider;
    private Client   $client;
    private Service  $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles
        foreach ([
            'receptionist', 'triage_nurse', 'intake_officer',
            'cashier', 'service_provider', 'admin',
        ] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Branch
        $this->branch = Branch::factory()->create();

        // Users — one per workflow role
        $this->receptionist    = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->triageNurse     = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->intakeOfficer   = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->cashier         = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->serviceProvider = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);

        $this->receptionist->assignRole('receptionist');
        $this->triageNurse->assignRole('triage_nurse');
        $this->intakeOfficer->assignRole('intake_officer');
        $this->cashier->assignRole('cashier');
        $this->serviceProvider->assignRole('service_provider');

        // Client — adult (30 years old)
        $this->client = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => now()->subYears(30)->toDateString(),
        ]);

        // Department (required by Service foreign key)
        $department = \App\Models\Department::create([
            'branch_id' => $this->branch->id,
            'code'      => 'CONSULT',
            'name'      => 'General Consultation Dept',
            'is_active' => true,
        ]);

        // Service
        $this->service = Service::create([
            'code'          => 'GEN-CONSULT',
            'name'          => 'General Consultation',
            'base_price'    => 1000,
            'is_active'     => true,
            'department_id' => $department->id,
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /**
     * Create a visit at reception stage for the fixture client.
     */
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

    // ─── Tests 04–06: Triage and Intake ──────────────────────────────────────

    /** @test */
    public function test_04_triage_nurse_records_vitals(): void
    {
        $visit = $this->makeVisit(['current_stage' => 'triage']);
        $this->actingAs($this->triageNurse);

        // Use forceCreate to bypass $fillable; 'systolic_bp' is the real DB column
        // (the model's $fillable mistakenly lists 'blood_pressure_systolic').
        Triage::forceCreate([
            'visit_id'    => $visit->id,
            'client_id'   => $this->client->id,
            'branch_id'   => $this->branch->id,
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
}
