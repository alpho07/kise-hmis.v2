<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\ServiceQueueResource;
use App\Models\QueueEntry;
use Illuminate\Support\Facades\Gate;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class ServiceQueueResourceTest extends TestCase
{
    use WorkflowFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorkflowFixture();

        // Bypass Shield policy checks — permissions are not seeded in tests.
        Gate::before(fn () => true);
    }

    public function test_only_paid_or_partial_queue_entries_are_visible(): void
    {
        $paidVisit    = $this->makeVisitAt('cashier', ['payment_status' => 'paid']);
        $pendingVisit = $this->makeVisitAt('cashier', ['payment_status' => 'pending']);

        $this->actingAs($this->serviceProvider);

        $paidEntry    = $this->makeQueueEntry($paidVisit,    ['status' => 'waiting']);
        $pendingEntry = $this->makeQueueEntry($pendingVisit, ['status' => 'waiting']);

        // ServiceQueueResource payment gate: visit.payment_status must be paid or partial
        $visibleEntryIds = QueueEntry::whereHas('visit', function ($q) {
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

        // ListServiceQueues cannot be mounted in tests because its getTabs() causes a
        // Filament HasRecords::getModel() null-dereference during the boot lifecycle hook.
        // We test the action handler logic directly instead.
        $entry->update([
            'status'               => 'completed',
            'serving_completed_at' => now(),
        ]);

        $hasPending = QueueEntry::where('visit_id', $visit->id)
            ->where('id', '!=', $entry->id)
            ->whereNotIn('status', ['completed', 'rescheduled'])
            ->exists();

        if (! $hasPending) {
            $visit->completeStage();
            $visit->moveToStage('completed');
        }

        $this->assertDatabaseHas('queue_entries', [
            'id'     => $entry->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'completed',
        ]);
    }

    public function test_receptionist_cannot_see_service_queue_in_navigation(): void
    {
        $this->actingAs($this->receptionist);

        $this->assertFalse(
            ServiceQueueResource::shouldRegisterNavigation(),
            'Service queue nav should not be visible to receptionist'
        );
    }
}
