<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\Service;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Unit tests for Service age-group scopes.
 *
 * Business rules:
 * - age_group = 'child'  → only for clients < 18 years old
 * - age_group = 'adult'  → only for clients ≥ 18 years old
 * - age_group = 'all'    → available to any client
 * - scopeForClient resolves age from estimated_age first, DOB second
 */
class ServiceAgeGroupScopeTest extends TestCase
{

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function makeService(string $ageGroup, string $name = null): Service
    {
        $dept = Department::firstOrCreate(
            ['name' => 'Test Department', 'branch_id' => $this->branch->id],
            ['code' => 'TEST', 'description' => 'Test', 'is_active' => true, 'branch_id' => $this->branch->id]
        );

        return Service::create([
            'code'          => 'TEST-' . uniqid(),
            'name'          => $name ?? "Test Service ({$ageGroup})",
            'base_price'    => 500,
            'service_type'  => 'therapy',
            'age_group'     => $ageGroup,
            'category'      => 'Therapy',
            'is_active'     => true,
            'duration_minutes' => 60,
            'department_id' => $dept->id,
        ]);
    }

    private function makeClient(array $overrides = []): Client
    {
        return Client::factory()->create(array_merge([
            'branch_id' => $this->branch->id,
        ], $overrides));
    }

    // ─── scopeForAgeGroup ─────────────────────────────────────────────────────

    /** @test */
    public function scope_for_age_group_child_returns_child_and_all_services(): void
    {
        $childSvc  = $this->makeService('child');
        $adultSvc  = $this->makeService('adult');
        $allSvc    = $this->makeService('all');

        $results = Service::forAgeGroup('child')->pluck('id');

        $this->assertContains($childSvc->id, $results);
        $this->assertContains($allSvc->id, $results);
        $this->assertNotContains($adultSvc->id, $results);
    }

    /** @test */
    public function scope_for_age_group_adult_returns_adult_and_all_services(): void
    {
        $childSvc = $this->makeService('child');
        $adultSvc = $this->makeService('adult');
        $allSvc   = $this->makeService('all');

        $results = Service::forAgeGroup('adult')->pluck('id');

        $this->assertContains($adultSvc->id, $results);
        $this->assertContains($allSvc->id, $results);
        $this->assertNotContains($childSvc->id, $results);
    }

    /** @test */
    public function scope_for_age_group_all_returns_every_service(): void
    {
        $childSvc = $this->makeService('child');
        $adultSvc = $this->makeService('adult');
        $allSvc   = $this->makeService('all');

        $results = Service::forAgeGroup('all')->pluck('id');

        $this->assertContains($childSvc->id, $results);
        $this->assertContains($adultSvc->id, $results);
        $this->assertContains($allSvc->id, $results);
    }

    // ─── scopeForClient ───────────────────────────────────────────────────────

    /** @test */
    public function scope_for_client_filters_child_services_for_minor(): void
    {
        $childSvc = $this->makeService('child');
        $adultSvc = $this->makeService('adult');
        $allSvc   = $this->makeService('all');

        // Client aged 8 (minor) via date_of_birth
        $child = $this->makeClient([
            'date_of_birth' => Carbon::now()->subYears(8)->toDateString(),
        ]);

        $results = Service::forClient($child)->pluck('id');

        $this->assertContains($childSvc->id, $results);
        $this->assertContains($allSvc->id, $results);
        $this->assertNotContains($adultSvc->id, $results);
    }

    /** @test */
    public function scope_for_client_filters_adult_services_for_adult(): void
    {
        $childSvc = $this->makeService('child');
        $adultSvc = $this->makeService('adult');
        $allSvc   = $this->makeService('all');

        // Client aged 35 (adult) via date_of_birth
        $adult = $this->makeClient([
            'date_of_birth' => Carbon::now()->subYears(35)->toDateString(),
        ]);

        $results = Service::forClient($adult)->pluck('id');

        $this->assertContains($adultSvc->id, $results);
        $this->assertContains($allSvc->id, $results);
        $this->assertNotContains($childSvc->id, $results);
    }

    /** @test */
    public function scope_for_client_uses_estimated_age_over_dob_when_both_present(): void
    {
        $childSvc = $this->makeService('child');
        $adultSvc = $this->makeService('adult');

        // DOB says adult (35), but estimated_age of 10 takes priority
        $clientWithEstimatedAge = $this->makeClient([
            'date_of_birth' => Carbon::now()->subYears(35)->toDateString(), // adult DOB
            'estimated_age' => 10, // child estimated_age wins
        ]);

        $results = Service::forClient($clientWithEstimatedAge)->pluck('id');

        $this->assertContains($childSvc->id, $results);
        $this->assertNotContains($adultSvc->id, $results);
    }

    /** @test */
    public function scope_for_client_estimated_age_overrides_dob(): void
    {
        $childSvc = $this->makeService('child');
        $adultSvc = $this->makeService('adult');

        // DOB says adult (35), estimated_age says child (10)
        // estimated_age takes precedence per Service::scopeForClient implementation
        $client = $this->makeClient([
            'date_of_birth' => Carbon::now()->subYears(35)->toDateString(),
            'estimated_age' => 10,
        ]);

        $results = Service::forClient($client)->pluck('id');

        $this->assertContains($childSvc->id, $results);
        $this->assertNotContains($adultSvc->id, $results);
    }

    /** @test */
    public function scope_for_client_uses_dob_when_estimated_age_is_null(): void
    {
        $childSvc = $this->makeService('child');
        $adultSvc = $this->makeService('adult');

        // No estimated_age → fall back to DOB
        $clientFromDob = $this->makeClient([
            'date_of_birth' => Carbon::now()->subYears(30)->toDateString(),
            'estimated_age' => null,
        ]);

        $results = Service::forClient($clientFromDob)->pluck('id');

        $this->assertContains($adultSvc->id, $results);
        $this->assertNotContains($childSvc->id, $results);
    }

    /** @test */
    public function seventeen_year_old_is_classified_as_child(): void
    {
        $childSvc = $this->makeService('child');
        $adultSvc = $this->makeService('adult');

        $client = $this->makeClient([
            'date_of_birth' => Carbon::now()->subYears(17)->toDateString(),
        ]);

        $results = Service::forClient($client)->pluck('id');

        $this->assertContains($childSvc->id, $results);
        $this->assertNotContains($adultSvc->id, $results);
    }

    /** @test */
    public function eighteen_year_old_is_classified_as_adult(): void
    {
        $childSvc = $this->makeService('child');
        $adultSvc = $this->makeService('adult');

        $client = $this->makeClient([
            'date_of_birth' => Carbon::now()->subYears(18)->toDateString(),
        ]);

        $results = Service::forClient($client)->pluck('id');

        $this->assertContains($adultSvc->id, $results);
        $this->assertNotContains($childSvc->id, $results);
    }

    // ─── isAvailableForAge / isAvailableForClient ─────────────────────────────

    /** @test */
    public function is_available_for_age_respects_child_service(): void
    {
        $childSvc = $this->makeService('child');

        $this->assertTrue($childSvc->isAvailableForAge(5));
        $this->assertTrue($childSvc->isAvailableForAge(17));
        $this->assertFalse($childSvc->isAvailableForAge(18));
        $this->assertFalse($childSvc->isAvailableForAge(40));
        $this->assertTrue($childSvc->isAvailableForAge(null)); // unknown age → assume available (safe default)
    }

    /** @test */
    public function is_available_for_age_respects_adult_service(): void
    {
        $adultSvc = $this->makeService('adult');

        $this->assertFalse($adultSvc->isAvailableForAge(17));
        $this->assertTrue($adultSvc->isAvailableForAge(18));
        $this->assertTrue($adultSvc->isAvailableForAge(65));
    }

    /** @test */
    public function is_available_for_age_all_service_accepts_any_age(): void
    {
        $allSvc = $this->makeService('all');

        $this->assertTrue($allSvc->isAvailableForAge(5));
        $this->assertTrue($allSvc->isAvailableForAge(17));
        $this->assertTrue($allSvc->isAvailableForAge(18));
        $this->assertTrue($allSvc->isAvailableForAge(80));
    }
}
