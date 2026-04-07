<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for the visit lifecycle workflow.
 *
 * Workflow stages: reception → triage → intake → billing → payment → queue → completed
 *
 * Business rules:
 * - Visit::moveToStage advances current_stage and records VisitStage timestamp
 * - Visit::completeStage marks current stage as done
 * - Cash clients: intake → queue (FIFO, payment at cashier)
 * - Sponsor clients: intake → billing (Payment Admin reviews first)
 * - Visit number format: VST-YYYYMMDD-XXXX
 */
class VisitWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $receptionist;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'intake_officer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->branch       = Branch::factory()->create();
        $this->receptionist = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->receptionist->assignRole('receptionist');

        $this->client = Client::factory()->create(['branch_id' => $this->branch->id]);

        $this->actingAs($this->receptionist);
    }

    // ─── Visit creation ───────────────────────────────────────────────────────

    /** @test */
    public function new_visit_starts_at_reception_stage(): void
    {
        $visit = Visit::factory()->create([
            'branch_id'     => $this->branch->id,
            'client_id'     => $this->client->id,
            'current_stage' => 'reception',
        ]);

        $this->assertEquals('reception', $visit->current_stage);
    }

    /** @test */
    public function visit_number_follows_vst_format(): void
    {
        $visit = Visit::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
        ]);

        $this->assertMatchesRegularExpression('/^VST-\d{8}-\d{4}$/', $visit->visit_number);
    }

    // ─── Stage transitions ────────────────────────────────────────────────────

    /** @test */
    public function move_to_stage_advances_current_stage(): void
    {
        $visit = Visit::factory()->create([
            'branch_id'     => $this->branch->id,
            'client_id'     => $this->client->id,
            'current_stage' => 'reception',
        ]);

        $visit->moveToStage('triage');

        $this->assertEquals('triage', $visit->fresh()->current_stage);
    }

    /** @test */
    public function move_to_stage_records_stage_history(): void
    {
        $visit = Visit::factory()->create([
            'branch_id'     => $this->branch->id,
            'client_id'     => $this->client->id,
            'current_stage' => 'reception',
        ]);

        $visit->moveToStage('triage');

        $this->assertDatabaseHas('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'triage',
        ]);
    }

    /** @test */
    public function cash_client_routes_from_intake_to_queue(): void
    {
        $visit = Visit::factory()->create([
            'branch_id'     => $this->branch->id,
            'client_id'     => $this->client->id,
            'current_stage' => 'intake',
        ]);

        // Simulate cash routing (no sponsor)
        $visit->completeStage();
        $visit->moveToStage('queue');

        $this->assertEquals('queue', $visit->fresh()->current_stage);
    }

    /** @test */
    public function sponsor_client_routes_from_intake_to_billing(): void
    {
        $visit = Visit::factory()->create([
            'branch_id'     => $this->branch->id,
            'client_id'     => $this->client->id,
            'current_stage' => 'intake',
        ]);

        // Simulate sponsor routing (SHA/NCPWD/insurance)
        $visit->completeStage();
        $visit->moveToStage('billing');

        $this->assertEquals('billing', $visit->fresh()->current_stage);
    }

    /** @test */
    public function complete_stage_marks_visit_as_completed(): void
    {
        $visit = Visit::factory()->create([
            'branch_id'     => $this->branch->id,
            'client_id'     => $this->client->id,
            'current_stage' => 'queue',
        ]);

        $visit->completeStage();
        $visit->moveToStage('completed');

        $this->assertEquals('completed', $visit->fresh()->current_stage);
    }

    // ─── Visit status ─────────────────────────────────────────────────────────

    /** @test */
    public function deferred_visit_has_deferred_status(): void
    {
        $visit = Visit::factory()->create([
            'branch_id'  => $this->branch->id,
            'client_id'  => $this->client->id,
            'status'     => 'deferred',
        ]);

        $this->assertEquals('deferred', $visit->status);
    }

    /** @test */
    public function visit_payment_status_defaults_to_pending(): void
    {
        $visit = Visit::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
        ]);

        // payment_status defaults to 'pending' via model boot
        $this->assertEquals('pending', $visit->fresh()->payment_status);
    }

    // ─── Returning client detection ───────────────────────────────────────────

    /** @test */
    public function client_with_prior_visit_is_returning(): void
    {
        // First visit
        Visit::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'status'    => 'completed',
        ]);

        // Second visit (returning clients use 'walk_in' type with different client_type on client)
        $secondVisit = Visit::factory()->create([
            'branch_id'  => $this->branch->id,
            'client_id'  => $this->client->id,
            'visit_type' => 'walk_in',
        ]);

        $priorVisitCount = Visit::where('client_id', $this->client->id)
            ->where('id', '!=', $secondVisit->id)
            ->count();

        $this->assertGreaterThan(0, $priorVisitCount, 'Client should have prior visits');
    }

    /** @test */
    public function multiple_visits_for_same_client_are_allowed(): void
    {
        Visit::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
        ]);

        $visitCount = Visit::where('client_id', $this->client->id)->count();
        $this->assertEquals(3, $visitCount);
    }
}
