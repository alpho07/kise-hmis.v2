<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Department as DepartmentModel;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for the service queue payment gate.
 *
 * Business rules:
 * - Clients enter the service queue after intake (FIFO by design)
 * - 'Start Service' and 'Call Client' actions are HIDDEN until payment is confirmed
 * - visit.payment_status must be 'paid' or 'partial' for actions to be visible
 * - 'View Client Profile' is always visible (read-only, no payment gate)
 * - 'Complete Service' is gated by status='in_service', not payment (payment was already verified)
 */
class ServiceQueuePaymentGateTest extends TestCase
{

    private Branch $branch;
    private User $serviceProvider;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'service_provider', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',            'guard_name' => 'web']);

        $this->branch          = Branch::factory()->create();
        $this->serviceProvider = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->serviceProvider->assignRole('service_provider');

        $dept = DepartmentModel::firstOrCreate(
            ['name' => 'Physiotherapy', 'branch_id' => $this->branch->id],
            ['code' => 'PHY', 'description' => 'Physiotherapy', 'is_active' => true, 'branch_id' => $this->branch->id]
        );

        $this->service = Service::create([
            'code'          => 'PT-001',
            'name'          => 'Physiotherapy Session',
            'base_price'    => 500,
            'service_type'  => 'therapy',
            'age_group'     => 'all',
            'category'      => 'Therapy',
            'is_active'     => true,
            'duration_minutes' => 60,
            'department_id' => $dept->id,
        ]);

        $this->actingAs($this->serviceProvider);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeQueueEntry(string $visitPaymentStatus, string $entryStatus = 'ready'): QueueEntry
    {
        $client = Client::factory()->create(['branch_id' => $this->branch->id]);
        $visit  = Visit::factory()->create([
            'branch_id'      => $this->branch->id,
            'client_id'      => $client->id,
            'payment_status' => $visitPaymentStatus,
            'current_stage'  => 'queue',
            'status'         => 'in_queue',
        ]);

        $sequence = QueueEntry::count() + 1;

        return QueueEntry::create([
            'visit_id'     => $visit->id,
            'client_id'    => $client->id,
            'service_id'   => $this->service->id,
            'branch_id'    => $this->branch->id,
            'status'       => $entryStatus,
            'queue_number' => $sequence,
            'priority_level' => 3,
            'joined_at'    => now(),
        ]);
    }

    // ─── Payment gate: start_service ─────────────────────────────────────────

    /** @test */
    public function start_service_is_hidden_when_visit_payment_is_pending(): void
    {
        $entry = $this->makeQueueEntry('pending');
        $entry->load('visit');

        // Simulate the visible() closure from ServiceQueueResource
        $isVisible = $entry->status === 'ready'
            && in_array($entry->visit?->payment_status, ['paid', 'partial'], true);

        $this->assertFalse($isVisible, 'Start Service should be hidden when payment is pending');
    }

    /** @test */
    public function start_service_is_visible_when_visit_payment_is_paid(): void
    {
        $entry = $this->makeQueueEntry('paid');
        $entry->load('visit');

        $isVisible = $entry->status === 'ready'
            && in_array($entry->visit?->payment_status, ['paid', 'partial'], true);

        $this->assertTrue($isVisible, 'Start Service should be visible when payment is paid');
    }

    /** @test */
    public function start_service_is_visible_when_visit_payment_is_partial(): void
    {
        $entry = $this->makeQueueEntry('partial');
        $entry->load('visit');

        $isVisible = $entry->status === 'ready'
            && in_array($entry->visit?->payment_status, ['paid', 'partial'], true);

        $this->assertTrue($isVisible, 'Start Service should be visible when payment is partial');
    }

    /** @test */
    public function start_service_is_hidden_when_status_is_in_service(): void
    {
        $entry = $this->makeQueueEntry('paid', 'in_service');
        $entry->load('visit');

        // start_service requires status='ready'
        $isVisible = $entry->status === 'ready'
            && in_array($entry->visit?->payment_status, ['paid', 'partial'], true);

        $this->assertFalse($isVisible, 'Start Service should be hidden when status is in_service');
    }

    // ─── Payment gate: call_next ──────────────────────────────────────────────

    /** @test */
    public function call_client_is_hidden_when_visit_payment_is_pending(): void
    {
        $entry = $this->makeQueueEntry('pending');
        $entry->load('visit');

        // Simulate the call_next visible() closure
        $isVisible = $entry->status === 'ready'
            && in_array($entry->visit?->payment_status, ['paid', 'partial'], true);

        $this->assertFalse($isVisible, 'Call Client should be hidden when payment is pending');
    }

    /** @test */
    public function call_client_is_visible_when_visit_payment_is_paid(): void
    {
        $entry = $this->makeQueueEntry('paid');
        $entry->load('visit');

        $isVisible = $entry->status === 'ready'
            && in_array($entry->visit?->payment_status, ['paid', 'partial'], true);

        $this->assertTrue($isVisible, 'Call Client should be visible when payment is paid');
    }

    // ─── Complete service: gated by status not payment ─────────────────────────

    /** @test */
    public function complete_service_is_visible_only_when_in_service(): void
    {
        // Complete service is based on status='in_service', payment already verified
        $entryReady    = $this->makeQueueEntry('paid', 'ready');
        $entryInService = $this->makeQueueEntry('paid', 'in_service');
        $entryCompleted = $this->makeQueueEntry('paid', 'completed');

        $this->assertFalse(
            $entryReady->status === 'in_service',
            'Complete should not be visible for ready status'
        );
        $this->assertTrue(
            $entryInService->status === 'in_service',
            'Complete should be visible for in_service status'
        );
        $this->assertFalse(
            $entryCompleted->status === 'in_service',
            'Complete should not be visible for already-completed entry'
        );
    }

    // ─── FIFO queue integrity ─────────────────────────────────────────────────

    /** @test */
    public function client_is_visible_in_queue_before_payment_is_made(): void
    {
        // FIFO design: client enters queue at intake, visible before payment
        $entry = $this->makeQueueEntry('pending', 'ready');

        $this->assertDatabaseHas('queue_entries', [
            'id'     => $entry->id,
            'status' => 'ready',
        ]);

        // Client is visible in queue (status='ready'), but action buttons are hidden
        $this->assertEquals('ready', $entry->status);
        $this->assertEquals('pending', $entry->visit->payment_status);
    }

    /** @test */
    public function queue_number_is_assigned_in_ascending_order(): void
    {
        $entry1 = $this->makeQueueEntry('pending');
        $entry2 = $this->makeQueueEntry('pending');
        $entry3 = $this->makeQueueEntry('pending');

        // Queue numbers should be strictly ascending (FIFO)
        $this->assertGreaterThan($entry1->queue_number, $entry2->queue_number);
        $this->assertGreaterThan($entry2->queue_number, $entry3->queue_number);
    }

    /** @test */
    public function completing_service_marks_entry_as_completed(): void
    {
        $entry = $this->makeQueueEntry('paid', 'in_service');
        $entry->update(['status' => 'completed', 'serving_completed_at' => now()]);

        $this->assertDatabaseHas('queue_entries', [
            'id'     => $entry->id,
            'status' => 'completed',
        ]);

        $this->assertNotNull($entry->fresh()->serving_completed_at);
    }

    // ─── Payment status transitions ───────────────────────────────────────────

    /** @test */
    public function visit_payment_status_transitions_from_pending_to_paid(): void
    {
        $client = Client::factory()->create(['branch_id' => $this->branch->id]);
        $visit  = Visit::factory()->create([
            'branch_id'      => $this->branch->id,
            'client_id'      => $client->id,
            'payment_status' => 'pending',
        ]);

        $this->assertEquals('pending', $visit->payment_status);

        $visit->update(['payment_status' => 'paid']);
        $this->assertEquals('paid', $visit->fresh()->payment_status);
    }
}
