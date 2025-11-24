<?php

namespace Database\Seeders;

use App\Models\SubCounty;
use App\Models\Ward;
use Illuminate\Database\Seeder;

class WardSeeder extends Seeder
{
    public function run(): void
    {
        $kasarani = SubCounty::where('code', '047-07')->first();

        if (!$kasarani) {
            $this->command->error('Kasarani sub-county not found. Please run SubCountySeeder first.');
            return;
        }

        $wards = [
            ['sub_county_id' => $kasarani->id, 'code' => '047-07-01', 'name' => 'Clay City', 'sort_order' => 1],
            ['sub_county_id' => $kasarani->id, 'code' => '047-07-02', 'name' => 'Mwiki', 'sort_order' => 2],
            ['sub_county_id' => $kasarani->id, 'code' => '047-07-03', 'name' => 'Kasarani', 'sort_order' => 3],
            ['sub_county_id' => $kasarani->id, 'code' => '047-07-04', 'name' => 'Njiru', 'sort_order' => 4],
            ['sub_county_id' => $kasarani->id, 'code' => '047-07-05', 'name' => 'Ruai', 'sort_order' => 5],
        ];

        foreach ($wards as $ward) {
            Ward::create($ward);
        }

        $this->command->info('5 Kasarani wards seeded successfully!');
        $this->command->warn('Note: Add wards for other sub-counties as needed.');
    }
}