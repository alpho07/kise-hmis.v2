<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\InsuranceProvider;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

trait WorkflowFixture
{
    protected Branch            $branch;
    protected User              $receptionist;
    protected User              $triageNurse;
    protected User              $intakeOfficer;
    protected User              $billingOfficer;
    protected User              $cashier;
    protected User              $serviceProvider;
    protected Client            $client;
    protected Service           $service;
    protected InsuranceProvider $shaProvider;

    protected function seedWorkflowFixture(): void
    {
        foreach ([
            'receptionist', 'triage_nurse', 'intake_officer',
            'billing_officer', 'cashier', 'service_provider', 'admin', 'super_admin',
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

        $department = Department::create([
            'branch_id' => $this->branch->id,
            'code'      => 'CONSULT',
            'name'      => 'General Consultation',
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'code'          => 'GEN-CONSULT',
            'name'          => 'General Consultation',
            'base_price'    => 1000,
            'is_active'     => true,
            'department_id' => $department->id,
        ]);

        $this->shaProvider = InsuranceProvider::create([
            'code'                        => 'SHA',
            'name'                        => 'Social Health Authority',
            'type'                        => 'government_scheme',
            'is_active'                   => true,
            'default_coverage_percentage' => 80,
        ]);
    }

    /**
     * Create a visit at the given stage for the fixture client.
     * Authenticates as receptionist to satisfy BelongsToBranch scope.
     */
    protected function makeVisitAt(string $stage, array $overrides = []): Visit
    {
        $this->actingAs($this->receptionist);

        return Visit::create(array_merge([
            'branch_id'     => $this->branch->id,
            'client_id'     => $this->client->id,
            'visit_type'    => 'walk_in',
            'visit_date'    => now()->toDateString(),
            'current_stage' => $stage,
            'check_in_time' => now(),
        ], $overrides));
    }

    /**
     * Create a QueueEntry for a visit.
     * Required by ServiceQueueResource which queries QueueEntry, not Visit.
     */
    protected function makeQueueEntry(Visit $visit, array $overrides = []): QueueEntry
    {
        static $queueSeq = 1;

        return QueueEntry::create(array_merge([
            'branch_id'    => $this->branch->id,
            'visit_id'     => $visit->id,
            'client_id'    => $visit->client_id,
            'service_id'   => $this->service->id,
            'queue_number' => $queueSeq++,
            'status'       => 'waiting',
        ], $overrides));
    }

    /**
     * Create a user with a known plaintext password — for Dusk login form only.
     * Livewire tests use actingAs() and do not need this.
     */
    protected function makeUserWithPassword(string $role, string $password = 'password'): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create([
            'branch_id'  => $this->branch->id,
            'is_active'  => true,
            'password'   => Hash::make($password),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
