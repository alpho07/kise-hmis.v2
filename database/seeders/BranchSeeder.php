<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\County;
use App\Models\SubCounty;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $nairobi = County::where('code', '047')->first();
        $kasarani = SubCounty::where('code', '047-07')->first();

        $branches = [
            [
                'code' => 'KS',
                'name' => 'KISE Kasarani Main Branch',
                'type' => 'main',
                'phone' => '+254712345678',
                'email' => 'kasarani@kise.ac.ke',
                'address' => 'Along Thika Road, Kasarani',
                'county_id' => $nairobi?->id,
                'sub_county_id' => $kasarani?->id,
                'latitude' => -1.2217,
                'longitude' => 36.8977,
                'is_active' => true,
                'opened_at' => '2010-01-15',
                'operating_hours_start' => '08:00:00',
                'operating_hours_end' => '17:00:00',
                'operating_days' => json_encode([1, 2, 3, 4, 5]),
                'max_daily_clients' => 150,
                'settings' => json_encode([
                    'allow_walk_ins' => true,
                    'require_referral' => false,
                    'enable_queue_sms' => true,
                    'default_language' => 'en',
                    'currency' => 'KES',
                ]),
            ],
            [
                'code' => 'LG',
                'name' => 'KISE Langata Satellite',
                'type' => 'satellite',
                'phone' => '+254723456789',
                'email' => 'langata@kise.ac.ke',
                'address' => 'Langata Road, Nairobi',
                'county_id' => $nairobi?->id,
                'is_active' => true,
                'opened_at' => '2018-06-01',
                'operating_hours_start' => '08:00:00',
                'operating_hours_end' => '17:00:00',
                'operating_days' => json_encode([1, 2, 3, 4, 5]),
                'max_daily_clients' => 50,
                'settings' => json_encode([
                    'allow_walk_ins' => true,
                    'require_referral' => false,
                ]),
            ],
            [
                'code' => 'MK-OUT',
                'name' => 'Makueni Outreach Program',
                'type' => 'outreach',
                'county_id' => County::where('code', '017')->first()?->id,
                'is_active' => true,
                'operating_days' => json_encode([1, 3, 5]),
                'max_daily_clients' => 30,
                'settings' => json_encode([
                    'allow_walk_ins' => true,
                    'mobile_clinic' => true,
                ]),
            ],
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }

        $this->command->info('3 branches seeded successfully!');
    }
}