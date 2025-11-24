<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::where('code', 'KS')->first();

        if (!$mainBranch) {
            $this->command->error('Main branch not found. Please run BranchSeeder first.');
            return;
        }

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $receptionistRole = Role::firstOrCreate(['name' => 'receptionist']);
        $triageNurseRole = Role::firstOrCreate(['name' => 'triage_nurse']);
        $intakeOfficerRole = Role::firstOrCreate(['name' => 'intake_officer']);

        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'admin@kise.ac.ke',
                'password' => Hash::make('admin123'),
                'branch_id' => $mainBranch->id,
                'employee_id' => 'EMP-001',
                'designation' => 'System Administrator',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'super_admin',
            ],
            [
                'name' => 'Branch Manager',
                'email' => 'manager@kise.ac.ke',
                'password' => Hash::make('manager123'),
                'branch_id' => $mainBranch->id,
                'employee_id' => 'EMP-002',
                'designation' => 'Branch Manager',
                'phone' => '+254712000001',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'admin',
            ],
            [
                'name' => 'Receptionist One',
                'email' => 'receptionist@kise.ac.ke',
                'password' => Hash::make('reception123'),
                'branch_id' => $mainBranch->id,
                'employee_id' => 'EMP-003',
                'designation' => 'Receptionist',
                'phone' => '+254712000002',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'receptionist',
            ],
            [
                'name' => 'Triage Nurse',
                'email' => 'triage@kise.ac.ke',
                'password' => Hash::make('triage123'),
                'branch_id' => $mainBranch->id,
                'employee_id' => 'EMP-004',
                'designation' => 'Registered Nurse',
                'phone' => '+254712000003',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'triage_nurse',
            ],
            [
                'name' => 'Intake Officer',
                'email' => 'intake@kise.ac.ke',
                'password' => Hash::make('intake123'),
                'branch_id' => $mainBranch->id,
                'employee_id' => 'EMP-005',
                'designation' => 'Intake Assessment Officer',
                'phone' => '+254712000004',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'intake_officer',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::create($userData);

            switch ($role) {
                case 'super_admin':
                    $user->assignRole($superAdminRole);
                    break;
                case 'admin':
                    $user->assignRole($adminRole);
                    break;
                case 'receptionist':
                    $user->assignRole($receptionistRole);
                    break;
                case 'triage_nurse':
                    $user->assignRole($triageNurseRole);
                    break;
                case 'intake_officer':
                    $user->assignRole($intakeOfficerRole);
                    break;
            }
        }

        $mainBranch->update(['manager_id' => User::where('email', 'manager@kise.ac.ke')->first()->id]);

        $this->command->info('5 users seeded successfully!');
        $this->command->info('');
        $this->command->info('Login Credentials:');
        $this->command->info('Super Admin: admin@kise.ac.ke / admin123');
        $this->command->info('Manager: manager@kise.ac.ke / manager123');
        $this->command->info('Receptionist: receptionist@kise.ac.ke / reception123');
        $this->command->info('Triage Nurse: triage@kise.ac.ke / triage123');
        $this->command->info('Intake Officer: intake@kise.ac.ke / intake123');
    }
}