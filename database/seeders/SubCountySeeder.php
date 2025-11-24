<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\SubCounty;
use Illuminate\Database\Seeder;

class SubCountySeeder extends Seeder
{
    public function run(): void
    {
        $nairobi = County::where('code', '047')->first();

        if (!$nairobi) {
            $this->command->error('Nairobi county not found. Please run CountySeeder first.');
            return;
        }

        $subCounties = [
            ['county_id' => $nairobi->id, 'code' => '047-01', 'name' => 'Westlands', 'sort_order' => 1],
            ['county_id' => $nairobi->id, 'code' => '047-02', 'name' => 'Dagoretti North', 'sort_order' => 2],
            ['county_id' => $nairobi->id, 'code' => '047-03', 'name' => 'Dagoretti South', 'sort_order' => 3],
            ['county_id' => $nairobi->id, 'code' => '047-04', 'name' => 'Langata', 'sort_order' => 4],
            ['county_id' => $nairobi->id, 'code' => '047-05', 'name' => 'Kibra', 'sort_order' => 5],
            ['county_id' => $nairobi->id, 'code' => '047-06', 'name' => 'Roysambu', 'sort_order' => 6],
            ['county_id' => $nairobi->id, 'code' => '047-07', 'name' => 'Kasarani', 'sort_order' => 7],
            ['county_id' => $nairobi->id, 'code' => '047-08', 'name' => 'Ruaraka', 'sort_order' => 8],
            ['county_id' => $nairobi->id, 'code' => '047-09', 'name' => 'Embakasi South', 'sort_order' => 9],
            ['county_id' => $nairobi->id, 'code' => '047-10', 'name' => 'Embakasi North', 'sort_order' => 10],
            ['county_id' => $nairobi->id, 'code' => '047-11', 'name' => 'Embakasi Central', 'sort_order' => 11],
            ['county_id' => $nairobi->id, 'code' => '047-12', 'name' => 'Embakasi East', 'sort_order' => 12],
            ['county_id' => $nairobi->id, 'code' => '047-13', 'name' => 'Embakasi West', 'sort_order' => 13],
            ['county_id' => $nairobi->id, 'code' => '047-14', 'name' => 'Makadara', 'sort_order' => 14],
            ['county_id' => $nairobi->id, 'code' => '047-15', 'name' => 'Kamukunji', 'sort_order' => 15],
            ['county_id' => $nairobi->id, 'code' => '047-16', 'name' => 'Starehe', 'sort_order' => 16],
            ['county_id' => $nairobi->id, 'code' => '047-17', 'name' => 'Mathare', 'sort_order' => 17],
        ];

        foreach ($subCounties as $subCounty) {
            SubCounty::create($subCounty);
        }

        $this->command->info('17 Nairobi sub-counties seeded successfully!');
        $this->command->warn('Note: Add sub-counties for other 46 counties as needed.');
    }
}