# Appointments, Sessions & Specialist Hub Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build 8 connected sub-systems — appointment scheduling, session tracking, return-visit auto-routing, reception hub, specialist hub enhancements, service availability, insurance pricing extensibility, and a notification stub — wired end-to-end into the existing KISE HMIS visit workflow.

**Architecture:** New Filament pages/resources for appointments and service availability; targeted edits to existing resources (Triage routing, ServiceQueue post-service, SpecialistHub forms+sessions); new Service ↔ AssessmentFormSchema pivot; NotificationService mock; 9 migrations in sequence.

**Tech Stack:** Laravel 12, Filament v3, Livewire 3, Spatie Laravel Permission (FilamentShield), Spatie ActivityLog, MySQL 8

---

## File Map

### New files
| File | Responsibility |
|---|---|
| `database/migrations/2026_04_05_000001_add_service_type_columns_to_services.php` | Add service_type, requires_sessions, default_session_count to services |
| `database/migrations/2026_04_05_000002_alter_insurance_providers_type_enum.php` | Data-migrate + ALTER type ENUM values |
| `database/migrations/2026_04_05_000003_service_insurance_prices_unique_constraint.php` | Deduplicate rows + add UNIQUE(service_id, insurance_provider_id) |
| `database/migrations/2026_04_05_000004_create_service_form_schemas_table.php` | Pivot table for Service ↔ AssessmentFormSchema |
| `database/migrations/2026_04_05_000005_alter_visits_triage_path_to_enum.php` | Normalize triage_path values + ALTER to ENUM |
| `database/migrations/2026_04_05_000006_add_branch_id_to_appointments.php` | Add branch_id FK to appointments |
| `database/migrations/2026_04_05_000007_add_columns_to_service_sessions.php` | Add progress_status, next_session_date, session_sequence |
| `database/migrations/2026_04_05_000008_create_service_availability_table.php` | New service_availability table |
| `database/migrations/2026_04_05_000009_create_notification_logs_table.php` | New notification_logs table |
| `database/seeders/InsuranceProviderSeeder.php` | Upsert SHA, NCPWD, E-citizen providers |
| `database/seeders/ServiceCatalogSeeder.php` | Upsert 16 services from PDF price list |
| `app/Models/ServiceAvailability.php` | ServiceAvailability Eloquent model |
| `app/Models/NotificationLog.php` | NotificationLog Eloquent model |
| `app/Observers/ServiceSessionObserver.php` | Auto-set session_sequence + auto-complete booking |
| `app/Services/NotificationService.php` | Mock SMS gateway — always logs status=mock |
| `app/Filament/Pages/AppointmentsHubPage.php` | Reception Appointments Hub page |
| `app/Filament/Widgets/TodayAppointmentsWidget.php` | Today's appointments table widget |
| `app/Filament/Widgets/WalkInQueueWidget.php` | Walk-in count widget |
| `app/Filament/Widgets/ServiceAvailabilityWidget.php` | Department availability status widget |
| `app/Filament/Resources/AppointmentResource.php` | Full CRUD for appointments (department-scoped) |
| `app/Filament/Resources/AppointmentResource/Pages/ListAppointments.php` | Tabbed list (Today/Upcoming/Past/All) |
| `app/Filament/Resources/AppointmentResource/Pages/CreateAppointment.php` | Appointment create page |
| `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php` | Appointment edit page |
| `app/Filament/Resources/ServiceAvailabilityResource.php` | Daily availability management |
| `app/Filament/Resources/ServiceAvailabilityResource/Pages/ListServiceAvailabilities.php` | List page |
| `resources/views/filament/pages/appointments-hub.blade.php` | Blade template for AppointmentsHubPage |

### Modified files
| File | Change |
|---|---|
| `app/Models/Service.php` | Fix broken scopes (category_type→category), remove heuristic assessmentForms accessor, add assessmentForms belongsToMany, add new columns to $fillable/$casts |
| `app/Models/ServiceInsurancePrice.php` | Remove effective_from/to from $fillable/$casts; delete scopeEffective(); keep is_active |
| `app/Models/InsuranceProvider.php` | Rename scopePublic→scopeGovernmentScheme, add scopeEcitizen |
| `app/Models/ServiceSession.php` | Replace entire $fillable with correct column list |
| `app/Models/Appointment.php` | Add branch_id, insurance_provider_id to $fillable/$casts, add insuranceProvider relationship |
| `app/Providers/AppServiceProvider.php` | Register ServiceSessionObserver |
| `app/Filament/Resources/TriageResource/Pages/CreateTriage.php` | Update determineNextStage() to route on is_appointment/triage_path |
| `app/Filament/Resources/ServiceQueueResource.php` | Add "Served Today" tab; extend complete_service action with follow-up booking modal |
| `app/Filament/Pages/SpecialistHub.php` | Replace heuristic getServiceFormsProperty with pivot; add session tracking panel property; update mount eager-loads |
| `resources/views/filament/pages/specialist-hub.blade.php` | Add current services card list with form buttons and sessions panels |
| `database/seeders/DatabaseSeeder.php` | Add InsuranceProviderSeeder, ServiceCatalogSeeder; add customer_care to roles list |

---

## Task 1: Migrations — services table new columns (Phase 0a)

**Files:**
- Create: `database/migrations/2026_04_05_000001_add_service_type_columns_to_services.php`

- [ ] **Step 1: Write the migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->enum('service_type', ['assessment', 'therapy', 'assistive_technology', 'consultation'])
                  ->default('assessment')
                  ->after('category');
            $table->boolean('requires_sessions')->default(false)->after('service_type');
            $table->unsignedTinyInteger('default_session_count')->nullable()->after('requires_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'requires_sessions', 'default_session_count']);
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate --path=database/migrations/2026_04_05_000001_add_service_type_columns_to_services.php
```
Expected: `Migrated: 2026_04_05_000001...`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_05_000001_add_service_type_columns_to_services.php
git commit -m "feat: add service_type, requires_sessions, default_session_count to services"
```

---

## Task 2: Migrations — insurance_providers type ENUM (Phase 0b)

**Files:**
- Create: `database/migrations/2026_04_05_000002_alter_insurance_providers_type_enum.php`

- [ ] **Step 1: Write the migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Step 1: Normalize old values to new ones
        DB::update("UPDATE insurance_providers SET type = 'government_scheme' WHERE type IN ('public', 'government')");

        // Step 2: ALTER column (MySQL requires re-specifying NOT NULL + DEFAULT)
        DB::statement("ALTER TABLE insurance_providers MODIFY COLUMN type ENUM('government_scheme', 'ecitizen', 'private') NOT NULL DEFAULT 'private'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE insurance_providers MODIFY COLUMN type ENUM('public', 'private', 'government') NOT NULL DEFAULT 'private'");
        DB::update("UPDATE insurance_providers SET type = 'public' WHERE type = 'government_scheme'");
        DB::update("UPDATE insurance_providers SET type = 'public' WHERE type = 'ecitizen'");
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate --path=database/migrations/2026_04_05_000002_alter_insurance_providers_type_enum.php
```

- [ ] **Step 3: Update InsuranceProvider model scopes** in `app/Models/InsuranceProvider.php`

Replace `scopePublic()` with two scopes (do NOT remove `scopePrivate`):
```php
// REMOVE this:
public function scopePublic($query)
{
    return $query->where('type', 'public');
}

// ADD these:
public function scopeGovernmentScheme($query)
{
    return $query->where('type', 'government_scheme');
}

public function scopeEcitizen($query)
{
    return $query->where('type', 'ecitizen');
}
```

- [ ] **Step 4: Search the entire codebase for `InsuranceProvider::public()` or `->public()` and replace with `->governmentScheme()`**

```bash
grep -rn "InsuranceProvider::public\|->public()" app/ --include="*.php"
```

Replace each occurrence found.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_05_000002_alter_insurance_providers_type_enum.php app/Models/InsuranceProvider.php
git commit -m "feat: expand insurance_providers type ENUM, rename scopePublic to scopeGovernmentScheme"
```

---

## Task 3: Migrations — service_insurance_prices unique constraint (Phase 0c)

**Files:**
- Create: `database/migrations/2026_04_05_000003_service_insurance_prices_unique_constraint.php`
- Modify: `app/Models/ServiceInsurancePrice.php`

- [ ] **Step 1: Write the migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Remove duplicate rows keeping the most recently updated one
        DB::statement("
            DELETE sip1 FROM service_insurance_prices sip1
            INNER JOIN service_insurance_prices sip2
            ON sip1.service_id = sip2.service_id
               AND sip1.insurance_provider_id = sip2.insurance_provider_id
               AND sip1.updated_at < sip2.updated_at
        ");

        Schema::table('service_insurance_prices', function (Blueprint $table) {
            $table->unique(['service_id', 'insurance_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('service_insurance_prices', function (Blueprint $table) {
            $table->dropUnique(['service_id', 'insurance_provider_id']);
        });
    }
};
```

- [ ] **Step 2: Update `ServiceInsurancePrice` model** — remove `effective_from`, `effective_to` from `$fillable` and `$casts`, and delete `scopeEffective()`. Keep `is_active`, `scopeActive()`.

New `$fillable`:
```php
protected $fillable = [
    'service_id',
    'insurance_provider_id',
    'covered_amount',
    'client_copay',
    'coverage_percentage',
    'is_fully_covered',
    'requires_preauthorization',
    'preauthorization_code',
    'is_active',
    'notes',
];
```

New `$casts` (remove `effective_from` and `effective_to` entries):
```php
protected $casts = [
    'covered_amount' => 'decimal:2',
    'client_copay' => 'decimal:2',
    'coverage_percentage' => 'decimal:2',
    'is_fully_covered' => 'boolean',
    'requires_preauthorization' => 'boolean',
    'is_active' => 'boolean',
];
```

Delete the entire `scopeEffective()` method.

- [ ] **Step 3: Update `Service::getPriceForInsurance()`** — it calls `->effective()` which no longer exists. Replace with `->active()`:

In `app/Models/Service.php`, find `getPriceForInsurance()` and change:
```php
// OLD
->effective()
// NEW
->active()
```

Also update `isCoveredBy()` the same way.

- [ ] **Step 4: Run migration**

```bash
php artisan migrate --path=database/migrations/2026_04_05_000003_service_insurance_prices_unique_constraint.php
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_05_000003_service_insurance_prices_unique_constraint.php app/Models/ServiceInsurancePrice.php app/Models/Service.php
git commit -m "feat: add unique constraint to service_insurance_prices; remove date-range scope"
```

---

## Task 4: Migration — service_form_schemas pivot (Phase 0d)

**Files:**
- Create: `database/migrations/2026_04_05_000004_create_service_form_schemas_table.php`
- Modify: `app/Models/Service.php`

- [ ] **Step 1: Write the migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_form_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_form_schema_id')->constrained('assessment_form_schemas')->cascadeOnDelete();
            $table->unique(['service_id', 'assessment_form_schema_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_form_schemas');
    }
};
```

- [ ] **Step 2: Add `assessmentForms` relationship to `Service` model**

Add to `app/Models/Service.php` imports:
```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

Add relationship method:
```php
public function assessmentForms(): BelongsToMany
{
    return $this->belongsToMany(
        AssessmentFormSchema::class,
        'service_form_schemas'
    );
}
```

- [ ] **Step 3: Remove the heuristic `getAssessmentFormsAttribute()` method** from `app/Models/Service.php` — delete the entire method (lines ~212–248 in the original file). It is replaced by the pivot relationship above.

- [ ] **Step 4: Fix broken scopes** in `app/Models/Service.php` — `scopeChild`, `scopeAdult`, `scopeBoth` reference the non-existent column `category_type`. Fix them to use `category`:

```php
public function scopeChild($query)
{
    return $query->where('category', self::CATEGORY_CHILD);
}

public function scopeAdult($query)
{
    return $query->where('category', self::CATEGORY_ADULT);
}

public function scopeBoth($query)
{
    return $query->where('category', self::CATEGORY_BOTH);
}
```

Also fix `getActivitylogOptions()` which logs `category_type` — change to `category` and `service_type`.

- [ ] **Step 5: Add new columns to `Service::$fillable` and `$casts`**

In `$fillable`, add after `'notes'`:
```php
'service_type',
'requires_sessions',
'default_session_count',
```

In `$casts`, add:
```php
'requires_sessions' => 'boolean',
'default_session_count' => 'integer',
```

- [ ] **Step 6: Run migration**

```bash
php artisan migrate --path=database/migrations/2026_04_05_000004_create_service_form_schemas_table.php
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_04_05_000004_create_service_form_schemas_table.php app/Models/Service.php
git commit -m "feat: add service_form_schemas pivot; fix Service scopes; add assessmentForms relationship"
```

---

## Task 5: Migration — visits.triage_path ENUM (Phase 0e)

**Files:**
- Create: `database/migrations/2026_04_05_000005_alter_visits_triage_path_to_enum.php`

- [ ] **Step 1: Write the migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Normalize any non-standard values before ALTER
        DB::update("
            UPDATE visits
            SET triage_path = 'standard'
            WHERE triage_path NOT IN ('standard', 'returning', 'medical_veto', 'crisis')
               OR triage_path IS NULL
        ");

        DB::statement("
            ALTER TABLE visits
            MODIFY COLUMN triage_path
            ENUM('standard', 'returning', 'medical_veto', 'crisis') NULL
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE visits MODIFY COLUMN triage_path VARCHAR(255) NULL");
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate --path=database/migrations/2026_04_05_000005_alter_visits_triage_path_to_enum.php
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_05_000005_alter_visits_triage_path_to_enum.php
git commit -m "feat: normalize and ALTER visits.triage_path to ENUM"
```

---

## Task 6: Migrations — appointments + service_sessions + availability + notifications (Phases 0f–0i)

**Files:**
- Create: 4 migration files
- Modify: `app/Models/ServiceSession.php`, `app/Models/Appointment.php`

- [ ] **Step 1: Write migration 0f — appointments branch_id**

```php
// database/migrations/2026_04_05_000006_add_branch_id_to_appointments.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->nullOnDelete()->constrained();
            $table->foreignId('insurance_provider_id')->nullable()->after('branch_id')->nullOnDelete()->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['insurance_provider_id']);
            $table->dropColumn(['branch_id', 'insurance_provider_id']);
        });
    }
};
```

- [ ] **Step 2: Write migration 0g — service_sessions new columns**

```php
// database/migrations/2026_04_05_000007_add_columns_to_service_sessions.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_sessions', function (Blueprint $table) {
            $table->enum('progress_status', ['improving', 'stable', 'regressing', 'completed'])->nullable()->after('attendance');
            $table->date('next_session_date')->nullable()->after('progress_status');
            $table->unsignedTinyInteger('session_sequence')->nullable()->after('next_session_date');
        });
    }

    public function down(): void
    {
        Schema::table('service_sessions', function (Blueprint $table) {
            $table->dropColumn(['progress_status', 'next_session_date', 'session_sequence']);
        });
    }
};
```

- [ ] **Step 3: Write migration 0h — service_availability table**

```php
// database/migrations/2026_04_05_000008_create_service_availability_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_available')->default(true);
            $table->enum('reason_code', ['staff_absent', 'equipment_unavailable', 'public_holiday', 'training', 'other'])->nullable();
            $table->string('comment', 500)->nullable();
            $table->foreignId('updated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_availability');
    }
};
```

- [ ] **Step 4: Write migration 0i — notification_logs table**

```php
// database/migrations/2026_04_05_000009_create_notification_logs_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_phone', 20);
            $table->enum('message_type', ['appointment_reminder', 'check_in_confirmation', 'disruption_alert', 'follow_up_booking']);
            $table->text('message_body');
            $table->unsignedBigInteger('appointment_id')->nullable(); // plain integer, no FK — survives appointment deletion
            $table->enum('status', ['mock', 'queued', 'sent', 'failed'])->default('mock');
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
```

- [ ] **Step 5: Fix `ServiceSession::$fillable`** — replace the entire array:

```php
protected $fillable = [
    'visit_id',
    'service_booking_id',
    'service_id',
    'client_id',
    'provider_id',
    'department_id',
    'session_date',
    'start_time',
    'end_time',
    'duration_minutes',
    'status',
    'session_number',
    'session_goals',
    'activities_performed',
    'attendance',
    'client_response',
    'observations',
    'recommendations',
    'homework_assigned',
    'cancellation_reason',
    'progress_status',
    'next_session_date',
    'session_sequence',
];
```

Remove `session_type`, `attendance_status`, `signed_in_by`, `baseline_assessment_data`, `intervention_plan` (columns that do not correspond to the actual DB schema). Also update `$casts` to remove `baseline_assessment_data` array cast and any casts for removed fields. Add: `'session_date' => 'date'`, `'session_sequence' => 'integer'`.

- [ ] **Step 6: Update `Appointment` model** — add `branch_id` and `insurance_provider_id` to `$fillable` and add relationship:

```php
// Add to $fillable array:
'branch_id',
'insurance_provider_id',

// Add relationship method:
public function insuranceProvider(): BelongsTo
{
    return $this->belongsTo(InsuranceProvider::class);
}
```

Do NOT add `BelongsToBranch` trait — branch_id is set from department, not from auth user.

- [ ] **Step 7: Run all four migrations**

```bash
php artisan migrate --path=database/migrations/2026_04_05_000006_add_branch_id_to_appointments.php
php artisan migrate --path=database/migrations/2026_04_05_000007_add_columns_to_service_sessions.php
php artisan migrate --path=database/migrations/2026_04_05_000008_create_service_availability_table.php
php artisan migrate --path=database/migrations/2026_04_05_000009_create_notification_logs_table.php
```

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_04_05_000006* database/migrations/2026_04_05_000007* database/migrations/2026_04_05_000008* database/migrations/2026_04_05_000009* app/Models/ServiceSession.php app/Models/Appointment.php
git commit -m "feat: migrations 0f-0i (appointments branch_id, session columns, availability, notification_logs) + model updates"
```

---

## Task 7: Seeders — insurance providers + service catalog (Phase 1)

**Files:**
- Create: `database/seeders/InsuranceProviderSeeder.php`
- Create: `database/seeders/ServiceCatalogSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create `InsuranceProviderSeeder`**

```php
<?php
namespace Database\Seeders;

use App\Models\InsuranceProvider;
use Illuminate\Database\Seeder;

class InsuranceProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['code' => 'SHA',      'name' => 'Social Health Authority',      'short_name' => 'SHA',     'type' => 'government_scheme'],
            ['code' => 'NCPWD',    'name' => 'NCPWD',                        'short_name' => 'NCPWD',   'type' => 'government_scheme'],
            ['code' => 'ECITIZEN', 'name' => 'E-Citizen',                    'short_name' => 'E-Citizen','type' => 'ecitizen'],
        ];

        foreach ($providers as $provider) {
            InsuranceProvider::updateOrCreate(
                ['code' => $provider['code']],
                array_merge($provider, ['is_active' => true, 'sort_order' => 1])
            );
        }
    }
}
```

- [ ] **Step 2: Create `ServiceCatalogSeeder`**

This seeder upserts the 16 services from the PDF price list. It looks up department by name — departments must already exist (seeded by DatabaseSeeder before this runs).

```php
<?php
namespace Database\Seeders;

use App\Models\Department;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Department name → department_id map (departments seeded by DatabaseSeeder)
        $depts = Department::pluck('id', 'name');

        $services = [
            // Children services
            ['code' => 'COT-001',  'name' => 'Children OT',                'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'child', 'dept' => 'Occupational Therapy'],
            ['code' => 'CPT-001',  'name' => 'Children PT',                'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'child', 'dept' => 'Physiotherapy'],
            ['code' => 'CHY-001',  'name' => 'Children Hydrotherapy',      'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'child', 'dept' => 'Physiotherapy'],
            ['code' => 'CFM-001',  'name' => 'Children Fine Motor',        'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'child', 'dept' => 'Occupational Therapy'],
            ['code' => 'CSI-001',  'name' => 'Sensory Integration',        'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'child', 'dept' => 'Occupational Therapy'],
            ['code' => 'CPL-001',  'name' => 'Play Therapy',               'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'child', 'dept' => 'Psychology'],
            ['code' => 'CST-001',  'name' => 'Children Speech Therapy',    'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'child', 'dept' => 'Speech & Language Therapy'],
            // Adult services
            ['code' => 'AAC-001',  'name' => 'Adult Assessment Consultation','base_price' => 1000,'service_type' => 'consultation',         'requires_sessions' => false, 'default_session_count' => null, 'category' => 'adult', 'dept' => 'Occupational Therapy'],
            ['code' => 'AOT-001',  'name' => 'Adult OT',                   'base_price' => 1000, 'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'adult', 'dept' => 'Occupational Therapy'],
            ['code' => 'APT-001',  'name' => 'Adult PT',                   'base_price' => 1000, 'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'adult', 'dept' => 'Physiotherapy'],
            ['code' => 'AHY-001',  'name' => 'Adult Hydrotherapy',         'base_price' => 1500, 'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'adult', 'dept' => 'Physiotherapy'],
            ['code' => 'AST-001',  'name' => 'Adult Speech Therapy',       'base_price' => 1000, 'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12, 'category' => 'adult', 'dept' => 'Speech & Language Therapy'],
            ['code' => 'ASA-001',  'name' => 'Adult Speech Assessment',    'base_price' => 2000, 'service_type' => 'assessment',           'requires_sessions' => false, 'default_session_count' => null, 'category' => 'adult', 'dept' => 'Speech & Language Therapy'],
            ['code' => 'AUD-001',  'name' => 'Auditory for Adults',        'base_price' => 1000, 'service_type' => 'assessment',           'requires_sessions' => false, 'default_session_count' => null, 'category' => 'adult', 'dept' => 'Audiology'],
            ['code' => 'EAR-001',  'name' => 'Ear Molds (per ear)',        'base_price' => 2000, 'service_type' => 'assistive_technology', 'requires_sessions' => false, 'default_session_count' => null, 'category' => 'both',  'dept' => 'Audiology'],
            ['code' => 'NUT-001',  'name' => 'Nutrition Review',           'base_price' => 500,  'service_type' => 'consultation',         'requires_sessions' => false, 'default_session_count' => null, 'category' => 'both',  'dept' => 'General/Nutrition Clinic'],
        ];

        foreach ($services as $svc) {
            $deptId = $depts[$svc['dept']] ?? null;
            Service::updateOrCreate(
                ['code' => $svc['code']],
                [
                    'name'                  => $svc['name'],
                    'base_price'            => $svc['base_price'],
                    'service_type'          => $svc['service_type'],
                    'requires_sessions'     => $svc['requires_sessions'],
                    'default_session_count' => $svc['default_session_count'],
                    'category'              => $svc['category'],
                    'department_id'         => $deptId,
                    'is_active'             => true,
                    'duration_minutes'      => 60,
                ]
            );
        }
    }
}
```

**Note:** If department names don't match exactly, check `departments.name` values in the DB and adjust the `dept` strings above.

- [ ] **Step 3: Register seeders in `DatabaseSeeder.php`**

In the `run()` method, add before the closing bracket:
```php
$this->call(InsuranceProviderSeeder::class);
$this->call(ServiceCatalogSeeder::class);
```

Also add `customer_care` to the roles array in the `RoleSeeder` call section (if roles are hardcoded in `DatabaseSeeder`) or add it to `RoleSeeder.php` directly. Search for the roles list:

```bash
grep -n "customer_care\|receptionist\|service_provider" database/seeders/RoleSeeder.php
```

Add `'customer_care'` to that list.

- [ ] **Step 4: Run seeders**

```bash
php artisan db:seed --class=InsuranceProviderSeeder
php artisan db:seed --class=ServiceCatalogSeeder
```

- [ ] **Step 5: Verify**

```bash
php artisan tinker --execute="echo App\Models\InsuranceProvider::count() . ' providers, ' . App\Models\Service::count() . ' services';"
```

Expected: at least 3 providers and 16+ services.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/InsuranceProviderSeeder.php database/seeders/ServiceCatalogSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat: add InsuranceProviderSeeder and ServiceCatalogSeeder (16 services from PDF)"
```

---

## Task 8: ServiceResource enhancements (Phase 2)

**Files:**
- Modify: `app/Filament/Resources/ServiceResource.php`

- [ ] **Step 1: Read the existing ServiceResource** to understand current form/table structure

```bash
cat app/Filament/Resources/ServiceResource.php
```

- [ ] **Step 2: Add insurance pricing repeater to the form schema**

Inside the form's `schema([...])`, add a new Section after the existing fields:

```php
Forms\Components\Section::make('Insurance Pricing')
    ->description('Set how much each insurer covers and what the client pays.')
    ->schema([
        Forms\Components\Repeater::make('insurancePrices')
            ->relationship('insurancePrices')
            ->schema([
                Forms\Components\Select::make('insurance_provider_id')
                    ->label('Insurance Provider')
                    ->options(
                        \App\Models\InsuranceProvider::active()->ordered()
                            ->get()
                            ->groupBy(fn($p) => ucwords(str_replace('_', ' ', $p->type)))
                            ->map(fn($group) => $group->pluck('name', 'id'))
                            ->toArray()
                    )
                    ->required()
                    ->searchable(),

                Forms\Components\TextInput::make('covered_amount')
                    ->label('Insurer Pays (KES)')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('client_copay')
                    ->label('Client Pays (KES)')
                    ->numeric()
                    ->required(),

                Forms\Components\Toggle::make('is_active')
                    ->default(true),

                Forms\Components\Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(3)
            ->addActionLabel('Add Insurance Price')
            ->collapsible(),
    ])
    ->collapsible(),
```

- [ ] **Step 3: Add session configuration panel**

```php
Forms\Components\Section::make('Session Configuration')
    ->schema([
        Forms\Components\Select::make('service_type')
            ->options([
                'assessment'          => 'Assessment',
                'therapy'             => 'Therapy',
                'assistive_technology'=> 'Assistive Technology',
                'consultation'        => 'Consultation',
            ])
            ->required()
            ->default('assessment'),

        Forms\Components\Toggle::make('requires_sessions')
            ->label('Requires Multiple Sessions')
            ->live()
            ->helperText('Enable for therapy services where a course of sessions is prescribed.'),

        Forms\Components\TextInput::make('default_session_count')
            ->label('Default Session Count')
            ->numeric()
            ->minValue(1)
            ->maxValue(100)
            ->visible(fn (\Filament\Forms\Get $get) => $get('requires_sessions'))
            ->required(fn (\Filament\Forms\Get $get) => $get('requires_sessions')),
    ])
    ->columns(3)
    ->collapsible(),
```

- [ ] **Step 4: Add assessment forms multi-select panel**

```php
Forms\Components\Section::make('Linked Assessment Forms')
    ->schema([
        Forms\Components\Select::make('assessmentForms')
            ->label('Forms')
            ->multiple()
            ->relationship('assessmentForms', 'name')
            ->preload()
            ->searchable()
            ->helperText('Forms that specialists fill out for this service. Link both adult and paediatric variants if applicable.'),
    ])
    ->collapsible(),
```

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/ServiceResource.php
git commit -m "feat: add insurance pricing, session config, and assessment forms panels to ServiceResource"
```

---

## Task 9: ServiceInsurancePrice lookup in IntakeAssessmentEditor (Phase 3)

**Files:**
- Modify: `app/Filament/Pages/IntakeAssessmentEditor.php`

- [ ] **Step 1: Read the `finalize()` method** in IntakeAssessmentEditor

```bash
grep -n "finalize\|match.*paymentMethod\|sha_price\|ncpwd_price\|base_price\|sponsor\|client_amount\|covered" app/Filament/Pages/IntakeAssessmentEditor.php | head -40
```

- [ ] **Step 2: Locate the hardcoded payment ratio block** — it will look like a `match($paymentMethod)` or similar that calculates `sponsor_amount` / `client_amount` from SHA/NCPWD prices. Replace with a `ServiceInsurancePrice` lookup:

```php
// OLD pattern (roughly):
$sponsorAmount = match($paymentMethod) {
    'sha'   => $service->sha_price,
    'ncpwd' => $service->ncpwd_price,
    default => 0,
};
$clientAmount = $service->base_price - $sponsorAmount;

// NEW:
use App\Models\ServiceInsurancePrice;

$insuranceProviderId = $sr['insurance_provider_id'] ?? null;
$price = $insuranceProviderId
    ? ServiceInsurancePrice::where('service_id', $service->id)
          ->where('insurance_provider_id', $insuranceProviderId)
          ->active()
          ->first()
    : null;

$clientAmount = $price ? $price->client_copay   : $service->base_price;
$sponsorAmount = $price ? $price->covered_amount : 0;
```

Adapt variable names to match the existing code exactly.

- [ ] **Step 3: Run the existing test suite to confirm nothing broke**

```bash
php artisan test --filter=IntakeAssessment
```

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/IntakeAssessmentEditor.php
git commit -m "feat: replace hardcoded insurance ratios with ServiceInsurancePrice lookup in finalize()"
```

---

## Task 10: NotificationService stub + models (Phase 4)

**Files:**
- Create: `app/Models/NotificationLog.php`
- Create: `app/Services/NotificationService.php`

- [ ] **Step 1: Create `NotificationLog` model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'recipient_phone',
        'message_type',
        'message_body',
        'appointment_id',
        'status',
        'staff_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    const UPDATED_AT = null;

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
```

- [ ] **Step 2: Create `NotificationService`**

```php
<?php
namespace App\Services;

use App\Models\Client;
use App\Models\NotificationLog;

class NotificationService
{
    /**
     * Send (or mock) an SMS notification to a client.
     * Always writes a NotificationLog with status='mock'.
     * Celcom gateway stub is below — uncomment + set CELCOM_API_KEY and CELCOM_ENDPOINT in .env to enable.
     */
    public function send(Client $client, string $type, array $data, ?int $appointmentId = null): NotificationLog
    {
        $body = $this->buildMessage($type, $client, $data);

        $log = NotificationLog::create([
            'client_id'      => $client->id,
            'recipient_phone'=> $client->phone_primary ?? $client->phone ?? '',
            'message_type'   => $type,
            'message_body'   => $body,
            'appointment_id' => $appointmentId,
            'status'         => 'mock',
            'staff_id'       => auth()->id(),
            'sent_at'        => now(),
        ]);

        // --- CELCOM GATEWAY STUB (uncomment to enable) ---
        // $apiKey   = config('services.celcom.api_key');   // CELCOM_API_KEY in .env
        // $endpoint = config('services.celcom.endpoint');  // CELCOM_ENDPOINT in .env
        // try {
        //     $response = \Illuminate\Support\Facades\Http::withToken($apiKey)->post($endpoint, [
        //         'to'      => $log->recipient_phone,
        //         'message' => $body,
        //     ]);
        //     $log->update(['status' => $response->successful() ? 'sent' : 'failed']);
        // } catch (\Throwable $e) {
        //     $log->update(['status' => 'failed']);
        // }
        // --- END STUB ---

        return $log;
    }

    private function buildMessage(string $type, Client $client, array $data): string
    {
        $name    = $client->first_name;
        $service = $data['service'] ?? 'your service';
        $date    = isset($data['date']) ? \Carbon\Carbon::parse($data['date'])->format('d M Y') : '';
        $time    = $data['time'] ?? '';
        $reason  = $data['reason'] ?? 'operational reasons';

        return match ($type) {
            'appointment_reminder'  => "Dear {$name}, your appointment at KISE on {$date} at {$time} for {$service} is confirmed. Reply STOP to opt out.",
            'check_in_confirmation' => "Dear {$name}, you have been checked in at KISE for {$service}. Please proceed to Triage.",
            'disruption_alert'      => "Dear {$name}, {$service} at KISE is unavailable on {$date} ({$reason}). We will contact you to reschedule.",
            'follow_up_booking'     => "Dear {$name}, your next {$service} appointment at KISE has been booked for {$date} at {$time}.",
            default                 => "Dear {$name}, you have a message from KISE regarding {$service}.",
        };
    }
}
```

- [ ] **Step 3: Register in `AppServiceProvider`** (optional — or resolve from container directly via `app(NotificationService::class)`)

- [ ] **Step 4: Commit**

```bash
git add app/Models/NotificationLog.php app/Services/NotificationService.php
git commit -m "feat: add NotificationLog model and NotificationService mock SMS stub"
```

---

## Task 11: ServiceSession observer (Phase 5)

**Files:**
- Create: `app/Observers/ServiceSessionObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Create the observer**

```php
<?php
namespace App\Observers;

use App\Models\ServiceSession;

class ServiceSessionObserver
{
    public function created(ServiceSession $session): void
    {
        $count = ServiceSession::where('service_booking_id', $session->service_booking_id)->count();

        // Set session_sequence (1-based)
        $session->updateQuietly(['session_sequence' => $count]);

        // Auto-complete booking if prescribed count reached
        $booking = $session->serviceBooking()->with('service')->first();
        if (
            $booking &&
            $booking->service?->default_session_count &&
            $count >= $booking->service->default_session_count
        ) {
            $booking->update(['service_status' => 'completed']);
        }
    }
}
```

- [ ] **Step 2: Register observer in `AppServiceProvider::boot()`**

```php
use App\Models\ServiceSession;
use App\Observers\ServiceSessionObserver;

ServiceSession::observe(ServiceSessionObserver::class);
```

- [ ] **Step 3: Write a feature test**

Create `tests/Feature/ServiceSessionObserverTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceSessionObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_sequence_is_set_on_create(): void
    {
        // Arrange: create a service booking with a session-based service
        $service = Service::factory()->create(['requires_sessions' => true, 'default_session_count' => 3]);
        $booking = ServiceBooking::factory()->create(['service_id' => $service->id]);

        // Act: create first session
        $session = ServiceSession::create([
            'service_booking_id' => $booking->id,
            'service_id'         => $service->id,
            'session_date'       => today(),
        ]);

        // Assert
        $this->assertEquals(1, $session->fresh()->session_sequence);
        $this->assertEquals('scheduled', $booking->fresh()->service_status);
    }

    public function test_booking_auto_completes_when_session_count_reached(): void
    {
        $service = Service::factory()->create(['requires_sessions' => true, 'default_session_count' => 2]);
        $booking = ServiceBooking::factory()->create(['service_id' => $service->id, 'service_status' => 'in_progress']);

        ServiceSession::create(['service_booking_id' => $booking->id, 'service_id' => $service->id, 'session_date' => today()]);
        ServiceSession::create(['service_booking_id' => $booking->id, 'service_id' => $service->id, 'session_date' => today()]);

        $this->assertEquals('completed', $booking->fresh()->service_status);
    }
}
```

- [ ] **Step 4: Run the test**

```bash
php artisan test --filter=ServiceSessionObserverTest
```

If factories don't exist, create minimal ones with `php artisan make:factory ServiceFactory` etc., or skip the test and verify manually via tinker.

- [ ] **Step 5: Commit**

```bash
git add app/Observers/ServiceSessionObserver.php app/Providers/AppServiceProvider.php tests/Feature/ServiceSessionObserverTest.php
git commit -m "feat: ServiceSessionObserver auto-sets session_sequence and auto-completes booking"
```

---

## Task 12: Return visit auto-routing in triage (Phase 6)

**Files:**
- Modify: `app/Filament/Resources/TriageResource/Pages/CreateTriage.php`

- [ ] **Step 1: Locate `determineNextStage()`** — currently at lines ~176–208

- [ ] **Step 2: Replace the routing logic**

Current (lines 188–207):
```php
$clientType = $visit->client?->client_type ?? 'new';

if ($clientType === 'returning') {
    ...
    return 'billing';
}
...
return 'intake';
```

Replace with:
```php
// Appointment check-ins and explicitly-marked returning visits skip intake entirely.
// Routing is system-driven — no nurse input required.
if ($visit->is_appointment || $visit->triage_path === 'returning') {
    Log::info('Routing to Billing', [
        'visit_id'    => $visit->id,
        'reason'      => $visit->is_appointment ? 'Appointment check-in' : 'Returning client triage_path',
        'is_appointment' => $visit->is_appointment,
        'triage_path' => $visit->triage_path,
    ]);
    return 'billing';
}

Log::info('Routing to Intake', [
    'visit_id'    => $visit->id,
    'reason'      => 'New or walk-in client — requires intake assessment',
    'triage_path' => $visit->triage_path,
]);
return 'intake';
```

Keep the crisis/medical_hold checks above this block — they must still short-circuit.

- [ ] **Step 3: Write a feature test**

```php
// tests/Feature/TriageRoutingTest.php
public function test_appointment_visit_routes_to_billing_not_intake(): void
{
    $visit = Visit::factory()->create([
        'is_appointment' => true,
        'triage_path'    => 'returning',
        'current_stage'  => 'triage',
    ]);
    $triage = Triage::factory()->create(['visit_id' => $visit->id, 'triage_status' => 'cleared']);

    // Simulate CreateTriage::determineNextStage
    $page = new \App\Filament\Resources\TriageResource\Pages\CreateTriage();
    $stage = $this->invokeMethod($page, 'determineNextStage', [$triage, $visit]);

    $this->assertEquals('billing', $stage);
}

public function test_new_walk_in_routes_to_intake(): void
{
    $visit = Visit::factory()->create(['is_appointment' => false, 'triage_path' => 'standard']);
    $triage = Triage::factory()->create(['visit_id' => $visit->id, 'triage_status' => 'cleared']);

    $page = new \App\Filament\Resources\TriageResource\Pages\CreateTriage();
    $stage = $this->invokeMethod($page, 'determineNextStage', [$triage, $visit]);

    $this->assertEquals('intake', $stage);
}
```

Add `invokeMethod` helper to `TestCase.php`:
```php
protected function invokeMethod($object, string $methodName, array $parameters = [])
{
    $reflection = new \ReflectionClass($object);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
}
```

- [ ] **Step 4: Run test**

```bash
php artisan test --filter=TriageRoutingTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/TriageResource/Pages/CreateTriage.php tests/Feature/TriageRoutingTest.php tests/TestCase.php
git commit -m "feat: auto-route appointment/returning visits to billing at triage completion"
```

---

## Task 13: New models — ServiceAvailability (Phase 8 foundation)

**Files:**
- Create: `app/Models/ServiceAvailability.php`

- [ ] **Step 1: Create the model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAvailability extends Model
{
    protected $table = 'service_availability';

    protected $fillable = [
        'branch_id',
        'department_id',
        'date',
        'is_available',
        'reason_code',
        'comment',
        'updated_by',
    ];

    protected $casts = [
        'date'         => 'date',
        'is_available' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public static function isDepartmentAvailable(int $departmentId, ?\Carbon\Carbon $date = null): bool
    {
        $date ??= today();
        $record = static::where('department_id', $departmentId)->whereDate('date', $date)->first();
        return $record ? $record->is_available : true; // default = available if no record
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/ServiceAvailability.php
git commit -m "feat: add ServiceAvailability model"
```

---

## Task 14: Reception Appointments Hub — Page + Widgets (Phase 7)

**Files:**
- Create: `app/Filament/Pages/AppointmentsHubPage.php`
- Create: `app/Filament/Widgets/TodayAppointmentsWidget.php`
- Create: `app/Filament/Widgets/WalkInQueueWidget.php`
- Create: `app/Filament/Widgets/ServiceAvailabilityWidget.php`
- Create: `resources/views/filament/pages/appointments-hub.blade.php`

- [ ] **Step 1: Create `AppointmentsHubPage`**

```php
<?php
namespace App\Filament\Pages;

use App\Filament\Widgets\ServiceAvailabilityWidget;
use App\Filament\Widgets\TodayAppointmentsWidget;
use App\Filament\Widgets\WalkInQueueWidget;
use Filament\Pages\Page;

class AppointmentsHubPage extends Page
{
    protected static string $view = 'filament.pages.appointments-hub';

    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Appointments Hub';
    protected static ?string $navigationGroup = 'Client Management';
    protected static ?int    $navigationSort  = 3;

    protected static ?string $title = 'Appointments Hub';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole(['receptionist', 'admin', 'super_admin']);
    }

    public function getWidgets(): array
    {
        return [
            ServiceAvailabilityWidget::class,
            WalkInQueueWidget::class,
            TodayAppointmentsWidget::class,
        ];
    }
}
```

- [ ] **Step 2: Create `TodayAppointmentsWidget`**

```php
<?php
namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\ServiceAvailability;
use App\Models\Visit;
use App\Services\NotificationService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Notifications\Notification;

class TodayAppointmentsWidget extends BaseWidget
{
    protected static ?string $heading = "Today's Appointments";
    protected static ?string $pollingInterval = '30s';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appointment::today()
                    ->whereHas('department', fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
                    ->with(['client', 'service', 'provider', 'department'])
                    ->orderBy('appointment_time')
            )
            ->columns([
                Tables\Columns\TextColumn::make('appointment_time')
                    ->label('Time')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service'),

                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Provider')
                    ->default('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'scheduled',
                        'primary' => 'confirmed',
                        'success' => 'checked_in',
                        'danger'  => 'no_show',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('check_in')
                    ->label('Check In')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->disabled(fn (Appointment $record) =>
                        !in_array($record->status, ['scheduled', 'confirmed']) ||
                        !ServiceAvailability::isDepartmentAvailable($record->department_id)
                    )
                    ->tooltip(fn (Appointment $record) =>
                        !ServiceAvailability::isDepartmentAvailable($record->department_id)
                            ? 'Department unavailable today'
                            : 'Check in client'
                    )
                    ->requiresConfirmation()
                    ->modalHeading(fn (Appointment $record) => "Check In: {$record->client->full_name}")
                    ->modalDescription(fn (Appointment $record) => "Service: {$record->service->name}. This will create a new visit and send to triage.")
                    ->action(function (Appointment $record) {
                        // 1. Create the Visit
                        $visit = Visit::create([
                            'client_id'      => $record->client_id,
                            'branch_id'      => auth()->user()->branch_id,
                            'is_appointment' => true,
                            'visit_type'     => 'appointment',
                            'triage_path'    => 'returning',
                            'check_in_time'  => now(),
                            'checked_in_by'  => auth()->id(),
                        ]);
                        // Visit number is auto-generated in model boot if applicable

                        // 2. Move to triage stage
                        $visit->moveToStage('triage');

                        // 3. Link appointment to visit
                        $record->update([
                            'status'        => 'checked_in',
                            'checked_in_at' => now(),
                            'checked_in_by' => auth()->id(),
                            'visit_id'      => $visit->id,
                            'branch_id'     => auth()->user()->branch_id,
                        ]);

                        // 4. Send mock SMS
                        app(NotificationService::class)->send(
                            $record->client,
                            'check_in_confirmation',
                            ['service' => $record->service->name, 'time' => now()->format('H:i')],
                            $record->id
                        );

                        Notification::make()
                            ->success()
                            ->title('Client Checked In')
                            ->body("{$record->client->full_name} sent to triage queue.")
                            ->send();
                    }),

                Tables\Actions\Action::make('no_show')
                    ->label('No Show')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Appointment $record) => in_array($record->status, ['scheduled', 'confirmed']))
                    ->requiresConfirmation()
                    ->action(fn (Appointment $record) => $record->markNoShow()),
            ]);
    }
}
```

- [ ] **Step 3: Create `WalkInQueueWidget`**

```php
<?php
namespace App\Filament\Widgets;

use App\Models\Visit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WalkInQueueWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected int|string|array $columnSpan = 2;

    protected function getStats(): array
    {
        $count = Visit::where('current_stage', 'reception')
            ->where('is_appointment', false)
            ->where('branch_id', auth()->user()->branch_id)
            ->count();

        return [
            Stat::make('Walk-Ins at Reception', $count)
                ->description('Awaiting reception processing')
                ->descriptionIcon('heroicon-m-users')
                ->color($count > 10 ? 'danger' : ($count > 5 ? 'warning' : 'success'))
                ->url(url('/admin/visits')),
        ];
    }
}
```

- [ ] **Step 4: Create `ServiceAvailabilityWidget`**

```php
<?php
namespace App\Filament\Widgets;

use App\Models\ServiceAvailability;
use App\Models\Department;
use Filament\Widgets\Widget;

class ServiceAvailabilityWidget extends Widget
{
    protected static string $view = 'filament.widgets.service-availability-widget';
    protected static ?string $pollingInterval = '30s';
    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $branchId = auth()->user()->branch_id;

        $departments = Department::where('branch_id', $branchId)->get();
        $records     = ServiceAvailability::today()
            ->forBranch($branchId)
            ->with('department')
            ->get()
            ->keyBy('department_id');

        $statuses = $departments->map(function (Department $dept) use ($records) {
            $record = $records->get($dept->id);
            return [
                'department' => $dept,
                'available'  => $record ? $record->is_available : true,
                'reason'     => $record?->reason_code,
                'comment'    => $record?->comment,
            ];
        });

        return ['statuses' => $statuses];
    }
}
```

Create blade template `resources/views/filament/widgets/service-availability-widget.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section heading="Today's Service Availability">
        <div class="flex flex-wrap gap-2">
            @foreach($statuses as $status)
                <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium
                    {{ $status['available'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    @if($status['available'])
                        <x-heroicon-m-check-circle class="w-4 h-4"/>
                    @else
                        <x-heroicon-m-x-circle class="w-4 h-4"/>
                    @endif
                    {{ $status['department']->name }}
                    @if(!$status['available'] && $status['reason'])
                        — {{ ucwords(str_replace('_', ' ', $status['reason'])) }}
                    @endif
                </span>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

- [ ] **Step 5: Create appointments-hub blade view**

`resources/views/filament/pages/appointments-hub.blade.php`:
```blade
<x-filament-panels::page>
    <x-filament-widgets::widgets
        :widgets="$this->getWidgets()"
        :columns="$this->getColumns()"
    />
</x-filament-panels::page>
```

- [ ] **Step 6: Test the page renders**

```bash
php artisan route:list | grep appointments-hub
# Then visit /admin/appointments-hub in browser as receptionist role
```

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages/AppointmentsHubPage.php app/Filament/Widgets/ resources/views/filament/pages/appointments-hub.blade.php resources/views/filament/widgets/service-availability-widget.blade.php
git commit -m "feat: Reception Appointments Hub page with today/walk-in/availability widgets"
```

---

## Task 15: Department AppointmentResource (Phase 8)

**Files:**
- Create: `app/Filament/Resources/AppointmentResource.php`
- Create: `app/Filament/Resources/AppointmentResource/Pages/ListAppointments.php`
- Create: `app/Filament/Resources/AppointmentResource/Pages/CreateAppointment.php`
- Create: `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php`

- [ ] **Step 1: Generate the resource scaffold**

```bash
php artisan make:filament-resource Appointment --generate
```

This creates the pages skeleton. We'll replace the content.

- [ ] **Step 2: Write `AppointmentResource.php`**

```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Department;
use App\Models\InsuranceProvider;
use App\Models\ServiceAvailability;
use App\Models\User;
use App\Services\NotificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;
    protected static ?string $navigationIcon  = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Appointments';
    protected static ?string $navigationGroup = 'Service Delivery';
    protected static ?int    $navigationSort  = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole(['service_provider', 'customer_care', 'admin', 'super_admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['client', 'service', 'provider', 'department']);

        // Department-scoped for service providers
        if (auth()->user()->hasRole('service_provider') && auth()->user()->department_id) {
            $query->where('department_id', auth()->user()->department_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Client & Service')
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label('Client')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) =>
                            Client::withoutGlobalScope('branch')
                                ->where(fn ($q) => $q
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name',  'like', "%{$search}%")
                                    ->orWhere('uci',        'like', "%{$search}%")
                                )
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => "{$c->uci} — {$c->full_name}"])
                                ->toArray()
                        )
                        ->getOptionLabelUsing(fn ($value) =>
                            Client::withoutGlobalScope('branch')->find($value)?->full_name
                        )
                        ->required(),

                    Forms\Components\Select::make('department_id')
                        ->label('Department')
                        ->options(Department::pluck('name', 'id'))
                        ->required()
                        ->live()
                        ->default(fn () => auth()->user()->department_id),

                    Forms\Components\Select::make('service_id')
                        ->label('Service')
                        ->options(fn (\Filament\Forms\Get $get) =>
                            $get('department_id')
                                ? \App\Models\Service::where('department_id', $get('department_id'))->active()->pluck('name', 'id')
                                : []
                        )
                        ->required()
                        ->searchable(),

                    Forms\Components\Select::make('provider_id')
                        ->label('Provider')
                        ->options(fn (\Filament\Forms\Get $get) =>
                            $get('department_id')
                                ? User::where('department_id', $get('department_id'))
                                      ->whereHas('roles', fn ($q) => $q->where('name', 'service_provider'))
                                      ->pluck('name', 'id')
                                : []
                        )
                        ->searchable(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Schedule')
                ->schema([
                    Forms\Components\DatePicker::make('appointment_date')
                        ->required()
                        ->minDate(today())
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                            if (!$state || !$get('department_id')) return;
                            $unavailable = !ServiceAvailability::isDepartmentAvailable(
                                $get('department_id'),
                                \Carbon\Carbon::parse($state)
                            );
                            if ($unavailable) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Department unavailable on this date')
                                    ->send();
                            }
                        }),

                    Forms\Components\TimePicker::make('appointment_time')
                        ->required()
                        ->seconds(false),

                    Forms\Components\TextInput::make('duration')
                        ->label('Duration (minutes)')
                        ->numeric()
                        ->default(60),

                    Forms\Components\Select::make('appointment_type')
                        ->options([
                            'follow_up'       => 'Follow-Up',
                            'review'          => 'Review',
                            'therapy_session' => 'Therapy Session',
                            'new_assessment'  => 'New Assessment',
                        ])
                        ->required(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Payment & Notes')
                ->schema([
                    Forms\Components\Select::make('insurance_provider_id')
                        ->label('Insurance Provider')
                        ->options(InsuranceProvider::active()->ordered()->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('send_sms')
                        ->label('Send SMS reminder to client')
                        ->default(true)
                        ->helperText('Logged as mock — no real SMS sent until gateway is configured.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('appointment_date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('appointment_time')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service'),

                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Provider')
                    ->default('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'scheduled',
                        'primary' => 'confirmed',
                        'success' => fn ($state) => in_array($state, ['checked_in', 'completed']),
                        'danger'  => fn ($state) => in_array($state, ['cancelled', 'no_show']),
                    ]),

                Tables\Columns\IconColumn::make('reminder_sent')
                    ->boolean()
                    ->label('SMS'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled', 'confirmed' => 'Confirmed',
                        'checked_in' => 'Checked In', 'cancelled' => 'Cancelled', 'no_show' => 'No Show',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->query(fn ($q) => $q->whereDate('appointment_date', today()))
                    ->label('Today'),

                Tables\Filters\Filter::make('upcoming')
                    ->query(fn ($q) => $q->where('appointment_date', '>=', today()))
                    ->label('Upcoming'),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Appointment $r) => $r->status === 'scheduled')
                    ->action(fn (Appointment $r) => $r->update(['status' => 'confirmed'])),

                Tables\Actions\Action::make('no_show')
                    ->label('No Show')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Appointment $r) => in_array($r->status, ['scheduled', 'confirmed']))
                    ->requiresConfirmation()
                    ->action(fn (Appointment $r) => $r->markNoShow()),

                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('appointment_date', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit'   => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 3: Write `Pages/ListAppointments.php`** with Today / Upcoming / Past tabs

```php
<?php
namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    public function getTabs(): array
    {
        return [
            'today'    => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereDate('appointment_date', today())),
            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('appointment_date', '>', today())
                    ->whereIn('status', ['scheduled', 'confirmed'])),
            'past'     => Tab::make('Past')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('appointment_date', '<', today())),
            'all'      => Tab::make('All'),
        ];
    }
}
```

- [ ] **Step 4: Write `Pages/CreateAppointment.php`** with SMS side-effect on save

```php
<?php
namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use App\Services\NotificationService;
use Filament\Resources\Pages\CreateRecord;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        // Populate branch_id from the department's branch
        if (!empty($data['department_id'])) {
            $dept = \App\Models\Department::find($data['department_id']);
            $data['branch_id'] = $dept?->branch_id;
        }
        return $data;
    }

    protected function afterCreate(): void
    {
        $appointment = $this->record;
        $sendSms = $this->data['send_sms'] ?? false;

        if ($sendSms) {
            app(NotificationService::class)->send(
                $appointment->client,
                'appointment_reminder',
                [
                    'service' => $appointment->service->name,
                    'date'    => $appointment->appointment_date->toDateString(),
                    'time'    => $appointment->appointment_time->format('H:i'),
                ],
                $appointment->id
            );
            $appointment->update(['reminder_sent' => true, 'reminder_sent_at' => now()]);
        }
    }
}
```

- [ ] **Step 5: Write `Pages/EditAppointment.php`** (standard)

```php
<?php
namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Resources\Pages\EditRecord;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/AppointmentResource.php app/Filament/Resources/AppointmentResource/
git commit -m "feat: AppointmentResource with tabbed list, cross-branch client search, and SMS reminder"
```

---

## Task 16: ServiceAvailabilityResource (Phase 9)

**Files:**
- Create: `app/Filament/Resources/ServiceAvailabilityResource.php`
- Create: `app/Filament/Resources/ServiceAvailabilityResource/Pages/ListServiceAvailabilities.php`

- [ ] **Step 1: Create `ServiceAvailabilityResource`**

```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ServiceAvailabilityResource\Pages;
use App\Models\ServiceAvailability;
use App\Models\Appointment;
use App\Services\NotificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ServiceAvailabilityResource extends Resource
{
    protected static ?string $model = ServiceAvailability::class;
    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Service Availability';
    protected static ?string $navigationGroup = 'System Settings';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole(['customer_care', 'admin', 'super_admin']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('department_id')
                ->relationship('department', 'name')
                ->required(),

            Forms\Components\DatePicker::make('date')
                ->required()
                ->default(today()),

            Forms\Components\Toggle::make('is_available')
                ->default(true)
                ->live()
                ->label('Available Today'),

            Forms\Components\Select::make('reason_code')
                ->options([
                    'staff_absent'           => 'Staff Absent',
                    'equipment_unavailable'  => 'Equipment Unavailable',
                    'public_holiday'         => 'Public Holiday',
                    'training'               => 'Training',
                    'other'                  => 'Other',
                ])
                ->visible(fn (\Filament\Forms\Get $get) => !$get('is_available'))
                ->required(fn (\Filament\Forms\Get $get) => !$get('is_available')),

            Forms\Components\Textarea::make('comment')
                ->visible(fn (\Filament\Forms\Get $get) => !$get('is_available'))
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('department.name')->label('Department')->searchable(),
                Tables\Columns\TextColumn::make('date')->date('d M Y')->sortable(),
                Tables\Columns\IconColumn::make('is_available')->boolean()->label('Available'),
                Tables\Columns\TextColumn::make('reason_code')
                    ->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),
                Tables\Columns\TextColumn::make('updatedBy.name')->label('Updated By'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d M H:i')->label('Last Updated'),
            ])
            ->filters([
                Tables\Filters\Filter::make('today')->query(fn ($q) => $q->whereDate('date', today()))->default(),
                Tables\Filters\TernaryFilter::make('is_available')->label('Availability'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (ServiceAvailability $record) {
                        if (!$record->is_available) {
                            static::notifyAffectedClients($record);
                        }
                    }),
            ]);
    }

    /**
     * After marking unavailable, collect affected appointments and send disruption SMS.
     */
    private static function notifyAffectedClients(ServiceAvailability $record): void
    {
        $appointments = Appointment::where('department_id', $record->department_id)
            ->whereDate('appointment_date', $record->date)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->with('client', 'service')
            ->get();

        if ($appointments->isEmpty()) return;

        $notifier = app(NotificationService::class);
        foreach ($appointments as $appt) {
            $notifier->send($appt->client, 'disruption_alert', [
                'service' => $appt->service->name,
                'date'    => $record->date->format('d M Y'),
                'reason'  => $record->reason_code ?? 'operational reasons',
            ], $appt->id);
        }

        Notification::make()
            ->warning()
            ->title('Disruption SMS Queued (Mock)')
            ->body($appointments->count() . ' clients notified (mock — check notification_logs).')
            ->send();
    }

    // NOTE: mutateFormDataBeforeCreate/Save are NOT static methods on the Resource class in Filament v3.
    // They must live on the Page class. See the CreateServiceAvailability page below.


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceAvailabilities::route('/'),
        ];
    }
}
```

- [ ] **Step 2: Create `Pages/ListServiceAvailabilities.php`**

```php
<?php
namespace App\Filament\Resources\ServiceAvailabilityResource\Pages;

use App\Filament\Resources\ServiceAvailabilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceAvailabilities extends ListRecords
{
    protected static string $resource = ServiceAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
```

- [ ] **Step 3: Create `Pages/CreateServiceAvailability.php`** — handles `updated_by` + `branch_id` population (cannot be done as static methods on the Resource class in Filament v3)

```php
<?php
namespace App\Filament\Resources\ServiceAvailabilityResource\Pages;

use App\Filament\Resources\ServiceAvailabilityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceAvailability extends CreateRecord
{
    protected static string $resource = ServiceAvailabilityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['updated_by'] = auth()->id();
        if (empty($data['branch_id'])) {
            $dept = \App\Models\Department::find($data['department_id']);
            $data['branch_id'] = $dept?->branch_id ?? auth()->user()->branch_id;
        }
        return $data;
    }
}
```

Also create `Pages/EditServiceAvailability.php`:

```php
<?php
namespace App\Filament\Resources\ServiceAvailabilityResource\Pages;

use App\Filament\Resources\ServiceAvailabilityResource;
use Filament\Resources\Pages\EditRecord;

class EditServiceAvailability extends EditRecord
{
    protected static string $resource = ServiceAvailabilityResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();
        return $data;
    }
}
```

Add `create` and `edit` routes to `ServiceAvailabilityResource::getPages()`:
```php
'create' => Pages\CreateServiceAvailability::route('/create'),
'edit'   => Pages\EditServiceAvailability::route('/{record}/edit'),
```

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/ServiceAvailabilityResource.php app/Filament/Resources/ServiceAvailabilityResource/
git commit -m "feat: ServiceAvailabilityResource for Customer Care daily updates + disruption SMS"
```

---

## Task 17: Post-service booking + "Served Today" tab (Phase 10)

**Files:**
- Modify: `app/Filament/Resources/ServiceQueueResource.php`

- [ ] **Step 1: Add "Book Follow-Up" action after `complete_service`**

Locate the `complete_service` action (around line 277). After the `Notification::make()->send()` call at the end of the `->action()` callback, add a second action that mounts after the first:

```php
->after(function ($livewire, QueueEntry $record) {
    // Mount the follow-up booking action after completion
    $livewire->mountAction('bookFollowUp', ['queue_entry_id' => $record->id]);
}),
```

Then add the `bookFollowUp` action as a new Table action in the `->actions([...])` array:

```php
Tables\Actions\Action::make('bookFollowUp')
    ->label('Book Follow-Up Appointment')
    ->icon('heroicon-o-calendar-plus')
    ->color('primary')
    ->visible(false) // triggered programmatically only
    ->slideOver()
    ->form(function (array $arguments) {
        $record = QueueEntry::find($arguments['queue_entry_id'] ?? null);
        return [
            Forms\Components\Placeholder::make('client_display')
                ->label('Client')
                ->content(fn () => $record?->client?->full_name ?? '—'),

            Forms\Components\Select::make('service_id')
                ->label('Service')
                ->options(\App\Models\Service::active()->pluck('name', 'id'))
                ->default(fn () => $record?->service_id)
                ->required(),

            Forms\Components\Select::make('provider_id')
                ->label('Provider')
                ->options(\App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'service_provider'))->pluck('name', 'id'))
                ->default(fn () => auth()->id()),

            Forms\Components\DatePicker::make('appointment_date')
                ->label('Date')
                ->default(today()->addWeek())
                ->required(),

            Forms\Components\TimePicker::make('appointment_time')
                ->label('Time')
                ->required()
                ->seconds(false),

            Forms\Components\Textarea::make('notes')
                ->rows(2),
        ];
    })
    ->action(function (array $data, array $arguments) {
        $record = QueueEntry::find($arguments['queue_entry_id']);
        if (!$record) return;

        $dept  = $record->service?->department_id;
        $appt  = \App\Models\Appointment::create([
            'client_id'        => $record->client_id,
            'service_id'       => $data['service_id'],
            'provider_id'      => $data['provider_id'],
            'department_id'    => $dept,
            'branch_id'        => auth()->user()->branch_id,
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
            'appointment_type' => 'follow_up',
            'status'           => 'scheduled',
            'notes'            => $data['notes'] ?? null,
            'created_by'       => auth()->id(),
        ]);

        app(\App\Services\NotificationService::class)->send(
            $record->client,
            'follow_up_booking',
            [
                'service' => $appt->service?->name ?? 'service',
                'date'    => $appt->appointment_date->format('d M Y'),
                'time'    => $appt->appointment_time->format('H:i'),
            ],
            $appt->id
        );

        Notification::make()
            ->success()
            ->title('Follow-Up Booked')
            ->body("Appointment set for {$appt->appointment_date->format('d M Y')}.")
            ->send();
    }),
```

- [ ] **Step 2: Add "Served Today" tab**

In `ListServiceQueues.php` (or if tabs are in the Resource, find where the table is defined), add tab support. If the resource's `getEloquentQuery()` filters to `['ready', 'in_service']`, we need to override it in the List page with tabs.

Create `app/Filament/Resources/ServiceQueueResource/Pages/ListServiceQueues.php` if it doesn't exist, or modify the existing one to add tabs:

```php
<?php
namespace App\Filament\Resources\ServiceQueueResource\Pages;

use App\Filament\Resources\ServiceQueueResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\QueueEntry;
use App\Models\Appointment;

class ListServiceQueues extends ListRecords
{
    protected static string $resource = ServiceQueueResource::class;

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active Queue')
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->whereIn('status', ['ready', 'in_service'])
                ),

            'served_today' => Tab::make('Served Today')
                ->badge(fn () => QueueEntry::where('status', 'completed')
                    ->whereDate('serving_completed_at', today())
                    ->when(auth()->user()->department_id, fn ($q) => $q->where('department_id', auth()->user()->department_id))
                    ->count()
                )
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->where('status', 'completed')
                     ->whereDate('serving_completed_at', today())
                     ->with(['client', 'service', 'serviceBooking'])
                ),
        ];
    }
}
```

Also add a "Book Appointment" row action in the table that is only visible when `status === 'completed'`:

```php
Tables\Actions\Action::make('book_appointment_from_served')
    ->label('Book Appointment')
    ->icon('heroicon-o-calendar-plus')
    ->color('primary')
    ->visible(fn (QueueEntry $record) => $record->status === 'completed')
    ->url(fn (QueueEntry $record) => route('filament.admin.resources.appointments.create', [
        'client_id'  => $record->client_id,
        'service_id' => $record->service_id,
    ])),
```

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/ServiceQueueResource.php app/Filament/Resources/ServiceQueueResource/Pages/
git commit -m "feat: post-service follow-up booking action and Served Today tab in ServiceQueueResource"
```

---

## Task 18: SpecialistHub — pivot-based forms + session tracking (Phases 11 & 12)

**Files:**
- Modify: `app/Filament/Pages/SpecialistHub.php`
- Modify: `resources/views/filament/pages/specialist-hub.blade.php`

- [ ] **Step 1: Update `mount()` eager-loads** in `SpecialistHub.php`

The existing `mount()` loads `visit.serviceBookings.service.department` but not the pivot forms or sessions. Update both load paths to add `service.assessmentForms` and `serviceBookings.sessions`:

```php
// In the queueId branch, change:
'visit.serviceBookings.service.department',
// To:
'visit.serviceBookings.service.department',
'visit.serviceBookings.service.assessmentForms',
'visit.serviceBookings.sessions',

// In the clientId branch, same update
```

- [ ] **Step 2: Replace `getServiceFormsProperty()`** — it currently calls `$service->assessment_forms` (heuristic). Replace with pivot relationship `$service->assessmentForms`:

```php
public function getServiceFormsProperty(): array
{
    if (! $this->visit) return [];

    $groups      = [];
    $bookings    = $this->visit->serviceBookings ?? collect();
    $isPaediatric = $this->is_paediatric;
    $doneSlugIds  = $this->current_form_responses->pluck('form_schema_id')->toArray();

    foreach ($bookings as $booking) {
        $service = $booking->service;
        if (! $service) continue;

        // Use pivot relationship — explicit, not heuristic
        $allForms = $service->assessmentForms ?? collect();

        $forms = $allForms->filter(function ($schema) use ($isPaediatric) {
            $slug = $schema->slug ?? '';
            $name = strtolower($schema->name ?? '');
            if ($isPaediatric) {
                if (str_contains($slug, '-adult') || str_contains($name, '(adult)')) return false;
            } else {
                if (str_contains($slug, '-pediatric') || str_contains($slug, '-paediatric')
                    || str_contains($name, 'paediatric') || str_contains($name, 'pediatric')) return false;
            }
            return true;
        });

        if ($forms->isEmpty()) $forms = $allForms;

        $forms = $forms->map(fn ($schema) => [
            'schema'    => $schema,
            'completed' => in_array($schema->id, $doneSlugIds),
        ])->values();

        $groups[] = [
            'booking'    => $booking,
            'service'    => $service,
            'department' => $service->department,
            'forms'      => $forms,
            'sessions'   => $booking->sessions ?? collect(),
        ];
    }

    return $groups;
}
```

- [ ] **Step 3: Add `addSession` Livewire action** to SpecialistHub

`SpecialistHub` currently only `extends Page` with no action traits. Add **both** traits and **both** interfaces. Update the class declaration first:

```php
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class SpecialistHub extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    // ... existing properties remain unchanged
}
```

Then add the action method:

public function addSessionAction(): \Filament\Actions\Action
{
    return \Filament\Actions\Action::make('addSession')
        ->label('Add Session')
        ->icon('heroicon-o-plus-circle')
        ->slideOver()
        ->form([
            \Filament\Forms\Components\Hidden::make('service_booking_id'),

            \Filament\Forms\Components\DatePicker::make('session_date')
                ->default(today())
                ->required(),

            \Filament\Forms\Components\Textarea::make('session_goals')
                ->rows(2),

            \Filament\Forms\Components\Textarea::make('activities_performed')
                ->rows(2),

            \Filament\Forms\Components\Select::make('progress_status')
                ->options([
                    'improving'  => 'Improving',
                    'stable'     => 'Stable',
                    'regressing' => 'Regressing',
                    'completed'  => 'Completed',
                ]),

            \Filament\Forms\Components\Select::make('attendance')
                ->options([
                    'present' => 'Present',
                    'absent'  => 'Absent',
                    'late'    => 'Late',
                ])
                ->required(),

            \Filament\Forms\Components\DatePicker::make('next_session_date')
                ->label('Next Session Date (optional)'),
        ])
        ->action(function (array $data) {
            \App\Models\ServiceSession::create(array_merge($data, [
                'provider_id'        => auth()->id(),
                'service_booking_id' => $data['service_booking_id'],
                'session_date'       => $data['session_date'],
            ]));

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Session Recorded')
                ->send();

            // Refresh page data
            $this->visit = \App\Models\Visit::with([
                'serviceBookings.service.assessmentForms',
                'serviceBookings.sessions',
            ])->find($this->visitId);
        });
}
```

- [ ] **Step 4: Update the blade template** `resources/views/filament/pages/specialist-hub.blade.php`

Read the existing template first:
```bash
cat resources/views/filament/pages/specialist-hub.blade.php
```

Find the section that says "coming soon" or shows the service bookings list. Replace it with a card-based panel. The key blade snippet for the services section:

```blade
{{-- Current Visit Services --}}
@if($this->service_forms)
    <div class="space-y-4">
        @foreach($this->service_forms as $group)
            <x-filament::section>
                <x-slot name="heading">
                    {{ $group['service']->name }}
                    <x-filament::badge color="info" class="ml-2">{{ ucwords(str_replace('_', ' ', $group['service']->service_type ?? 'assessment')) }}</x-filament::badge>
                    @if($group['service']->requires_sessions)
                        @php
                            $sessionCount  = $group['sessions']->count();
                            $totalSessions = $group['service']->default_session_count;
                        @endphp
                        <x-filament::badge color="{{ $sessionCount >= $totalSessions ? 'success' : 'warning' }}" class="ml-2">
                            {{ $sessionCount }} / {{ $totalSessions }} sessions
                        </x-filament::badge>
                    @endif
                </x-slot>

                {{-- Assessment Form Buttons --}}
                @if($group['forms']->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach($group['forms'] as $formEntry)
                            <a href="{{ $this->formUrl($formEntry['schema']->slug) }}"
                               class="inline-flex items-center gap-1 px-3 py-1 rounded text-sm
                                      {{ $formEntry['completed'] ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                @if($formEntry['completed'])
                                    <x-heroicon-m-check-badge class="w-4 h-4"/>
                                @else
                                    <x-heroicon-m-document-text class="w-4 h-4"/>
                                @endif
                                {{ $formEntry['schema']->name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- Session Tracking (therapy services only) --}}
                @if($group['service']->requires_sessions)
                    <div x-data="{ open: false }">
                        <button @click="open = !open" class="text-sm text-gray-500 flex items-center gap-1 mb-2">
                            <x-heroicon-m-chevron-right class="w-4 h-4 transition-transform" :class="{ 'rotate-90': open }"/>
                            Sessions
                        </button>

                        <div x-show="open" class="mt-2 space-y-1">
                            @forelse($group['sessions']->sortByDesc('session_sequence') as $session)
                                <div class="flex gap-4 text-sm py-1 border-b border-gray-100">
                                    <span class="font-semibold w-6">#{{ $session->session_sequence }}</span>
                                    <span>{{ $session->session_date?->format('d M Y') }}</span>
                                    <span class="capitalize">{{ $session->attendance }}</span>
                                    <span class="capitalize text-{{ match($session->progress_status) {
                                        'improving' => 'green', 'regressing' => 'red', default => 'gray'
                                    } }}-600">{{ $session->progress_status ?? '—' }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-400">No sessions recorded yet.</p>
                            @endforelse
                        </div>

                        <div class="mt-3">
                            {{ ($this->addSessionAction)(['service_booking_id' => $group['booking']->id]) }}
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    </div>
@endif
```

Include the actions rendering at the bottom of the page (required for slideOver to work):
```blade
<x-filament-actions::modals />
```

- [ ] **Step 5: Test**

Navigate to `/admin/specialist-hub?clientId=1&visitId=1` (use real IDs from your dev DB).
Confirm: service cards show, form buttons render, Add Session button opens slide-over.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/SpecialistHub.php resources/views/filament/pages/specialist-hub.blade.php
git commit -m "feat: SpecialistHub pivot-based forms, session tracking panel, Add Session action"
```

---

## Task 19: Permissions + roles (Phase 13)

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php` (customer_care added in Task 7 already)
- Run shield

- [ ] **Step 1: Confirm `customer_care` is in `RoleSeeder`**

```bash
grep -n "customer_care" database/seeders/RoleSeeder.php
```

If missing, add it to the roles array.

- [ ] **Step 2: Run shield to generate policies for new resources**

```bash
php artisan shield:generate --all
```

Expected output: lists all resources and generates policies. New resources (`AppointmentResource`, `ServiceAvailabilityResource`) will get policies in `app/Policies/`.

- [ ] **Step 3: Verify navigation visibility**

Log in as each role in your dev environment and confirm:
- `receptionist` → sees Appointments Hub, does NOT see AppointmentResource
- `service_provider` → sees AppointmentResource, does NOT see Appointments Hub
- `customer_care` → sees AppointmentResource + ServiceAvailabilityResource
- `triage_nurse` → does NOT see any of the above

- [ ] **Step 4: Run full test suite**

```bash
php artisan test
```

Fix any failing tests before proceeding.

- [ ] **Step 5: Final commit**

```bash
git add app/Policies/ database/seeders/RoleSeeder.php
git commit -m "feat: generate Shield policies for new resources; add customer_care role"
```

---

## Notes for the implementer

1. **Department names in `ServiceCatalogSeeder`** — match the exact `departments.name` values in your database. Run `php artisan tinker --execute="App\Models\Department::pluck('name');"` to list them and adjust the `dept` keys in the seeder if they differ.

2. **Visit factory** — if test factories don't exist, run `php artisan make:factory VisitFactory --model=Visit` and populate the minimum fields (`client_id`, `branch_id`, `current_stage`, `visit_number`).

3. **`$visit->visit_number` auto-generation** — check `Visit::boot()` or a creating observer for how `visit_number` is generated (VST-YYYYMMDD-XXXX pattern). When creating a Visit in `TodayAppointmentsWidget::check_in`, if this boot logic requires a `visit_type` or `check_in_time` field, include it.

4. **`InteractsWithActions` on SpecialistHub** — if the page doesn't already use Filament actions, you must add both the trait and the interface. Look for similar usage in `IntakeAssessmentEditor.php` for the exact pattern.

5. **`mutateFormDataBeforeCreate` scope issue in `ServiceAvailabilityResource`** — this is a static method being used on the resource, but `mutateFormDataBeforeCreate` is only called on CreateRecord pages, not on the Resource class. Move the `updated_by` + `branch_id` population into a `CreateServiceAvailability` page's `mutateFormDataBeforeCreate()` method or handle it via a model observer.

6. **ServiceQueueResource tab override** — the base `getEloquentQuery()` filters to `['ready', 'in_service']`. When the "Served Today" tab is active, `getTabs()` uses `modifyQueryUsing` which appends to the base query. This means the base filter will conflict. Resolve by removing the `whereIn('status', ['ready', 'in_service'])` from `getEloquentQuery()` and instead put it in the "Active Queue" tab's `modifyQueryUsing`.
