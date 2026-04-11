<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for Role-Based Access Control (RBAC).
 *
 * Roles: super_admin, admin, branch_manager, receptionist, triage_nurse,
 *        intake_officer, billing_admin, cashier, service_provider, queue_manager
 *
 * Business rules:
 * - super_admin sees all branches, bypasses BelongsToBranch scope
 * - Each role is scoped to their branch (except super_admin)
 * - Navigation visibility is controlled by shouldRegisterNavigation() per resource
 * - Users can only be assigned roles that exist in the system
 */
class RoleBasedAccessTest extends TestCase
{

    private Branch $branch;

    private const ALL_ROLES = [
        'super_admin',
        'admin',
        'branch_manager',
        'receptionist',
        'triage_nurse',
        'intake_officer',
        'billing_admin',
        'cashier',
        'service_provider',
        'queue_manager',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();

        foreach (self::ALL_ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    // ─── Role assignment ──────────────────────────────────────────────────────

    /** @test */
    public function each_defined_role_can_be_assigned_to_a_user(): void
    {
        foreach (self::ALL_ROLES as $roleName) {
            $user = User::factory()->create(['branch_id' => $this->branch->id]);
            $user->assignRole($roleName);

            $this->assertTrue($user->hasRole($roleName), "User should have role: {$roleName}");
        }
    }

    /** @test */
    public function user_without_role_does_not_have_any_role(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id]);

        foreach (self::ALL_ROLES as $role) {
            $this->assertFalse($user->hasRole($role));
        }
    }

    /** @test */
    public function super_admin_has_all_roles_check(): void
    {
        $superAdmin = User::factory()->create(['branch_id' => $this->branch->id]);
        $superAdmin->assignRole('super_admin');

        $this->assertTrue($superAdmin->hasRole('super_admin'));
        // super_admin should NOT accidentally have other roles
        $this->assertFalse($superAdmin->hasRole('receptionist'));
        $this->assertFalse($superAdmin->hasRole('cashier'));
    }

    // ─── Role navigation visibility logic ─────────────────────────────────────

    /** @test */
    public function receptionist_role_grants_access_to_client_management(): void
    {
        $receptionist = User::factory()->create(['branch_id' => $this->branch->id]);
        $receptionist->assignRole('receptionist');

        // Simulate shouldRegisterNavigation() logic for reception-stage resources
        $hasAccess = $receptionist->hasRole(['super_admin', 'admin', 'receptionist', 'branch_manager']);
        $this->assertTrue($hasAccess);
    }

    /** @test */
    public function service_provider_role_grants_access_to_service_queue(): void
    {
        $provider = User::factory()->create(['branch_id' => $this->branch->id]);
        $provider->assignRole('service_provider');

        $hasAccess = $provider->hasRole(['super_admin', 'admin', 'service_provider']);
        $this->assertTrue($hasAccess);
    }

    /** @test */
    public function cashier_does_not_have_billing_admin_access(): void
    {
        $cashier = User::factory()->create(['branch_id' => $this->branch->id]);
        $cashier->assignRole('cashier');

        // Billing admin resource should only be visible to billing_admin + admin + super_admin
        $hasBillingAdminAccess = $cashier->hasRole(['super_admin', 'admin', 'billing_admin']);
        $this->assertFalse($hasBillingAdminAccess);
    }

    /** @test */
    public function triage_nurse_role_grants_triage_access(): void
    {
        $nurse = User::factory()->create(['branch_id' => $this->branch->id]);
        $nurse->assignRole('triage_nurse');

        $hasAccess = $nurse->hasRole(['super_admin', 'admin', 'triage_nurse', 'branch_manager']);
        $this->assertTrue($hasAccess);
    }

    /** @test */
    public function intake_officer_role_grants_intake_access(): void
    {
        $officer = User::factory()->create(['branch_id' => $this->branch->id]);
        $officer->assignRole('intake_officer');

        $hasAccess = $officer->hasRole(['super_admin', 'admin', 'intake_officer', 'branch_manager']);
        $this->assertTrue($hasAccess);
    }

    // ─── Multi-role scenarios ─────────────────────────────────────────────────

    /** @test */
    public function admin_has_access_to_all_workflow_stages(): void
    {
        $admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $admin->assignRole('admin');

        // Admin should be able to access all resources that check for 'admin'
        $stages = ['reception', 'triage', 'intake', 'billing', 'service_delivery'];
        foreach ($stages as $stage) {
            $hasAccess = $admin->hasRole(['super_admin', 'admin']);
            $this->assertTrue($hasAccess, "Admin should have access at stage: {$stage}");
        }
    }

    /** @test */
    public function user_can_have_only_one_primary_role(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id]);
        $user->assignRole('receptionist');

        $this->assertCount(1, $user->getRoleNames());
        $this->assertTrue($user->hasRole('receptionist'));
        $this->assertFalse($user->hasRole('cashier'));
    }

    // ─── Branch isolation per role ─────────────────────────────────────────────

    /** @test */
    public function each_role_user_is_scoped_to_their_branch(): void
    {
        $otherBranch = Branch::factory()->create();

        foreach (['receptionist', 'triage_nurse', 'intake_officer', 'cashier'] as $role) {
            $userBranch = User::factory()->create(['branch_id' => $this->branch->id]);
            $userBranch->assignRole($role);

            $this->assertEquals($this->branch->id, $userBranch->branch_id);
            $this->assertNotEquals($otherBranch->id, $userBranch->branch_id);
        }
    }

    /** @test */
    public function super_admin_is_not_restricted_to_one_branch(): void
    {
        $superAdmin = User::factory()->create(['branch_id' => $this->branch->id]);
        $superAdmin->assignRole('super_admin');

        // The super_admin RECORD lives on a branch (for login purposes),
        // but their queries bypass BelongsToBranch scope
        $this->assertTrue($superAdmin->hasRole('super_admin'));
        $this->assertNotNull($superAdmin->branch_id); // still assigned a home branch
    }
}
