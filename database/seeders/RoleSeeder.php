<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'branch_manager' => 'Branch Manager',
            'receptionist' => 'Receptionist',
            'triage_nurse' => 'Triage Nurse',
            'intake_officer' => 'Intake Officer',
            'billing_admin' => 'Billing Admin',
            'cashier' => 'Cashier',
            'service_provider' => 'Service Provider',
            'queue_manager' => 'Queue Manager',
        ];

        foreach ($roles as $name => $description) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }

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

        $billingOfficer = Role::where('name', 'billing_admin')->first();
        $billingOfficer->givePermissionTo([
            'view_clients',
            'view_visits',
            'view_services',
            'view_invoices', 'create_invoices', 'approve_invoices',
            'view_payments', 'create_payments',
        ]);

        $this->command->info('Roles and permissions seeded successfully!');
    }
}