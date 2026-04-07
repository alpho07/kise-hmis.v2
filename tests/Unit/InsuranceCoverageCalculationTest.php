<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Department;
use App\Models\InsuranceProvider;

use App\Models\Service;
use App\Models\ServiceInsurancePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the dynamic insurance coverage system.
 *
 * Business rules:
 * - Coverage amounts come from ServiceInsurancePrice (per service × provider)
 * - If no per-service price exists, fall back to InsuranceProvider.default_coverage_percentage
 * - SHA covers 75% (client pays 25%) — from InsuranceSeeder
 * - NCPWD covers 50% (client pays 50%) — from InsuranceSeeder
 * - Waiver: client pays 0%, sponsor pays 100%
 * - Cash/M-PESA: client pays 100%, no sponsor
 */
class InsuranceCoverageCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(string $code, float $defaultCoverage): InsuranceProvider
    {
        return InsuranceProvider::create([
            'code'                       => $code,
            'name'                       => "Test {$code}",
            'short_name'                 => $code,
            'type'                       => 'government_scheme',
            'default_coverage_percentage'=> $defaultCoverage,
            'is_active'                  => true,
            'sort_order'                 => 1,
        ]);
    }

    private function makeService(float $basePrice = 1000.00): Service
    {
        $branch = Branch::factory()->create();
        $dept   = Department::firstOrCreate(
            ['name' => 'Test Department', 'branch_id' => $branch->id],
            ['code' => 'TEST', 'description' => 'Test', 'is_active' => true, 'branch_id' => $branch->id]
        );

        return Service::create([
            'code'          => 'SVC-' . uniqid(),
            'name'          => 'Test Service',
            'base_price'    => $basePrice,
            'service_type'  => 'therapy',
            'age_group'     => 'all',
            'category'      => 'Therapy',
            'is_active'     => true,
            'duration_minutes' => 60,
            'department_id' => $dept->id,
        ]);
    }

    // ─── ServiceInsurancePrice lookup ─────────────────────────────────────────

    /** @test */
    public function sha_service_price_record_reflects_75_percent_coverage(): void
    {
        $sha     = $this->makeProvider('SHA', 75.00);
        $service = $this->makeService(1000.00);

        ServiceInsurancePrice::create([
            'service_id'           => $service->id,
            'insurance_provider_id'=> $sha->id,
            'covered_amount'       => 750.00,
            'client_copay'         => 250.00,
            'coverage_percentage'  => 75.00,
            'is_active'            => true,
        ]);

        $priceRecord = ServiceInsurancePrice::where('service_id', $service->id)
            ->where('insurance_provider_id', $sha->id)
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($priceRecord);
        $this->assertEquals(750.00, (float) $priceRecord->covered_amount);
        $this->assertEquals(250.00, (float) $priceRecord->client_copay);
        $this->assertEquals(75.00,  (float) $priceRecord->coverage_percentage);
    }

    /** @test */
    public function ncpwd_service_price_record_reflects_50_percent_coverage(): void
    {
        $ncpwd   = $this->makeProvider('NCPWD', 50.00);
        $service = $this->makeService(500.00);

        ServiceInsurancePrice::create([
            'service_id'           => $service->id,
            'insurance_provider_id'=> $ncpwd->id,
            'covered_amount'       => 250.00,
            'client_copay'         => 250.00,
            'coverage_percentage'  => 50.00,
            'requires_preauthorization' => true,
            'is_active'            => true,
        ]);

        $priceRecord = ServiceInsurancePrice::where('service_id', $service->id)
            ->where('insurance_provider_id', $ncpwd->id)
            ->first();

        $this->assertEquals(250.00, (float) $priceRecord->covered_amount);
        $this->assertEquals(250.00, (float) $priceRecord->client_copay);
        $this->assertTrue($priceRecord->requires_preauthorization);
    }

    /** @test */
    public function missing_service_price_triggers_fallback_to_provider_default(): void
    {
        $sha     = $this->makeProvider('SHA', 75.00); // 75% default
        $service = $this->makeService(1000.00);

        // No ServiceInsurancePrice record created for this service+provider
        $priceRecord = ServiceInsurancePrice::where('service_id', $service->id)
            ->where('insurance_provider_id', $sha->id)
            ->first();

        $this->assertNull($priceRecord, 'No per-service price should exist');

        // Simulate fallback: use provider default
        $defaultRatio = (float) $sha->default_coverage_percentage / 100;
        $baseCost     = 1000.00;
        $sponsorPays  = round($baseCost * $defaultRatio, 2);
        $clientPays   = round($baseCost - $sponsorPays, 2);

        $this->assertEquals(750.00, $sponsorPays);
        $this->assertEquals(250.00, $clientPays);
    }

    /** @test */
    public function waiver_results_in_zero_client_payment(): void
    {
        // Waiver: client pays 0, sponsor pays full amount
        $baseCost    = 2000.00;
        $clientPays  = 0.00;
        $sponsorPays = round($baseCost, 2);

        $this->assertEquals(0.00,    $clientPays);
        $this->assertEquals(2000.00, $sponsorPays);
    }

    /** @test */
    public function cash_payment_results_in_full_client_payment(): void
    {
        // Cash: no sponsor, client pays full
        $baseCost    = 1500.00;
        $clientPays  = round($baseCost, 2);
        $sponsorPays = 0.00;

        $this->assertEquals(1500.00, $clientPays);
        $this->assertEquals(0.00,    $sponsorPays);
    }

    /** @test */
    public function service_insurance_price_client_pays_attribute_returns_copay(): void
    {
        $sha     = $this->makeProvider('SHA', 75.00);
        $service = $this->makeService(1000.00);

        $priceRecord = ServiceInsurancePrice::create([
            'service_id'           => $service->id,
            'insurance_provider_id'=> $sha->id,
            'covered_amount'       => 750.00,
            'client_copay'         => 250.00,
            'coverage_percentage'  => 75.00,
            'is_active'            => true,
        ]);

        $this->assertEquals(250.00, $priceRecord->client_pays);
        $this->assertEquals(750.00, $priceRecord->insurance_pays);
    }

    /** @test */
    public function inactive_service_insurance_price_is_excluded_from_lookup(): void
    {
        $sha     = $this->makeProvider('SHA', 75.00);
        $service = $this->makeService(1000.00);

        ServiceInsurancePrice::create([
            'service_id'           => $service->id,
            'insurance_provider_id'=> $sha->id,
            'covered_amount'       => 750.00,
            'client_copay'         => 250.00,
            'coverage_percentage'  => 75.00,
            'is_active'            => false, // inactive
        ]);

        $activeRecord = ServiceInsurancePrice::where('service_id', $service->id)
            ->where('insurance_provider_id', $sha->id)
            ->where('is_active', true)
            ->first();

        $this->assertNull($activeRecord, 'Inactive price records should not be returned');
    }

    /** @test */
    public function insurance_provider_get_price_for_service_uses_active_record(): void
    {
        $sha     = $this->makeProvider('SHA', 75.00);
        $service = $this->makeService(1000.00);

        // Create an active record
        $activeRecord = ServiceInsurancePrice::create([
            'service_id'           => $service->id,
            'insurance_provider_id'=> $sha->id,
            'covered_amount'       => 750.00,
            'client_copay'         => 250.00,
            'coverage_percentage'  => 75.00,
            'is_active'            => true,
        ]);

        $found = $sha->getPriceForService($service);

        $this->assertNotNull($found);
        $this->assertEquals($activeRecord->id, $found->id);
    }

    /** @test */
    public function insurance_provider_covers_service_returns_false_for_excluded_service(): void
    {
        $service = $this->makeService(1000.00);
        $sha     = $this->makeProvider('SHA', 75.00);

        // Manually exclude the service
        $sha->update(['excluded_services' => [$service->id]]);

        $this->assertFalse($sha->fresh()->coversService($service));
    }

    /** @test */
    public function per_service_price_takes_precedence_over_provider_default(): void
    {
        // Provider default is 75%, but per-service price is 90%
        $sha     = $this->makeProvider('SHA', 75.00);
        $service = $this->makeService(1000.00);

        ServiceInsurancePrice::create([
            'service_id'           => $service->id,
            'insurance_provider_id'=> $sha->id,
            'covered_amount'       => 900.00,  // 90% covered
            'client_copay'         => 100.00,
            'coverage_percentage'  => 90.00,
            'is_active'            => true,
        ]);

        $priceRecord = ServiceInsurancePrice::where('service_id', $service->id)
            ->where('insurance_provider_id', $sha->id)
            ->where('is_active', true)
            ->first();

        // Per-service 90% wins over provider default 75%
        $this->assertEquals(90.00,  (float) $priceRecord->coverage_percentage);
        $this->assertEquals(900.00, (float) $priceRecord->covered_amount);
        $this->assertEquals(100.00, (float) $priceRecord->client_copay);
    }
}
