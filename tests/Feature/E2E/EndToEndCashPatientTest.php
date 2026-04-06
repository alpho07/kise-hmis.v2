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
