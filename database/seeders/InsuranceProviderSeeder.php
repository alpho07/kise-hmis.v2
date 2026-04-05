<?php

namespace Database\Seeders;

use App\Models\InsuranceProvider;
use Illuminate\Database\Seeder;

class InsuranceProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['code' => 'SHA',      'name' => 'Social Health Authority', 'short_name' => 'SHA',      'type' => 'government_scheme'],
            ['code' => 'NCPWD',    'name' => 'NCPWD',                   'short_name' => 'NCPWD',    'type' => 'government_scheme'],
            ['code' => 'ECITIZEN', 'name' => 'E-Citizen',               'short_name' => 'E-Citizen','type' => 'ecitizen'],
        ];

        foreach ($providers as $provider) {
            InsuranceProvider::updateOrCreate(
                ['code' => $provider['code']],
                array_merge($provider, ['is_active' => true, 'sort_order' => 1])
            );
        }
    }
}
