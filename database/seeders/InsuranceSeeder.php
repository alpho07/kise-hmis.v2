<?php

namespace Database\Seeders;

use App\Models\InsuranceProvider;
use App\Models\Service;
use App\Models\ServiceInsurancePrice;
use Illuminate\Database\Seeder;

class InsuranceSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💼 Seeding Insurance Providers...');

        // 1. SHA (Social Health Authority)
        $sha = InsuranceProvider::create([
            'code' => 'SHA',
            'name' => 'Social Health Authority',
            'short_name' => 'SHA',
            'type' => 'government_scheme',
            'description' => 'Kenya Social Health Authority - Government health insurance scheme',
            'contact_person' => 'SHA Customer Care',
            'phone' => '+254-709-034-000',
            'email' => 'info@sha.go.ke',
            'claim_submission_method' => 'online',
            'claim_portal_url' => 'https://sha.go.ke/claims',
            'default_coverage_percentage' => 75.00,
            'claim_processing_days' => 14,
            'requires_preauthorization' => false,
            'requires_referral' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // 2. NCPWD (National Council for Persons with Disabilities)
        $ncpwd = InsuranceProvider::create([
            'code' => 'NCPWD',
            'name' => 'National Council for Persons with Disabilities',
            'short_name' => 'NCPWD',
            'type' => 'government_scheme',
            'description' => 'Government support fund for persons with disabilities',
            'contact_person' => 'NCPWD Services',
            'phone' => '+254-020-2251181',
            'email' => 'info@ncpwd.go.ke',
            'claim_submission_method' => 'manual',
            'default_coverage_percentage' => 50.00,
            'claim_processing_days' => 21,
            'requires_preauthorization' => true,
            'requires_referral' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // 3. NHIF (National Hospital Insurance Fund)
        $nhif = InsuranceProvider::create([
            'code' => 'NHIF',
            'name' => 'National Hospital Insurance Fund',
            'short_name' => 'NHIF',
            'type' => 'government_scheme',
            'description' => 'National health insurance for formal employment',
            'contact_person' => 'NHIF Customer Care',
            'phone' => '+254-020-2717000',
            'email' => 'customercare@nhif.or.ke',
            'claim_submission_method' => 'online',
            'claim_portal_url' => 'https://nhif.or.ke',
            'default_coverage_percentage' => 80.00,
            'claim_processing_days' => 10,
            'requires_preauthorization' => false,
            'requires_referral' => true,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // 4. AAR Insurance
        $aar = InsuranceProvider::create([
            'code' => 'AAR',
            'name' => 'AAR Insurance Kenya',
            'short_name' => 'AAR',
            'type' => 'private',
            'description' => 'Private health insurance provider',
            'contact_person' => 'AAR Customer Service',
            'phone' => '+254-709-910-000',
            'email' => 'customer.service@aar.co.ke',
            'claim_submission_method' => 'online',
            'claim_portal_url' => 'https://aar.co.ke/portal',
            'default_coverage_percentage' => 90.00,
            'claim_processing_days' => 7,
            'requires_preauthorization' => true,
            'requires_referral' => false,
            'is_active' => true,
            'sort_order' => 4,
        ]);

        // 5. Jubilee Insurance
        $jubilee = InsuranceProvider::create([
            'code' => 'JUBILEE',
            'name' => 'Jubilee Insurance',
            'short_name' => 'Jubilee',
            'type' => 'private',
            'description' => 'Private medical insurance',
            'contact_person' => 'Jubilee Care',
            'phone' => '+254-709-947-000',
            'email' => 'contactcentre@jubileekenya.com',
            'claim_submission_method' => 'online',
            'claim_portal_url' => 'https://jubileeinsurance.com',
            'default_coverage_percentage' => 85.00,
            'claim_processing_days' => 10,
            'requires_preauthorization' => true,
            'requires_referral' => false,
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $this->command->info('✓ 5 insurance providers seeded');

        // Now seed service insurance prices
        $this->seedServicePrices($sha, $ncpwd, $nhif, $aar, $jubilee);
    }

    protected function seedServicePrices($sha, $ncpwd, $nhif, $aar, $jubilee): void
    {
        $this->command->info('💰 Seeding Service Insurance Prices...');

        $services = Service::all();

        foreach ($services as $service) {
            $basePrice = $service->base_price;

            // SHA Coverage (75% coverage)
            ServiceInsurancePrice::create([
                'service_id' => $service->id,
                'insurance_provider_id' => $sha->id,
                'covered_amount' => $basePrice * 0.75,
                'client_copay' => $basePrice * 0.25,
                'coverage_percentage' => 75.00,
                'is_fully_covered' => false,
                'is_active' => true,
            ]);

            // NCPWD Coverage (50% coverage)
            ServiceInsurancePrice::create([
                'service_id' => $service->id,
                'insurance_provider_id' => $ncpwd->id,
                'covered_amount' => $basePrice * 0.50,
                'client_copay' => $basePrice * 0.50,
                'coverage_percentage' => 50.00,
                'is_fully_covered' => false,
                'requires_preauthorization' => true,
                'is_active' => true,
            ]);

            // NHIF Coverage (80% coverage)
            ServiceInsurancePrice::create([
                'service_id' => $service->id,
                'insurance_provider_id' => $nhif->id,
                'covered_amount' => $basePrice * 0.80,
                'client_copay' => $basePrice * 0.20,
                'coverage_percentage' => 80.00,
                'is_fully_covered' => false,
                'is_active' => true,
            ]);

            // AAR Coverage (90% coverage)
            ServiceInsurancePrice::create([
                'service_id' => $service->id,
                'insurance_provider_id' => $aar->id,
                'covered_amount' => $basePrice * 0.90,
                'client_copay' => $basePrice * 0.10,
                'coverage_percentage' => 90.00,
                'is_fully_covered' => false,
                'requires_preauthorization' => true,
                'is_active' => true,
            ]);

            // Jubilee Coverage (85% coverage)
            ServiceInsurancePrice::create([
                'service_id' => $service->id,
                'insurance_provider_id' => $jubilee->id,
                'covered_amount' => $basePrice * 0.85,
                'client_copay' => $basePrice * 0.15,
                'coverage_percentage' => 85.00,
                'is_fully_covered' => false,
                'requires_preauthorization' => true,
                'is_active' => true,
            ]);
        }

        $totalPrices = $services->count() * 5;
        $this->command->info("✓ {$totalPrices} service insurance prices seeded");
    }
}