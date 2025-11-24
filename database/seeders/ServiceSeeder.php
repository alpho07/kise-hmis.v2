<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $phys = Department::where('code', 'PHYS')->first();
        $ot = Department::where('code', 'OT')->first();
        $slt = Department::where('code', 'SLT')->first();
        $psy = Department::where('code', 'PSY')->first();
        $edu = Department::where('code', 'EDU')->first();
        $aud = Department::where('code', 'AUD')->first();

        if (!$phys) {
            $this->command->error('Departments not found. Please run DepartmentSeeder first.');
            return;
        }

        $services = [
            [
                'code' => 'PHYS-001',
                'name' => 'Physiotherapy Initial Assessment',
                'description' => 'Initial physiotherapy assessment and treatment plan',
                'department_id' => $phys->id,
                'base_price' => 2000.00,
                'sha_covered' => true,
                'sha_price' => 500.00,
                'ncpwd_covered' => true,
                'ncpwd_price' => 1000.00,
                'requires_assessment' => true,
                'duration_minutes' => 45,
                'category' => 'Assessment',
                'is_active' => true,
            ],
            [
                'code' => 'PHYS-002',
                'name' => 'Physiotherapy Session',
                'description' => 'Individual physiotherapy treatment session',
                'department_id' => $phys->id,
                'base_price' => 1500.00,
                'sha_covered' => true,
                'sha_price' => 300.00,
                'ncpwd_covered' => true,
                'ncpwd_price' => 750.00,
                'is_recurring' => true,
                'duration_minutes' => 30,
                'category' => 'Therapy',
                'is_active' => true,
            ],
            [
                'code' => 'OT-001',
                'name' => 'Occupational Therapy Assessment',
                'description' => 'Comprehensive OT assessment',
                'department_id' => $ot->id,
                'base_price' => 2500.00,
                'sha_covered' => true,
                'sha_price' => 600.00,
                'ncpwd_covered' => true,
                'ncpwd_price' => 1200.00,
                'requires_assessment' => true,
                'duration_minutes' => 60,
                'category' => 'Assessment',
                'is_active' => true,
            ],
            [
                'code' => 'OT-002',
                'name' => 'Occupational Therapy Session',
                'description' => 'Individual OT treatment session',
                'department_id' => $ot->id,
                'base_price' => 1800.00,
                'sha_covered' => true,
                'sha_price' => 400.00,
                'ncpwd_covered' => true,
                'ncpwd_price' => 900.00,
                'is_recurring' => true,
                'duration_minutes' => 45,
                'category' => 'Therapy',
                'is_active' => true,
            ],
            [
                'code' => 'SLT-001',
                'name' => 'Speech & Language Assessment',
                'description' => 'Comprehensive speech and language assessment',
                'department_id' => $slt->id,
                'base_price' => 3000.00,
                'sha_covered' => true,
                'sha_price' => 700.00,
                'ncpwd_covered' => true,
                'ncpwd_price' => 1500.00,
                'requires_assessment' => true,
                'duration_minutes' => 60,
                'category' => 'Assessment',
                'is_active' => true,
            ],
            [
                'code' => 'SLT-002',
                'name' => 'Speech Therapy Session',
                'description' => 'Individual speech therapy session',
                'department_id' => $slt->id,
                'base_price' => 2000.00,
                'sha_covered' => true,
                'sha_price' => 500.00,
                'ncpwd_covered' => true,
                'ncpwd_price' => 1000.00,
                'is_recurring' => true,
                'duration_minutes' => 40,
                'category' => 'Therapy',
                'is_active' => true,
            ],
            [
                'code' => 'PSY-001',
                'name' => 'Psychological Assessment',
                'description' => 'Comprehensive psychological evaluation',
                'department_id' => $psy->id,
                'base_price' => 4000.00,
                'sha_covered' => false,
                'ncpwd_covered' => true,
                'ncpwd_price' => 2000.00,
                'requires_assessment' => true,
                'duration_minutes' => 90,
                'category' => 'Assessment',
                'is_active' => true,
            ],
            [
                'code' => 'PSY-002',
                'name' => 'Counseling Session',
                'description' => 'Individual counseling session',
                'department_id' => $psy->id,
                'base_price' => 1500.00,
                'sha_covered' => false,
                'ncpwd_covered' => true,
                'ncpwd_price' => 750.00,
                'is_recurring' => true,
                'duration_minutes' => 50,
                'category' => 'Counseling',
                'is_active' => true,
            ],
            [
                'code' => 'EDU-001',
                'name' => 'Educational Assessment',
                'description' => 'Comprehensive educational assessment and placement',
                'department_id' => $edu->id,
                'base_price' => 3500.00,
                'sha_covered' => false,
                'ncpwd_covered' => true,
                'ncpwd_price' => 1750.00,
                'requires_assessment' => true,
                'duration_minutes' => 120,
                'category' => 'Assessment',
                'is_active' => true,
            ],
            [
                'code' => 'AUD-001',
                'name' => 'Audiological Assessment',
                'description' => 'Comprehensive hearing assessment',
                'department_id' => $aud->id,
                'base_price' => 2500.00,
                'sha_covered' => true,
                'sha_price' => 600.00,
                'ncpwd_covered' => true,
                'ncpwd_price' => 1250.00,
                'requires_assessment' => true,
                'duration_minutes' => 45,
                'category' => 'Assessment',
                'is_active' => true,
            ],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }

        $this->command->info('10 services seeded successfully!');
    }
}