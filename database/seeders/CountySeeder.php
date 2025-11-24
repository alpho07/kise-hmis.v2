<?php

namespace Database\Seeders;

use App\Models\County;
use Illuminate\Database\Seeder;

class CountySeeder extends Seeder
{
    public function run(): void
    {
        $counties = [
            ['code' => '001', 'name' => 'Mombasa', 'sort_order' => 1],
            ['code' => '002', 'name' => 'Kwale', 'sort_order' => 2],
            ['code' => '003', 'name' => 'Kilifi', 'sort_order' => 3],
            ['code' => '004', 'name' => 'Tana River', 'sort_order' => 4],
            ['code' => '005', 'name' => 'Lamu', 'sort_order' => 5],
            ['code' => '006', 'name' => 'Taita Taveta', 'sort_order' => 6],
            ['code' => '007', 'name' => 'Garissa', 'sort_order' => 7],
            ['code' => '008', 'name' => 'Wajir', 'sort_order' => 8],
            ['code' => '009', 'name' => 'Mandera', 'sort_order' => 9],
            ['code' => '010', 'name' => 'Marsabit', 'sort_order' => 10],
            ['code' => '011', 'name' => 'Isiolo', 'sort_order' => 11],
            ['code' => '012', 'name' => 'Meru', 'sort_order' => 12],
            ['code' => '013', 'name' => 'Tharaka Nithi', 'sort_order' => 13],
            ['code' => '014', 'name' => 'Embu', 'sort_order' => 14],
            ['code' => '015', 'name' => 'Kitui', 'sort_order' => 15],
            ['code' => '016', 'name' => 'Machakos', 'sort_order' => 16],
            ['code' => '017', 'name' => 'Makueni', 'sort_order' => 17],
            ['code' => '018', 'name' => 'Nyandarua', 'sort_order' => 18],
            ['code' => '019', 'name' => 'Nyeri', 'sort_order' => 19],
            ['code' => '020', 'name' => 'Kirinyaga', 'sort_order' => 20],
            ['code' => '021', 'name' => 'Murang\'a', 'sort_order' => 21],
            ['code' => '022', 'name' => 'Kiambu', 'sort_order' => 22],
            ['code' => '023', 'name' => 'Turkana', 'sort_order' => 23],
            ['code' => '024', 'name' => 'West Pokot', 'sort_order' => 24],
            ['code' => '025', 'name' => 'Samburu', 'sort_order' => 25],
            ['code' => '026', 'name' => 'Trans Nzoia', 'sort_order' => 26],
            ['code' => '027', 'name' => 'Uasin Gishu', 'sort_order' => 27],
            ['code' => '028', 'name' => 'Elgeyo Marakwet', 'sort_order' => 28],
            ['code' => '029', 'name' => 'Nandi', 'sort_order' => 29],
            ['code' => '030', 'name' => 'Baringo', 'sort_order' => 30],
            ['code' => '031', 'name' => 'Laikipia', 'sort_order' => 31],
            ['code' => '032', 'name' => 'Nakuru', 'sort_order' => 32],
            ['code' => '033', 'name' => 'Narok', 'sort_order' => 33],
            ['code' => '034', 'name' => 'Kajiado', 'sort_order' => 34],
            ['code' => '035', 'name' => 'Kericho', 'sort_order' => 35],
            ['code' => '036', 'name' => 'Bomet', 'sort_order' => 36],
            ['code' => '037', 'name' => 'Kakamega', 'sort_order' => 37],
            ['code' => '038', 'name' => 'Vihiga', 'sort_order' => 38],
            ['code' => '039', 'name' => 'Bungoma', 'sort_order' => 39],
            ['code' => '040', 'name' => 'Busia', 'sort_order' => 40],
            ['code' => '041', 'name' => 'Siaya', 'sort_order' => 41],
            ['code' => '042', 'name' => 'Kisumu', 'sort_order' => 42],
            ['code' => '043', 'name' => 'Homa Bay', 'sort_order' => 43],
            ['code' => '044', 'name' => 'Migori', 'sort_order' => 44],
            ['code' => '045', 'name' => 'Kisii', 'sort_order' => 45],
            ['code' => '046', 'name' => 'Nyamira', 'sort_order' => 46],
            ['code' => '047', 'name' => 'Nairobi', 'sort_order' => 47],
        ];

        foreach ($counties as $county) {
            County::create($county);
        }

        $this->command->info('47 counties seeded successfully!');
    }
}