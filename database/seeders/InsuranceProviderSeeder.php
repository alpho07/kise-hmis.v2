<?php

namespace Database\Seeders;

use App\Models\InsuranceProvider;
use Illuminate\Database\Seeder;

class InsuranceProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['code' => 'SHA',       'name' => 'Social Health Authority', 'short_name' => 'SHA',      'type' => 'government_scheme', 'sort_order' => 10],
            ['code' => 'NCPWD',     'name' => 'NCPWD',                   'short_name' => 'NCPWD',    'type' => 'government_scheme', 'sort_order' => 11],
            ['code' => 'E-CITIZEN', 'name' => 'E-Citizen',               'short_name' => 'E-Citizen','type' => 'ecitizen',          'sort_order' => 12],
        ];

        foreach ($providers as $provider) {
            InsuranceProvider::updateOrCreate(
                ['code' => $provider['code']],
                array_merge($provider, ['is_active' => true])
            );
        }
    }
}
