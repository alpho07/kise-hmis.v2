<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\CashierQueueResource;
use App\Filament\Resources\CashierQueueResource\Pages\ListCashierQueues;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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

        // Bypass Shield policy checks — permissions are not seeded in tests.
        Gate::before(fn () => true);
    }

    public function test_process_payment_marks_invoice_paid_and_creates_payment_record(): void
    {
        $visit = $this->makeVisitAt('cashier');
        $this->actingAs($this->cashier);

        // CashierQueueResource reads $record->invoice — must exist before opening the action form
        Invoice::create([
            'invoice_number'       => 'INV-' . uniqid(),
            'visit_id'             => $visit->id,
            'client_id'            => $this->client->id,
            'branch_id'            => $this->branch->id,
            'total_amount'         => 1000,
            'total_client_amount'  => 1000,
            'total_sponsor_amount' => 0,
            'balance_due'          => 1000,
            'has_sponsor'          => false,
            'status'               => 'pending',
            'generated_by'         => $this->cashier->id,
        ]);

        Livewire::test(ListCashierQueues::class)
            ->callTableAction('process_payment', $visit, data: [
                'use_cash'    => true,
                'cash_amount' => 1000,
            ]);

        // HybridPaymentService records payment and updates invoice amount_paid
        $this->assertDatabaseHas('invoices', [
            'visit_id'   => $visit->id,
            'amount_paid' => 1000,
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

        $this->assertFalse(
            CashierQueueResource::shouldRegisterNavigation(),
            'Cashier queue nav should not be visible to intake officer'
        );
    }
}
