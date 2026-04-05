<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\County;
use App\Models\Department;
use App\Models\Service;
use App\Models\SubCounty;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🌱 ========================================');
        $this->command->info('🌱 KISE HMIS DATABASE SEEDING');
        $this->command->info('🌱 ========================================');
        $this->command->info('');

        // PHASE 1: Location Data
        $this->command->info('📍 PHASE 1: Seeding Location Data...');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->seedCounties();
        $this->seedSubCounties();
        $this->seedWards();
        $this->command->info('');

        // PHASE 2: Organization Structure
        $this->command->info('🏢 PHASE 2: Seeding Organization Structure...');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->seedBranches();
        $this->seedDepartments();
        $this->command->info('');

        // PHASE 3: Users & Roles
        $this->command->info('👥 PHASE 3: Seeding Users & Roles...');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->seedRolesAndPermissions();
        $this->seedUsers();
        $this->command->info('');

        // PHASE 4: Services
        $this->command->info('💼 PHASE 4: Seeding Services...');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->seedServices();
        $this->command->info('');

        // PHASE 4b: Insurance Providers & Service Catalog
        $this->command->info('🏥 PHASE 4b: Seeding Insurance Providers & Service Catalog...');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->call(InsuranceProviderSeeder::class);
        $this->call(ServiceCatalogSeeder::class);
        $this->command->info('');

        // PHASE 5: System Settings
        $this->command->info('⚙️  PHASE 5: Seeding System Settings...');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->seedSystemSettings();
        $this->command->info('');

        // Summary
        $this->command->info('');
        $this->command->info('✅ ========================================');
        $this->command->info('✅ DATABASE SEEDING COMPLETED SUCCESSFULLY!');
        $this->command->info('✅ ========================================');
        $this->command->info('');
        
        $this->displayLoginCredentials();
        
        $this->command->info('');
        $this->command->info('📊 Database Summary:');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->displayDatabaseStats();
        $this->command->info('');
    }

    /**
     * Seed all 47 Kenya counties
     */
    protected function seedCounties(): void
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

        $this->command->info('✓ 47 counties seeded');
    }

    /**
     * Seed Nairobi sub-counties
     */
    protected function seedSubCounties(): void
    {
        $nairobi = County::where('code', '047')->first();

        if (!$nairobi) {
            $this->command->error('Nairobi county not found');
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

        $this->command->info('✓ 17 Nairobi sub-counties seeded');
    }

    /**
     * Seed Kasarani wards
     */
    protected function seedWards(): void
    {
        $kasarani = SubCounty::where('code', '047-07')->first();

        if (!$kasarani) {
            $this->command->error('Kasarani sub-county not found');
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

        $this->command->info('✓ 5 Kasarani wards seeded');
    }

    /**
     * Seed branches
     */
    protected function seedBranches(): void
    {
        $nairobi = County::where('code', '047')->first();
        $kasarani = SubCounty::where('code', '047-07')->first();
        $makueni = County::where('code', '017')->first();

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
                'operating_days' => [1, 2, 3, 4, 5],
                'max_daily_clients' => 150,
                'settings' => [
                    'allow_walk_ins' => true,
                    'require_referral' => false,
                    'enable_queue_sms' => true,
                    'default_language' => 'en',
                    'currency' => 'KES',
                ],
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
                'operating_days' => [1, 2, 3, 4, 5],
                'max_daily_clients' => 50,
                'settings' => [
                    'allow_walk_ins' => true,
                    'require_referral' => false,
                ],
            ],
            [
                'code' => 'MK-OUT',
                'name' => 'Makueni Outreach Program',
                'type' => 'outreach',
                'county_id' => $makueni?->id,
                'is_active' => true,
                'operating_days' => [1, 3, 5],
                'max_daily_clients' => 30,
                'settings' => [
                    'allow_walk_ins' => true,
                    'mobile_clinic' => true,
                ],
            ],
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }

        $this->command->info('✓ 3 branches seeded');
    }

    /**
     * Seed departments
     */
    protected function seedDepartments(): void
    {
        $mainBranch = Branch::where('code', 'KS')->first();

        if (!$mainBranch) {
            $this->command->error('Main branch not found');
            return;
        }

        $departments = [
            [
                'branch_id' => $mainBranch->id,
                'code' => 'PHYS',
                'name' => 'Physiotherapy',
                'description' => 'Physical therapy and rehabilitation services',
                'has_queue' => true,
                'queue_capacity' => 50,
                'sla_target_minutes' => 30,
                'location' => 'Ground Floor, Room 101',
                'phone' => '+254712345001',
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'code' => 'OT',
                'name' => 'Occupational Therapy',
                'description' => 'Occupational therapy and life skills training',
                'has_queue' => true,
                'queue_capacity' => 40,
                'sla_target_minutes' => 45,
                'location' => 'First Floor, Room 201',
                'phone' => '+254712345002',
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'code' => 'SLT',
                'name' => 'Speech & Language Therapy',
                'description' => 'Speech, language, and communication therapy',
                'has_queue' => true,
                'queue_capacity' => 35,
                'sla_target_minutes' => 40,
                'location' => 'First Floor, Room 203',
                'phone' => '+254712345003',
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'code' => 'PSY',
                'name' => 'Psychological Services',
                'description' => 'Psychological assessment and counseling',
                'has_queue' => true,
                'queue_capacity' => 30,
                'sla_target_minutes' => 60,
                'location' => 'Second Floor, Room 301',
                'phone' => '+254712345004',
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'code' => 'EDU',
                'name' => 'Educational Assessment',
                'description' => 'Educational assessment and placement services',
                'has_queue' => true,
                'queue_capacity' => 25,
                'sla_target_minutes' => 90,
                'location' => 'Second Floor, Room 302',
                'phone' => '+254712345005',
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'code' => 'AUD',
                'name' => 'Audiology',
                'description' => 'Hearing assessment and audiological services',
                'has_queue' => true,
                'queue_capacity' => 30,
                'sla_target_minutes' => 30,
                'location' => 'Ground Floor, Room 105',
                'phone' => '+254712345006',
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'code' => 'VISION',
                'name' => 'Vision Services',
                'description' => 'Vision assessment and low vision services',
                'has_queue' => true,
                'queue_capacity' => 25,
                'sla_target_minutes' => 30,
                'location' => 'Ground Floor, Room 106',
                'phone' => '+254712345007',
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'code' => 'AT',
                'name' => 'Assistive Technology',
                'description' => 'Assistive devices assessment and provision',
                'has_queue' => false,
                'location' => 'Ground Floor, AT Lab',
                'phone' => '+254712345008',
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'code' => 'GNC',
                'name' => 'Guidance & Counseling',
                'description' => 'Guidance and counseling services',
                'has_queue' => true,
                'queue_capacity' => 20,
                'sla_target_minutes' => 45,
                'location' => 'Second Floor, Room 305',
                'phone' => '+254712345009',
                'is_active' => true,
            ],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }

        $this->command->info('✓ 9 departments seeded');
    }

    /**
     * Seed roles and permissions
     */
    protected function seedRolesAndPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Roles
        $roles = [
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'branch_manager' => 'Branch Manager',
            'receptionist' => 'Receptionist',
            'triage_nurse' => 'Triage Nurse',
            'intake_officer' => 'Intake Officer',
            'billing_officer' => 'Billing Officer',
            'service_provider' => 'Service Provider',
            'queue_manager' => 'Queue Manager',
            'customer_care' => 'Customer Care',
        ];

        foreach ($roles as $name => $description) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
        }

        // Create Permissions
        $permissions = [
            'view_clients',
            'create_clients',
            'edit_clients',
            'delete_clients',
            'view_visits',
            'create_visits',
            'edit_visits',
            'delete_visits',
            'view_triage',
            'create_triage',
            'edit_triage',
            'view_intake',
            'create_intake',
            'edit_intake',
            'view_services',
            'create_services',
            'edit_services',
            'view_invoices',
            'create_invoices',
            'approve_invoices',
            'view_payments',
            'create_payments',
            'refund_payments',
            'view_queues',
            'manage_queues',
            'view_reports',
            'view_analytics',
            'manage_settings',
            'manage_users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign permissions to roles
        $superAdmin = Role::where('name', 'super_admin')->first();
        $superAdmin->givePermissionTo(Permission::all());

        $admin = Role::where('name', 'admin')->first();
        $admin->givePermissionTo([
            'view_clients', 'create_clients', 'edit_clients',
            'view_visits', 'create_visits', 'edit_visits',
            'view_services', 'create_services', 'edit_services',
            'view_invoices', 'create_invoices', 'approve_invoices',
            'view_payments', 'create_payments',
            'view_queues', 'manage_queues',
            'view_reports', 'view_analytics',
            'manage_users',
        ]);

        $receptionist = Role::where('name', 'receptionist')->first();
        $receptionist->givePermissionTo([
            'view_clients', 'create_clients', 'edit_clients',
            'view_visits', 'create_visits',
            'view_queues',
        ]);

        $triageNurse = Role::where('name', 'triage_nurse')->first();
        $triageNurse->givePermissionTo([
            'view_clients',
            'view_visits',
            'view_triage', 'create_triage', 'edit_triage',
            'view_queues',
        ]);

        $intakeOfficer = Role::where('name', 'intake_officer')->first();
        $intakeOfficer->givePermissionTo([
            'view_clients', 'edit_clients',
            'view_visits',
            'view_intake', 'create_intake', 'edit_intake',
            'view_services',
        ]);

        $billingOfficer = Role::where('name', 'billing_officer')->first();
        $billingOfficer->givePermissionTo([
            'view_clients',
            'view_visits',
            'view_services',
            'view_invoices', 'create_invoices', 'approve_invoices',
            'view_payments', 'create_payments',
        ]);

        $this->command->info('✓ Roles and permissions seeded');
    }

    /**
     * Seed users
     */
    protected function seedUsers(): void
    {
        $mainBranch = Branch::where('code', 'KS')->first();

        if (!$mainBranch) {
            $this->command->error('Main branch not found');
            return;
        }

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
            $user->assignRole($role);
        }

        // Set branch manager
        $mainBranch->update([
            'manager_id' => User::where('email', 'manager@kise.ac.ke')->first()->id
        ]);

        $this->command->info('✓ 5 users seeded');
    }

    /**
     * Seed services
     */
    protected function seedServices(): void
    {
        $phys = Department::where('code', 'PHYS')->first();
        $ot = Department::where('code', 'OT')->first();
        $slt = Department::where('code', 'SLT')->first();
        $psy = Department::where('code', 'PSY')->first();
        $edu = Department::where('code', 'EDU')->first();
        $aud = Department::where('code', 'AUD')->first();

        if (!$phys) {
            $this->command->error('Departments not found');
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
        ];

        foreach ($services as $service) {
            Service::create($service);
        }

        $this->command->info('✓ 9 services seeded');
    }

    /**
     * Seed system settings
     */
    protected function seedSystemSettings(): void
    {
        $settings = [
            [
                'key' => 'app_name',
                'value' => 'KISE HMIS',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Application Name',
                'description' => 'Name of the application displayed across the system',
                'is_public' => true,
                'is_editable' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'app_timezone',
                'value' => 'Africa/Nairobi',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Timezone',
                'description' => 'Default timezone for the application',
                'is_public' => true,
                'is_editable' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'currency',
                'value' => 'KES',
                'type' => 'string',
                'group' => 'billing',
                'label' => 'Currency',
                'description' => 'Default currency code',
                'is_public' => true,
                'is_editable' => false,
                'sort_order' => 1,
            ],
            [
                'key' => 'tax_rate',
                'value' => '0.16',
                'type' => 'string',
                'group' => 'billing',
                'label' => 'Tax Rate',
                'description' => 'Default tax rate (VAT)',
                'is_public' => true,
                'is_editable' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'sms_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'label' => 'SMS Notifications Enabled',
                'description' => 'Enable/disable SMS notifications',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'email_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'label' => 'Email Notifications Enabled',
                'description' => 'Enable/disable email notifications',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'auto_uci_generation',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'clients',
                'label' => 'Auto Generate UCI',
                'description' => 'Automatically generate Unique Client Identifiers',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'require_triage',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'workflow',
                'label' => 'Triage Required',
                'description' => 'Make triage mandatory for all new visits',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'queue_sms_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'queue',
                'label' => 'Queue SMS Notifications',
                'description' => 'Send SMS when client is called from queue',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'max_daily_clients_default',
                'value' => '100',
                'type' => 'integer',
                'group' => 'general',
                'label' => 'Default Max Daily Clients',
                'description' => 'Default maximum clients per day for new branches',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 10,
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::create($setting);
        }

        $this->command->info('✓ 10 system settings seeded');
    }

    /**
     * Display login credentials
     */
    protected function displayLoginCredentials(): void
    {
        $this->command->info('🔐 LOGIN CREDENTIALS:');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Super Admin', 'admin@kise.ac.ke', 'admin123'],
                ['Branch Manager', 'manager@kise.ac.ke', 'manager123'],
                ['Receptionist', 'receptionist@kise.ac.ke', 'reception123'],
                ['Triage Nurse', 'triage@kise.ac.ke', 'triage123'],
                ['Intake Officer', 'intake@kise.ac.ke', 'intake123'],
            ]
        );
    }

    /**
     * Display database statistics
     */
    protected function displayDatabaseStats(): void
    {
        $stats = [
            ['Counties', County::count()],
            ['Sub-Counties', SubCounty::count()],
            ['Wards', Ward::count()],
            ['Branches', Branch::count()],
            ['Departments', Department::count()],
            ['Services', Service::count()],
            ['Users', User::count()],
            ['Roles', Role::count()],
            ['Permissions', Permission::count()],
            ['System Settings', SystemSetting::count()],
        ];

        $this->command->table(['Table', 'Records'], $stats);
    }
}