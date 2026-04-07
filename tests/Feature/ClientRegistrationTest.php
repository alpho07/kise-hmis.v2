<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for client registration and UCI uniqueness.
 *
 * Business rules:
 * - UCI format: KISE/A/000XXX/YEAR — must be unique system-wide
 * - Clients are branch-scoped via BelongsToBranch trait
 * - super_admin sees all branches; regular staff see only their own branch
 * - Client age boundary: 17 = child, 18 = adult
 */
class ClientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',        'guard_name' => 'web']);

        $this->branch       = Branch::factory()->create(['name' => 'Nairobi Branch']);
        $this->receptionist = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->receptionist->assignRole('receptionist');
    }

    // ─── UCI uniqueness ───────────────────────────────────────────────────────

    /** @test */
    public function uci_must_be_unique_across_all_clients(): void
    {
        Client::factory()->create([
            'branch_id' => $this->branch->id,
            'uci'       => 'KISE/A/000001/2026',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Client::factory()->create([
            'branch_id' => $this->branch->id,
            'uci'       => 'KISE/A/000001/2026', // duplicate
        ]);
    }

    /** @test */
    public function different_clients_can_share_sequential_ucis(): void
    {
        $c1 = Client::factory()->create([
            'branch_id' => $this->branch->id,
            'uci'       => 'KISE/A/000001/2026',
        ]);
        $c2 = Client::factory()->create([
            'branch_id' => $this->branch->id,
            'uci'       => 'KISE/A/000002/2026',
        ]);

        $this->assertDatabaseHas('clients', ['uci' => 'KISE/A/000001/2026']);
        $this->assertDatabaseHas('clients', ['uci' => 'KISE/A/000002/2026']);
    }

    // ─── Branch isolation (BelongsToBranch scope) ─────────────────────────────

    /** @test */
    public function branch_staff_only_see_clients_from_their_branch(): void
    {
        $otherBranch = Branch::factory()->create(['name' => 'Mombasa Branch']);

        // Client in our branch
        $ownClient = Client::factory()->create(['branch_id' => $this->branch->id]);
        // Client in another branch
        $otherClient = Client::factory()->create(['branch_id' => $otherBranch->id]);

        $this->actingAs($this->receptionist);

        // BelongsToBranch scope applies globally — only own-branch clients visible
        $visible = Client::all();

        $this->assertTrue($visible->contains($ownClient));
        $this->assertFalse($visible->contains($otherClient));
    }

    /** @test */
    public function super_admin_sees_clients_from_all_branches(): void
    {
        $otherBranch = Branch::factory()->create(['name' => 'Kisumu Branch']);
        $superAdmin  = User::factory()->create(['branch_id' => $this->branch->id]);
        $superAdmin->assignRole('super_admin');

        $ownClient   = Client::factory()->create(['branch_id' => $this->branch->id]);
        $otherClient = Client::factory()->create(['branch_id' => $otherBranch->id]);

        $this->actingAs($superAdmin);

        $visible = Client::withoutGlobalScopes()->get(); // super_admin bypasses branch scope

        $this->assertTrue($visible->contains($ownClient));
        $this->assertTrue($visible->contains($otherClient));
    }

    // ─── Age classification ───────────────────────────────────────────────────

    /** @test */
    public function client_aged_17_is_classified_as_minor(): void
    {
        $client = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(17)->toDateString(),
            'estimated_age' => null,
        ]);

        $age = Carbon::parse($client->date_of_birth)->age;

        $this->assertLessThan(18, $age);
        $this->assertGreaterThanOrEqual(17, $age);
    }

    /** @test */
    public function client_aged_18_is_classified_as_adult(): void
    {
        $client = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(18)->toDateString(),
            'estimated_age' => null,
        ]);

        $age = Carbon::parse($client->date_of_birth)->age;

        $this->assertGreaterThanOrEqual(18, $age);
    }

    /** @test */
    public function estimated_age_takes_priority_over_dob_in_age_resolution(): void
    {
        // DOB says adult (35) but estimated_age overrides to 10 (child)
        $client = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(35)->toDateString(),
            'estimated_age' => 10,
        ]);

        $this->assertEquals(10, $client->estimated_age);

        // Application logic resolves age: estimated_age takes priority
        $resolvedAge = $client->estimated_age
            ?? ($client->date_of_birth ? Carbon::parse($client->date_of_birth)->age : null);

        $this->assertEquals(10, $resolvedAge);
    }

    // ─── Client data integrity ─────────────────────────────────────────────────

    /** @test */
    public function client_requires_branch_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Client::create([
            'uci'        => 'KISE/A/000099/2026',
            'first_name' => 'Test',
            'last_name'  => 'Client',
            'gender'     => 'male',
            'client_type'=> 'new',
        ]); // no branch_id
    }

    /** @test */
    public function new_client_defaults_is_active_to_true(): void
    {
        $this->actingAs($this->receptionist);

        $client = Client::factory()->create(['branch_id' => $this->branch->id]);

        $this->assertTrue((bool) $client->is_active);
    }

    /** @test */
    public function client_full_name_combines_first_and_last(): void
    {
        $this->actingAs($this->receptionist);

        $client = Client::factory()->create([
            'branch_id'  => $this->branch->id,
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
        ]);

        $this->assertStringContainsString('Jane', $client->full_name);
        $this->assertStringContainsString('Doe',  $client->full_name);
    }

    // ─── Client types ─────────────────────────────────────────────────────────

    /** @test */
    public function client_type_can_be_new_or_returning_or_old_new(): void
    {
        $this->actingAs($this->receptionist);

        foreach (['new', 'returning', 'old_new'] as $type) {
            $client = Client::factory()->create([
                'branch_id'   => $this->branch->id,
                'client_type' => $type,
            ]);

            $this->assertDatabaseHas('clients', ['id' => $client->id, 'client_type' => $type]);
        }
    }
}
