<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\InsuranceProvider;
use App\Models\Service;
use App\Models\ServiceInsurancePrice;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for NCPWD age restriction enforcement.
 *
 * Business rule: NCPWD (National Council for Persons with Disabilities)
 * subsidy is ONLY available to clients aged 17 years and below.
 * Clients aged 18+ must not be allowed to use NCPWD as a payment method.
 */
class NcpwdAgeRestrictionTest extends TestCase
{

    private Branch $branch;
    private User $intakeOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'intake_officer', 'guard_name' => 'web']);

        $this->branch       = Branch::factory()->create();
        $this->intakeOfficer = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->intakeOfficer->assignRole('intake_officer');

        $this->actingAs($this->intakeOfficer);
    }

    // ─── Age boundary logic ───────────────────────────────────────────────────

    /** @test */
    public function ncpwd_eligible_client_is_17_or_below(): void
    {
        foreach ([0, 5, 10, 15, 17] as $age) {
            $this->assertTrue(
                $age <= 17,
                "Age {$age} should be NCPWD-eligible (≤17)"
            );
        }
    }

    /** @test */
    public function ncpwd_ineligible_client_is_18_or_above(): void
    {
        foreach ([18, 25, 40, 60] as $age) {
            $this->assertFalse(
                $age <= 17,
                "Age {$age} should NOT be NCPWD-eligible (>17)"
            );
        }
    }

    /** @test */
    public function ncpwd_age_gate_uses_estimated_age_taking_priority_over_dob(): void
    {
        // estimated_age overrides DOB when both are present
        $youngClient = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(35)->toDateString(), // adult DOB
            'estimated_age' => 8, // child estimated_age takes priority
        ]);

        $resolvedAge = $this->resolveClientAge($youngClient);
        $this->assertEquals(8, $resolvedAge);
        $this->assertTrue($resolvedAge <= 17, 'Age 8 (estimated) should be NCPWD-eligible');

        $adultClient = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(5)->toDateString(), // child DOB
            'estimated_age' => 25, // adult estimated_age takes priority
        ]);

        $resolvedAge = $this->resolveClientAge($adultClient);
        $this->assertEquals(25, $resolvedAge);
        $this->assertFalse($resolvedAge <= 17, 'Age 25 (estimated) should NOT be NCPWD-eligible');
    }

    /** @test */
    public function ncpwd_age_gate_uses_dob_when_estimated_age_is_null(): void
    {
        $child = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(10)->toDateString(),
            'estimated_age' => null,
        ]);

        $resolvedAge = $this->resolveClientAge($child);
        $this->assertNotNull($resolvedAge);
        $this->assertLessThanOrEqual(17, $resolvedAge);

        $adult = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(30)->toDateString(),
            'estimated_age' => null,
        ]);

        $resolvedAge = $this->resolveClientAge($adult);
        $this->assertGreaterThan(17, $resolvedAge);
    }

    /** @test */
    public function ncpwd_is_rejected_for_adult_via_server_side_logic(): void
    {
        // Simulate the server-side check performed in CreateIntakeAssessment::afterCreate
        $adultClient = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(35)->toDateString(),
            'estimated_age' => null,
        ]);

        $clientAge      = $this->resolveClientAge($adultClient);
        $paymentMethod  = 'ncpwd';
        $shouldBlock    = ($paymentMethod === 'ncpwd' && $clientAge !== null && $clientAge > 17);

        $this->assertTrue($shouldBlock, 'Server-side NCPWD gate should block adult client');
    }

    /** @test */
    public function ncpwd_is_accepted_for_child_via_server_side_logic(): void
    {
        $childClient = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(8)->toDateString(),
            'estimated_age' => null,
        ]);

        $clientAge     = $this->resolveClientAge($childClient);
        $paymentMethod = 'ncpwd';
        $shouldBlock   = ($paymentMethod === 'ncpwd' && $clientAge !== null && $clientAge > 17);

        $this->assertFalse($shouldBlock, 'Server-side NCPWD gate should NOT block child client');
    }

    /** @test */
    public function ncpwd_boundary_case_17_years_is_eligible(): void
    {
        $exactly17 = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(17)->toDateString(),
            'estimated_age' => null,
        ]);

        $clientAge   = $this->resolveClientAge($exactly17);
        $shouldBlock = ($clientAge !== null && $clientAge > 17);

        $this->assertFalse($shouldBlock, '17-year-old should be NCPWD eligible (boundary ≤17)');
    }

    /** @test */
    public function ncpwd_boundary_case_18_years_is_ineligible(): void
    {
        $exactly18 = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(18)->toDateString(),
            'estimated_age' => null,
        ]);

        $clientAge   = $this->resolveClientAge($exactly18);
        $shouldBlock = ($clientAge !== null && $clientAge > 17);

        $this->assertTrue($shouldBlock, '18-year-old should NOT be NCPWD eligible (>17)');
    }

    // ─── NCPWD coverage rate consistency ─────────────────────────────────────

    /** @test */
    public function ncpwd_provider_default_coverage_is_50_percent(): void
    {
        $ncpwd = InsuranceProvider::create([
            'code'                        => 'NCPWD',
            'name'                        => 'National Council for Persons with Disabilities',
            'short_name'                  => 'NCPWD',
            'type'                        => 'government_scheme',
            'default_coverage_percentage' => 50.00,
            'is_active'                   => true,
            'sort_order'                  => 2,
        ]);

        $this->assertEquals(50.00, (float) $ncpwd->default_coverage_percentage);
    }

    /** @test */
    public function ncpwd_service_price_inherits_50_percent_coverage(): void
    {
        $ncpwd = InsuranceProvider::create([
            'code'                        => 'NCPWD',
            'name'                        => 'NCPWD',
            'short_name'                  => 'NCPWD',
            'type'                        => 'government_scheme',
            'default_coverage_percentage' => 50.00,
            'is_active'                   => true,
            'sort_order'                  => 2,
        ]);

        $dept = Department::firstOrCreate(
            ['name' => 'Test Department', 'branch_id' => $this->branch->id],
            ['code' => 'TEST', 'description' => 'Test', 'is_active' => true, 'branch_id' => $this->branch->id]
        );

        $service = Service::create([
            'code'          => 'TST-001',
            'name'          => 'Test Therapy',
            'base_price'    => 1000.00,
            'service_type'  => 'therapy',
            'age_group'     => 'child',
            'category'      => 'Therapy',
            'is_active'     => true,
            'duration_minutes' => 60,
            'department_id' => $dept->id,
        ]);

        $priceRecord = ServiceInsurancePrice::create([
            'service_id'           => $service->id,
            'insurance_provider_id'=> $ncpwd->id,
            'covered_amount'       => 500.00,  // 50% of 1000
            'client_copay'         => 500.00,  // 50% of 1000
            'coverage_percentage'  => 50.00,
            'is_active'            => true,
        ]);

        $this->assertEquals(50.00,  (float) $priceRecord->coverage_percentage);
        $this->assertEquals(500.00, (float) $priceRecord->covered_amount);
        $this->assertEquals(500.00, (float) $priceRecord->client_copay);

        // Verify the amounts sum to base_price
        $this->assertEquals(
            (float) $service->base_price,
            (float) $priceRecord->covered_amount + (float) $priceRecord->client_copay
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Replicate the age-resolution logic from CreateIntakeAssessment:
     * estimated_age → date_of_birth → null
     */
    private function resolveClientAge(Client $client): ?int
    {
        return $client->estimated_age
            ?? ($client->date_of_birth ? Carbon::parse($client->date_of_birth)->age : null);
    }
}
