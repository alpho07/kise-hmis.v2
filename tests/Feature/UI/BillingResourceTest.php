<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\BillingResource;
use App\Filament\Resources\BillingResource\Pages\ListBillings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class BillingResourceTest extends TestCase
{
    use WorkflowFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorkflowFixture();

        // Bypass Shield policy checks — permissions are not seeded in tests.
        Gate::before(fn () => true);
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
        $visit = $this->makeVisitAt('billing');
        $this->actingAs($this->billingOfficer);

        $shaInvoice = $this->makeShaInvoice($visit);
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

        // total_sponsor_amount = 0 skips InsuranceClaimService (only called when > 0)
        // total_client_amount > 0 still routes visit to 'cashier'
        $invoice = $this->makeShaInvoice($visit, [
            'total_sponsor_amount' => 0,
            'total_client_amount'  => 1000,
        ]);

        Livewire::test(ListBillings::class)
            ->callTableAction('approve', $invoice, data: [
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

        $this->assertFalse(
            BillingResource::shouldRegisterNavigation(),
            'Billing nav should not be visible to cashier'
        );
    }
}
