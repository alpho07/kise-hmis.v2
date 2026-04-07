<?php

namespace Tests\Browser;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

/**
 * Real-browser smoke tests for the cash-patient workflow.
 *
 * Prerequisites: app server running on http://127.0.0.1:8000
 *   php artisan serve --port=8000 --env=dusk
 *
 * Run:
 *   php artisan dusk tests/Browser/CashPatientWorkflowTest.php
 *
 * Each test is fully independent — seeds its own DB state.
 * DatabaseMigrations re-runs all migrations before each test.
 */
class CashPatientWorkflowTest extends DuskTestCase
{
    use DatabaseMigrations;

    // ─── Shared seed helpers ──────────────────────────────────────────────────

    private function seedBase(): array
    {
        foreach ([
            'receptionist', 'triage_nurse', 'intake_officer',
            'cashier', 'service_provider', 'admin',
        ] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $branch = Branch::factory()->create();
        $dept   = Department::create([
            'branch_id' => $branch->id,
            'code'      => 'CONSULT',
            'name'      => 'General Consultation',
            'is_active' => true,
        ]);
        $service = Service::create([
            'code'          => 'GEN-CONSULT',
            'name'          => 'General Consultation',
            'base_price'    => 1000,
            'is_active'     => true,
            'department_id' => $dept->id,
        ]);
        $client = Client::factory()->create([
            'branch_id'     => $branch->id,
            'date_of_birth' => now()->subYears(30)->toDateString(),
        ]);

        return compact('branch', 'service', 'client');
    }

    private function makeUser(string $role, Branch $branch, string $password = 'password'): User
    {
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
            'password'  => Hash::make($password),
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function makeVisit(Branch $branch, Client $client, string $stage, array $extra = []): Visit
    {
        return Visit::create(array_merge([
            'branch_id'     => $branch->id,
            'client_id'     => $client->id,
            'visit_type'    => 'walk_in',
            'visit_date'    => now()->toDateString(),
            'current_stage' => $stage,
            'check_in_time' => now(),
        ], $extra));
    }

    // ─── Test methods ─────────────────────────────────────────────────────────

    /** Smoke: receptionist can log in and see the Reception nav item. */
    public function test_01_receptionist_logs_in_and_sees_reception_nav(): void
    {
        ['branch' => $branch] = $this->seedBase();
        $user = $this->makeUser('receptionist', $branch);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/admin/login')
                    ->type('email', $user->email)
                    ->type('password', 'password')
                    ->press('Sign in')
                    ->assertPathIs('/admin')
                    ->assertSee('Reception');
        });
    }

    /** Smoke: receptionist can open the create-reception form. */
    public function test_02_receptionist_registers_client_and_checks_in(): void
    {
        ['branch' => $branch, 'client' => $client] = $this->seedBase();
        $user = $this->makeUser('receptionist', $branch);

        $this->browse(function (Browser $browser) use ($user, $client) {
            $browser->loginAs($user)
                    ->visit('/admin/receptions/create')
                    ->pause(1000)
                    ->assertSee('Client')
                    // Filament Select: type to search then pick first option
                    ->click('[wire\\:key*="client_id"] input')
                    ->waitFor('[wire\\:key*="client_id"] [role="option"]', 5)
                    ->click('[wire\\:key*="client_id"] [role="option"]:first-child')
                    ->select('[wire\\:key*="visit_type"] select', 'walk_in')
                    ->press('Create')
                    ->waitForText('Check-In Successful', 10)
                    ->assertSee('Check-In Successful');
        });
    }

    /** Smoke: triage nurse can see triage queue and open triage form. */
    public function test_03_triage_nurse_records_vitals(): void
    {
        ['branch' => $branch, 'client' => $client] = $this->seedBase();
        $nurse = $this->makeUser('triage_nurse', $branch);
        $visit = $this->makeVisit($branch, $client, 'triage');

        $this->browse(function (Browser $browser) use ($nurse, $visit) {
            $browser->loginAs($nurse)
                    ->visit('/admin/triage-queues')
                    ->waitForText('Start Triage', 10)
                    ->clickLink('Start Triage')
                    ->waitForLocation('/admin/triages/create', 10)
                    ->waitFor('input[wire\\:model*="systolic_bp"]', 5)
                    ->type('input[wire\\:model*="systolic_bp"]', '120')
                    ->type('input[wire\\:model*="heart_rate"]', '72')
                    ->type('input[wire\\:model*="temperature"]', '36.6')
                    ->press('Create')
                    ->waitForText('created', 10);
        });

        $this->assertDatabaseHas('triages', ['visit_id' => $visit->id]);
    }

    /** Smoke: intake officer can trigger start-intake action which creates assessment. */
    public function test_04_intake_officer_starts_intake(): void
    {
        ['branch' => $branch, 'client' => $client] = $this->seedBase();
        $officer = $this->makeUser('intake_officer', $branch);
        $visit   = $this->makeVisit($branch, $client, 'intake');

        $this->browse(function (Browser $browser) use ($officer, $visit) {
            $browser->loginAs($officer)
                    ->visit('/admin/intake-queues')
                    ->waitForText('Start Intake', 10)
                    ->clickLink('Start Intake')
                    ->pause(2000); // allow redirect and DB write to complete
        });

        $this->assertDatabaseHas('intake_assessments', ['visit_id' => $visit->id]);
    }

    /** Smoke: cashier can process a cash payment and invoice amount_paid is updated. */
    public function test_05_cashier_processes_payment(): void
    {
        ['branch' => $branch, 'client' => $client] = $this->seedBase();
        $cashier = $this->makeUser('cashier', $branch);
        $visit   = $this->makeVisit($branch, $client, 'cashier');

        Invoice::create([
            'invoice_number'       => 'INV-DUSK-001',
            'visit_id'             => $visit->id,
            'client_id'            => $client->id,
            'branch_id'            => $branch->id,
            'total_amount'         => 1000,
            'total_client_amount'  => 1000,
            'total_sponsor_amount' => 0,
            'balance_due'          => 1000,
            'has_sponsor'          => false,
            'status'               => 'pending',
            'generated_by'         => $cashier->id,
        ]);

        $this->browse(function (Browser $browser) use ($cashier) {
            $browser->loginAs($cashier)
                    ->visit('/admin/cashier-queues')
                    ->waitForText('Process Payment', 10)
                    ->clickLink('Process Payment')
                    ->waitFor('[role="dialog"]', 5)
                    ->check('[wire\\:model*="use_cash"]')
                    ->waitFor('input[wire\\:model*="cash_amount"]', 3)
                    ->type('input[wire\\:model*="cash_amount"]', '1000')
                    ->press('Confirm')
                    ->waitForText('Payment', 10);
        });

        // HybridPaymentService updates amount_paid on the invoice
        $this->assertDatabaseHas('invoices', [
            'visit_id'   => $visit->id,
            'amount_paid' => 1000,
        ]);
        $this->assertDatabaseHas('payments', ['visit_id' => $visit->id]);
    }

    /** Smoke: service provider can complete a service and visit advances to completed. */
    public function test_06_service_provider_completes_service(): void
    {
        ['branch' => $branch, 'client' => $client, 'service' => $service] = $this->seedBase();
        $provider = $this->makeUser('service_provider', $branch);
        $visit    = $this->makeVisit($branch, $client, 'cashier', ['payment_status' => 'paid']);

        $entry = QueueEntry::create([
            'branch_id'    => $branch->id,
            'visit_id'     => $visit->id,
            'client_id'    => $client->id,
            'service_id'   => $service->id,
            'queue_number' => 1,
            'status'       => 'in_service',
        ]);

        $this->browse(function (Browser $browser) use ($provider) {
            $browser->loginAs($provider)
                    ->visit('/admin/service-queues')
                    ->waitForText('Complete', 10)
                    // Action label in resource is 'Complete'
                    ->clickLink('Complete')
                    ->waitFor('[role="dialog"]', 5)
                    ->press('Confirm')
                    ->waitForText('Service Completed', 10);
        });

        $this->assertDatabaseHas('queue_entries', [
            'id'     => $entry->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'completed',
        ]);
    }
}
