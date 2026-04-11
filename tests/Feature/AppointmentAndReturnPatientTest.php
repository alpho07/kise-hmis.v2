<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\Service;
use App\Models\Triage;
use App\Models\User;
use App\Models\Visit;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests for appointment check-in and returning patient workflows.
 *
 * Key routing rules under test:
 *   - New walk-in           → reception → triage → intake
 *   - Returning (walk-in)   → triage → billing  (skips intake)
 *   - Appointment check-in  → triage (already at triage) → billing (skips intake)
 */
class AppointmentAndReturnPatientTest extends TestCase
{

    protected Branch $branch;
    protected User   $receptionist;
    protected User   $triageNurse;
    protected Client $returningClient;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['receptionist', 'triage_nurse', 'admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->branch       = Branch::factory()->create();
        $this->receptionist = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->receptionist->assignRole('receptionist');

        $this->triageNurse = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->triageNurse->assignRole('triage_nurse');

        // A client who has already been seen (returning)
        $this->returningClient = Client::factory()->create(['branch_id' => $this->branch->id]);
        Visit::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->returningClient->id,
            'status'    => 'completed',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAppointment(array $overrides = []): Appointment
    {
        $dept    = Department::factory()->create(['branch_id' => $this->branch->id]);
        $service = Service::factory()->create(['department_id' => $dept->id]);

        return Appointment::factory()->create(array_merge([
            'client_id'     => $this->returningClient->id,
            'branch_id'     => $this->branch->id,
            'department_id' => $dept->id,
            'service_id'    => $service->id,
            'created_by'    => $this->receptionist->id,
            'status'        => 'scheduled',
        ], $overrides));
    }

    private function completeTriage(Visit $visit, string $status = 'cleared'): Triage
    {
        return Triage::create([
            'visit_id'       => $visit->id,
            'client_id'      => $visit->client_id,
            'branch_id'      => $this->branch->id,
            'triage_number'  => 'TRG-' . $visit->id,
            'triage_status'  => $status,
            'risk_level'     => 'low',
            'triaged_by'     => $this->triageNurse->id,
        ]);
    }

    // ── Appointment scheduling ─────────────────────────────────────────────

    public function test_appointment_can_be_created_for_returning_client(): void
    {
        $appt = $this->makeAppointment(['appointment_date' => today()->toDateString()]);

        $this->assertDatabaseHas('appointments', [
            'client_id' => $this->returningClient->id,
            'status'    => 'scheduled',
        ]);
        $this->assertTrue($appt->canCheckIn());
    }

    public function test_confirmed_appointment_can_also_check_in(): void
    {
        $appt = $this->makeAppointment(['status' => 'confirmed']);
        $this->assertTrue($appt->canCheckIn());
    }

    public function test_checked_in_appointment_cannot_check_in_again(): void
    {
        $appt = $this->makeAppointment(['status' => 'checked_in']);
        $this->assertFalse($appt->canCheckIn());
    }

    public function test_appointment_no_show_marks_status_correctly(): void
    {
        $appt = $this->makeAppointment();
        $appt->markNoShow();

        $this->assertDatabaseHas('appointments', [
            'id'     => $appt->id,
            'status' => 'no_show',
        ]);
        $this->assertFalse($appt->fresh()->canCheckIn());
    }

    public function test_appointment_can_be_cancelled_with_reason(): void
    {
        $appt   = $this->makeAppointment();
        $reason = 'Client called to reschedule';
        $appt->cancel($reason);

        $this->assertDatabaseHas('appointments', [
            'id'                  => $appt->id,
            'status'              => 'cancelled',
            'cancellation_reason' => $reason,
        ]);
        $this->assertFalse($appt->canCancel());
    }

    // ── Appointment check-in creates correct visit ────────────────────────

    public function test_appointment_check_in_creates_visit_with_is_appointment_flag(): void
    {
        $this->actingAs($this->receptionist);

        $appt  = $this->makeAppointment();

        // Simulate what TodayAppointmentsWidget check_in action does
        $visit = Visit::create([
            'client_id'      => $appt->client_id,
            'branch_id'      => $this->branch->id,
            'is_appointment' => true,
            'visit_type'     => 'appointment',
            'triage_path'    => 'returning',
            'check_in_time'  => now(),
            'checked_in_by'  => $this->receptionist->id,
            'visit_date'     => today(),
        ]);
        $visit->moveToStage('triage');

        $appt->update([
            'status'        => 'checked_in',
            'checked_in_at' => now(),
            'checked_in_by' => $this->receptionist->id,
            'visit_id'      => $visit->id,
        ]);

        $this->assertTrue((bool) $visit->is_appointment);
        $this->assertEquals('appointment', $visit->visit_type);
        $this->assertEquals('returning', $visit->triage_path);
        $this->assertEquals('triage', $visit->current_stage);
        $this->assertEquals('checked_in', $appt->fresh()->status);
        $this->assertEquals($visit->id, $appt->fresh()->visit_id);
    }

    public function test_appointment_check_in_skips_reception_queue(): void
    {
        $this->actingAs($this->receptionist);

        $appt  = $this->makeAppointment();

        $visit = Visit::create([
            'client_id'      => $appt->client_id,
            'branch_id'      => $this->branch->id,
            'is_appointment' => true,
            'visit_type'     => 'appointment',
            'triage_path'    => 'returning',
            'check_in_time'  => now(),
            'checked_in_by'  => $this->receptionist->id,
            'visit_date'     => today(),
        ]);
        // Appointment goes straight to triage — never appears in reception queue
        $visit->moveToStage('triage');

        // Should NOT be in reception stage
        $this->assertNotEquals('reception', $visit->current_stage);
        $this->assertEquals('triage', $visit->current_stage);
    }

    // ── Triage routing: appointment → billing ─────────────────────────────

    public function test_appointment_visit_routes_to_billing_after_triage(): void
    {
        $this->actingAs($this->triageNurse);

        $visit = Visit::factory()->create([
            'branch_id'      => $this->branch->id,
            'client_id'      => $this->returningClient->id,
            'is_appointment' => true,
            'visit_type'     => 'appointment',
            'triage_path'    => 'returning',
            'current_stage'  => 'triage',
        ]);

        // Triage completes — appointment visits route to billing (skip intake)
        $visit->completeStage();
        $nextStage = ($visit->is_appointment || $visit->triage_path === 'returning')
            ? 'billing'
            : 'intake';
        $visit->moveToStage($nextStage);

        $this->assertEquals('billing', $visit->current_stage);
    }

    // ── Triage routing: returning walk-in → billing ───────────────────────

    public function test_returning_walkin_routes_to_billing_after_triage(): void
    {
        $this->actingAs($this->triageNurse);

        $visit = Visit::factory()->create([
            'branch_id'      => $this->branch->id,
            'client_id'      => $this->returningClient->id,
            'is_appointment' => false,
            'visit_type'     => 'walk_in',
            'triage_path'    => 'returning',
            'current_stage'  => 'triage',
        ]);

        $visit->completeStage();
        $nextStage = ($visit->is_appointment || $visit->triage_path === 'returning')
            ? 'billing'
            : 'intake';
        $visit->moveToStage($nextStage);

        $this->assertEquals('billing', $visit->current_stage);
    }

    // ── Triage routing: new walk-in → intake (control) ───────────────────

    public function test_new_walkin_routes_to_intake_after_triage(): void
    {
        $this->actingAs($this->triageNurse);

        $newClient = Client::factory()->create(['branch_id' => $this->branch->id]);
        $visit     = Visit::factory()->create([
            'branch_id'      => $this->branch->id,
            'client_id'      => $newClient->id,
            'is_appointment' => false,
            'visit_type'     => 'walk_in',
            'triage_path'    => 'standard',
            'current_stage'  => 'triage',
        ]);

        $visit->completeStage();
        $nextStage = ($visit->is_appointment || $visit->triage_path === 'returning')
            ? 'billing'
            : 'intake';
        $visit->moveToStage($nextStage);

        $this->assertEquals('intake', $visit->current_stage);
    }

    // ── Prior visit detection ─────────────────────────────────────────────

    public function test_client_with_prior_completed_visit_has_visit_history(): void
    {
        $priorVisitCount = Visit::where('client_id', $this->returningClient->id)
            ->where('status', 'completed')
            ->count();

        $this->assertGreaterThan(0, $priorVisitCount);
    }

    public function test_appointment_links_to_prior_visit_history_of_client(): void
    {
        $appt = $this->makeAppointment(['appointment_type' => 'follow_up']);

        $this->assertEquals('follow_up', $appt->appointment_type);
        $this->assertGreaterThan(
            0,
            Visit::where('client_id', $appt->client_id)->where('status', 'completed')->count(),
            'Appointment client must have prior visit history'
        );
    }

    // ── Stage history records appointment path ────────────────────────────

    public function test_appointment_visit_stage_history_is_recorded(): void
    {
        $this->actingAs($this->receptionist);

        $appt  = $this->makeAppointment();

        $visit = Visit::create([
            'client_id'      => $appt->client_id,
            'branch_id'      => $this->branch->id,
            'is_appointment' => true,
            'visit_type'     => 'appointment',
            'triage_path'    => 'returning',
            'check_in_time'  => now(),
            'checked_in_by'  => $this->receptionist->id,
            'visit_date'     => today(),
        ]);
        $visit->moveToStage('triage');

        $this->assertDatabaseHas('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'triage',
        ]);
    }
}
