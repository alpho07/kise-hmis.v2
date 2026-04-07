<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Maps every application role to the correct Filament Shield permissions.
 *
 * Run after  `php artisan shield:generate --all --panel=admin`
 * Run with:  `php artisan db:seed --class=ShieldPermissionSeeder`
 *
 * Permission naming convention (Shield-generated):
 *   view_any_{resource}   – access the list/index page
 *   view_{resource}       – view a single record
 *   create_{resource}     – create new records
 *   update_{resource}     – edit existing records
 *   delete_{resource}     – soft-delete
 *   delete_any_{resource} – bulk delete
 *   restore_{resource}    – restore soft-deleted
 *   force_delete_{resource}
 *   page_{PageName}       – access a custom Filament page
 *   widget_{WidgetName}   – see a dashboard widget
 */
class ShieldPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Resource permission sets (DRY helpers) ─────────────────────────────
        $crud = fn(string $r) => ["view_any_{$r}", "view_{$r}", "create_{$r}", "update_{$r}"];
        $ro   = fn(string $r) => ["view_any_{$r}", "view_{$r}"];
        $roc  = fn(string $r) => ["view_any_{$r}", "view_{$r}", "create_{$r}"];

        // ── Common read-only client/visit (nearly every role needs this) ───────
        $baseClientVisit = [
            ...$ro('client'),
            ...$ro('visit'),
            'page_ClientProfileHub',
        ];

        // ──────────────────────────────────────────────────────────────────────
        // ROLE → PERMISSION MAP
        // ──────────────────────────────────────────────────────────────────────
        $rolePermissions = [

            // ── Receptionist ──────────────────────────────────────────────────
            // Registers clients, opens visits, hands off to triage queue
            'receptionist' => [
                ...$crud('client'),
                ...$roc('visit'),
                ...$crud('reception'),           // ReceptionQueueResource
                ...$ro('intake::queue'),          // read-only peek at next stage
                ...$ro('triage::queue'),          // read-only peek at next stage
                'widget_WalkInQueueWidget',
            ],

            // ── Triage Nurse ──────────────────────────────────────────────────
            // Records vitals, clears or escalates clients in triage queue
            'triage_nurse' => [
                ...$baseClientVisit,
                ...$crud('triage::queue'),
                ...$crud('triage'),
                'widget_WalkInQueueWidget',
            ],

            // ── Intake Officer ────────────────────────────────────────────────
            // Completes clinical intake assessment, selects services, sets payment pathway
            'intake_officer' => [
                ...$ro('client'),
                'update_client',                 // update NCPWD/SHA numbers found at intake
                ...$ro('visit'),
                ...$ro('intake::queue'),
                ...$crud('intake::assessment'),
                ...$ro('service'),
                'page_ClientProfileHub',
                'page_IntakeAssessmentEditor',
            ],

            // ── Billing Officer ───────────────────────────────────────────────
            // Reviews sponsor invoices, processes insurance claims, approves billing
            'billing_officer' => [
                ...$baseClientVisit,
                ...$crud('billing'),             // BillingResource (sponsor invoices)
                ...$ro('payment'),
                ...$crud('insurance::claim'),
                ...$ro('insurance::batch::invoice'),
                ...$crud('insurance::provider'),
                ...$ro('service'),
                'widget_VisitPipelineWidget',
            ],

            // ── Cashier ───────────────────────────────────────────────────────
            // Collects cash / M-PESA payments at the cashier window
            'cashier' => [
                ...$baseClientVisit,
                ...$crud('cashier::queue'),
                ...$crud('payment'),
                ...$ro('billing'),               // read invoices to know how much to collect
            ],

            // ── Service Provider ──────────────────────────────────────────────
            // Delivers services to clients in the service queue
            'service_provider' => [
                ...$baseClientVisit,
                ...$crud('service::queue'),
                ...$ro('service::point::dashboard'),
                ...$crud('appointment'),         // book follow-ups
                ...$ro('dynamic::assessment'),   // read assessment forms
                'page_SpecialistHub',
                'widget_ServiceAvailabilityWidget',
                'widget_TodayAppointmentsWidget',
            ],

            // ── Queue Manager ─────────────────────────────────────────────────
            // Monitors all queues; does not modify clinical data
            'queue_manager' => [
                ...$baseClientVisit,
                ...$ro('reception'),
                ...$ro('triage::queue'),
                ...$ro('intake::queue'),
                ...$ro('cashier::queue'),
                ...$ro('service::queue'),
                ...$ro('appointment'),
                'page_AppointmentsHubPage',
                'widget_VisitPipelineWidget',
                'widget_WalkInQueueWidget',
                'widget_TodayAppointmentsWidget',
            ],

            // ── Customer Care ─────────────────────────────────────────────────
            // Manages appointments, checks queue status, assists clients at reception
            'customer_care' => [
                ...$crud('client'),
                ...$ro('visit'),
                ...$crud('appointment'),
                ...$ro('service::queue'),
                ...$ro('intake::queue'),
                ...$ro('triage::queue'),
                'page_AppointmentsHubPage',
                'page_ClientProfileHub',
                'widget_TodayAppointmentsWidget',
                'widget_WalkInQueueWidget',
            ],

            // ── Branch Manager ────────────────────────────────────────────────
            // Oversees all operations in their branch; no system-level settings
            'branch_manager' => [
                ...$crud('client'),
                ...$ro('visit'),
                ...$ro('reception'),
                ...$ro('triage::queue'),
                ...$ro('intake::queue'),
                ...$ro('intake::assessment'),
                ...$ro('cashier::queue'),
                ...$ro('service::queue'),
                ...$ro('billing'),
                ...$ro('payment'),
                ...$crud('service'),
                ...$crud('service::availability'),
                ...$crud('appointment'),
                ...$ro('dynamic::assessment'),
                ...$ro('department'),
                ...$ro('insurance::provider'),
                ...$ro('insurance::claim'),
                'page_AppointmentsHubPage',
                'page_ClientProfileHub',
                'widget_VisitPipelineWidget',
                'widget_WalkInQueueWidget',
                'widget_ServiceAvailabilityWidget',
                'widget_TodayAppointmentsWidget',
            ],

            // ── Admin ─────────────────────────────────────────────────────────
            // Full branch-scoped access; can manage users, roles, and settings
            'admin' => [
                // Clients & visits
                ...$crud('client'),
                ...$crud('visit'),
                // All workflow stages
                ...$crud('reception'),
                ...$crud('triage::queue'),
                ...$crud('triage'),
                ...$crud('intake::queue'),
                ...$crud('intake::assessment'),
                ...$crud('billing'),
                ...$crud('cashier::queue'),
                ...$crud('payment'),
                ...$crud('service::queue'),
                // Services & availability
                ...$crud('service'),
                ...$crud('service::availability'),
                ...$ro('service::point::dashboard'),
                // Insurance
                ...$crud('insurance::provider'),
                ...$crud('insurance::claim'),
                ...$crud('insurance::batch::invoice'),
                // Appointments
                ...$crud('appointment'),
                // Assessment forms
                ...$crud('dynamic::assessment'),
                // Admin data
                ...$crud('user'),
                ...$ro('role'),
                ...$ro('branch'),
                ...$ro('department'),
                ...$ro('county'),
                ...$ro('sub::county'),
                ...$ro('ward'),
                // Pages & widgets
                'page_AppointmentsHubPage',
                'page_ClientProfileHub',
                'page_IntakeAssessmentEditor',
                'page_SpecialistHub',
                'widget_VisitPipelineWidget',
                'widget_WalkInQueueWidget',
                'widget_ServiceAvailabilityWidget',
                'widget_TodayAppointmentsWidget',
            ],
        ];

        // ── Apply permissions to each role ─────────────────────────────────────
        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                $this->command->warn("Role [{$roleName}] not found — skipping.");
                continue;
            }

            // Filter to only permissions that actually exist in the DB
            $existing = Permission::whereIn('name', array_unique($permissionNames))
                ->pluck('name', 'id');

            $missing = array_diff(array_unique($permissionNames), $existing->values()->all());
            if (!empty($missing)) {
                $this->command->warn("  [{$roleName}] Missing permissions (not yet generated): " . implode(', ', $missing));
            }

            // Sync — replaces existing permission set entirely
            $role->syncPermissions($existing->keys()->all());

            $this->command->info("✓ [{$roleName}] synced {$existing->count()} permissions.");
        }

        // ── super_admin gets every permission (enforced by Shield gate intercept)
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
            $this->command->info('✓ [super_admin] synced ALL ' . Permission::count() . ' permissions.');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->command->info('Permission cache cleared.');
    }
}
