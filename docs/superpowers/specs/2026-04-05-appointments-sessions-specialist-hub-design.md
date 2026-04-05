# KISE HMIS — Appointments, Sessions, Specialist Hub & Service Pricing Design
**Date:** 2026-04-05
**Status:** Approved by stakeholder — spec reviewed and corrected (v3)
**Approach:** C — New AppointmentResource + targeted enhancements

---

## 1. Scope

Eight sub-systems built in dependency order:

| Phase | Sub-system |
|---|---|
| 0 | Migrations & seeders (data model foundation) |
| 1 | Service model enhancements + insurance pricing |
| 2 | Assessment forms → services wiring (pivot) |
| 3 | Return visit routing (Triage → Billing, skip Intake) |
| 4 | Reception Appointments Hub |
| 5 | Department AppointmentResource |
| 6 | Post-service booking (ServiceQueueResource extension) |
| 7 | Specialist Hub: services panel + form links + session tracking |
| 8 | Service availability + NotificationService stub |

Existing visit workflow: `reception → triage → intake → billing → payment → service → completed`
Return visit fast-track: `reception → triage → billing → payment → service → completed`

---

## 2. Data Model Changes

### 2.1 `services` table — new columns

The existing `category VARCHAR(100)` column stores age-group classification (`child/adult/both`) via `Service::CATEGORY_*` constants and must NOT be changed.
Add a **separate** column:

- `service_type` ENUM(`assessment`, `therapy`, `assistive_technology`, `consultation`) NOT NULL DEFAULT `assessment`
- `requires_sessions` BOOLEAN NOT NULL DEFAULT FALSE
- `default_session_count` UNSIGNED TINYINT NULL — prescribed session count (e.g. 12 for hydrotherapy)

**Service model scope fix:** The `Service` model may contain broken scopes referencing `category_type` (a column that does not exist). Any such scopes must be removed or corrected to reference the actual column names: `category` (existing age-group column) or the new `service_type` column. Do not conflate the two.

Seeded from the PDF price list:

| Service | Base Price (KES) | service_type | requires_sessions | default_session_count |
|---|---|---|---|---|
| Children OT | 500 | therapy | true | 12 |
| Children PT | 500 | therapy | true | 12 |
| Children Hydrotherapy | 500 | therapy | true | 12 |
| Children Fine Motor | 500 | therapy | true | 12 |
| Sensory Integration | 500 | therapy | true | 12 |
| Play Therapy | 500 | therapy | true | 12 |
| Children Speech Therapy | 500 | therapy | true | 12 |
| Adult Assessment Consultation | 1000 | consultation | false | null |
| Adult OT | 1000 | therapy | true | 12 |
| Adult PT | 1000 | therapy | true | 12 |
| Adult Hydrotherapy | 1500 | therapy | true | 12 |
| Adult Speech Therapy | 1000 | therapy | true | 12 |
| Adult Speech Assessment | 2000 | assessment | false | null |
| Auditory for Adults | 1000 | assessment | false | null |
| Ear Molds (per ear) | 2000 | assistive_technology | false | null |
| Nutrition Review | 500 | consultation | false | null |

Departments add their own services via `ServiceResource`.

---

### 2.2 `insurance_providers` table — ALTER existing `type` column

The table already has `type ENUM('public', 'private', 'government')`. **ALTER** this column (do not add a second column) to expand its values:

```
type ENUM('government_scheme', 'ecitizen', 'private')
```

Migration sequence:
1. `DB::update("UPDATE insurance_providers SET type='government_scheme' WHERE type IN ('public','government')")`
2. ALTER column to new ENUM

**Model scope updates (explicit):**
- Remove `scopePublic()` — replace with `scopeGovernmentScheme()`: `where('type', 'government_scheme')`
- Keep `scopePrivate()` — value `'private'` is unchanged
- Add `scopeEcitizen()`: `where('type', 'ecitizen')`
- Update any existing call sites of `InsuranceProvider::public()` → `InsuranceProvider::governmentScheme()`

Seeded system providers (upsert, not duplicate-insert):

| Name | type |
|---|---|
| SHA (Social Health Authority) | government_scheme |
| NCPWD | government_scheme |
| E-citizen | ecitizen |

All future payers are additional `InsuranceProvider` rows — no migration required.

---

### 2.3 `service_insurance_prices` table — verify + enforce

Model `ServiceInsurancePrice` exists. The existing migration has the unique constraint commented out and includes `effective_from`/`effective_to` date columns.

**Decision:** Leave `effective_from`/`effective_to` columns in the table but remove them from `ServiceInsurancePrice::$fillable` and `$casts`, and delete the `scopeEffective()` query method. Pricing is treated as a current-state record only; history is not tracked. Enforce a simple unique constraint `(service_id, insurance_provider_id)`. A data-cleanup migration must run first: delete any duplicate `(service_id, insurance_provider_id)` rows, keeping the most recently updated one.

Migration sequence:
1. Data cleanup query (delete duplicates, keep latest)
2. `ALTER TABLE service_insurance_prices ADD UNIQUE (service_id, insurance_provider_id)`

Schema (confirmed/add if missing):
- `service_id` FK → services (cascade delete)
- `insurance_provider_id` FK → insurance_providers (cascade delete)
- `covered_amount` DECIMAL(10,2) — absolute amount the insurer pays
- `client_copay` DECIMAL(10,2) — client out-of-pocket amount
- `notes` TEXT NULL

**Replaces** the hardcoded `match($paymentMethod)` ratio in `IntakeAssessmentEditor::finalize()`:

```php
$insuranceProviderId = $sr['insurance_provider_id'] ?? null;
$price = $insuranceProviderId
    ? ServiceInsurancePrice::where('service_id', $service->id)
          ->where('insurance_provider_id', $insuranceProviderId)
          ->first()
    : null;
$clientPays = $price ? $price->client_copay   : $service->base_price;
$covered    = $price ? $price->covered_amount : 0;
```

If no pricing row exists, full `base_price` is charged to the client (safe fallback).

---

### 2.4 `service_form_schemas` pivot table — NEW (replaces nullable FK approach)

Instead of adding `service_id` to `assessment_form_schemas` (which allows only one form per service), create a pivot table to support multiple forms per service (e.g. adult and paediatric variants):

```
service_form_schemas: id | service_id FK | assessment_form_schema_id FK
Unique: (service_id, assessment_form_schema_id)
```

`Service` gets a `belongsToMany(AssessmentFormSchema::class, 'service_form_schemas')` relationship.
`ServiceResource` gets a multi-select for linking form schemas to a service.
`SpecialistHub` queries `$serviceBooking->service->assessmentForms` to build the form button list.

**Pivot timestamps:** `service_form_schemas` does NOT use `->withTimestamps()` — it is a simple junction table with no extra columns beyond the two FKs and the primary key.

---

### 2.5 `visits` table — ALTER `triage_path` to ENUM

`triage_path` is currently a plain `string` column (the block that would have converted it was commented out in a prior migration). Migration sequence:

1. Data cleanup: `DB::update("UPDATE visits SET triage_path = 'standard' WHERE triage_path NOT IN ('standard','returning','medical_veto','crisis') OR triage_path IS NULL")`
2. `ALTER TABLE visits MODIFY triage_path ENUM('standard', 'returning', 'medical_veto', 'crisis') NULL`

`returning` is set at appointment check-in. `standard` is the default for new walk-in clients. `medical_veto` and `crisis` are set by triage for held/crisis cases.

---

### 2.6 `appointments` table — add `branch_id`

The `Appointment` model has no `branch_id`. Add:
- `branch_id` UNSIGNED BIGINT FK → branches, nullable, `nullOnDelete()`

On appointment creation, `branch_id` is populated from `department->branch_id`.

**Important:** The `Appointment` model must **NOT** use the `BelongsToBranch` trait. The trait auto-sets `branch_id` from `auth()->user()->branch_id`, which conflicts with department-derived branch assignment (a department's branch may differ from the logged-in user's branch in cross-department scenarios). Instead, scope appointment queries manually:
- Reception Hub: `Appointment::whereHas('department', fn($q) => $q->where('branch_id', auth()->user()->branch_id))`
- AppointmentResource: `Appointment::where('department_id', auth()->user()->department_id)`

---

### 2.7 `service_sessions` table — add missing columns only

Existing columns (do NOT re-add): `session_number VARCHAR(50)`, `session_goals`, `activities_performed`, `attendance ENUM('present','absent','late')`.

**Add only:**
- `progress_status` ENUM(`improving`, `stable`, `regressing`, `completed`) NULL
- `next_session_date` DATE NULL
- `session_sequence` UNSIGNED TINYINT NULL — integer sequence (1, 2, 3…) used for counting progress; separate from the VARCHAR `session_number`

**Fix `ServiceSession::$fillable`** — replace the entire `$fillable` array with the correct column list: `service_booking_id`, `session_date`, `conducted_by`, `session_number`, `session_goals`, `activities_performed`, `attendance`, `progress_status`, `next_session_date`, `session_sequence`, `notes`. Remove any entries for `session_type` or `attendance_status` — these columns do not exist in the table.

**Session auto-completion logic:**
An `ObservingServiceSession` observer fires on `ServiceSession::created`:
```php
$count = ServiceSession::where('service_booking_id', $session->service_booking_id)->count();
$booking = $session->serviceBooking()->with('service')->first();
if ($booking->service->default_session_count && $count >= $booking->service->default_session_count) {
    $booking->update(['service_status' => 'completed']);
}
```
`session_sequence` is also set by this observer: `$session->update(['session_sequence' => $count])`.

---

### 2.8 `service_availability` table — NEW

```
id | branch_id FK | department_id FK | date DATE | is_available BOOLEAN DEFAULT TRUE
   | reason_code ENUM('staff_absent','equipment_unavailable','public_holiday','training','other') NULL
   | comment VARCHAR(500) NULL | updated_by FK → users | created_at | updated_at
```

Unique constraint: `(department_id, date)` — enforces a single availability record per department per day. This is intentional: Customer Care updates one record for today's date, and subsequent updates use `updateOrCreate` on that constraint. Departments are branch-scoped, so no cross-branch collision risk.

---

### 2.9 `notification_logs` table — NEW

```
id | client_id FK | recipient_phone VARCHAR(20) | message_type ENUM | message_body TEXT
   | appointment_id UNSIGNED BIGINT NULL (plain integer, no FK constraint — survives appointment deletion)
   | status ENUM('mock','queued','sent','failed') DEFAULT 'mock'
   | staff_id FK → users | sent_at TIMESTAMP NULL | created_at
```

`message_type` ENUM values: `appointment_reminder`, `check_in_confirmation`, `disruption_alert`, `follow_up_booking`

---

## 3. New Roles

Add `customer_care` to `RoleSeeder` and run `shield:generate --all` at end of build.
`customer_care` accesses: `ServiceAvailabilityResource`, `AppointmentResource` (read/create).

---

## 4. Service Resource Enhancements

`ServiceResource` (existing) gains four new panels:

1. **Insurance pricing repeater** — inline table of `ServiceInsurancePrice` rows. Columns: Insurance Provider (Select grouped by `type`), Covered Amount, Client Copay, Notes. Add/remove rows. Provider select queries all `InsuranceProvider` records ordered by type.

2. **Session configuration** — `service_type` select; `requires_sessions` toggle; when ON, show `default_session_count` number input.

3. **Assessment forms** — multi-select of `AssessmentFormSchema` records (saved via `service_form_schemas` pivot).

4. **Category** (existing `category` column) — unchanged, still managed as before.

---

## 5. Return Visit Routing (Fully Automatic)

### Rule
When triage nurse clicks "Complete Triage" in `TriageQueueResource`:

```php
$routeTo = ($visit->is_appointment || $visit->triage_path === 'returning')
    ? 'billing'
    : 'intake';
$visit->completeStage();
$visit->moveToStage($routeTo);
```

**No routing selector for the nurse.** Decision is fully system-driven from data set at check-in.

### Triage queue pickup for appointment check-ins
`TriageQueueResource` queries `Visit::where('current_stage', 'triage')`. Since appointment check-in calls `$visit->moveToStage('triage')`, the visit appears in the triage queue automatically — no additional queue entry creation is needed for triage.

---

## 6. Reception Appointments Hub

**Type:** New Filament Page (`AppointmentsHubPage`).
**Navigation group:** Client Management.
**Roles:** `receptionist`, `admin`, `super_admin`.
**Scoping:** Reads `Appointment::whereHas('department', fn($q) => $q->where('branch_id', auth()->user()->branch_id))`.

**Implementation:** Use Filament `Widget` components (not page-level polling) so Panel A and B refresh independently without re-rendering the whole page. Three widgets: `TodayAppointmentsWidget`, `WalkInQueueWidget`, `ServiceAvailabilityWidget`.

### TodayAppointmentsWidget
Queries: `Appointment::today()->with(['client','service','provider','department'])->orderBy('appointment_time')`
Columns: Time, Client (UCI + name), Service, Provider, Status badge
Status badges: Scheduled (gray), Confirmed (blue), Checked In (green), No Show (red)
Actions per row: **Check In** (primary, disabled if already checked in or department unavailable today), Mark No Show, View Client

**Check-in action:**
1. Validate: `ServiceAvailability::where('department_id', $appt->department_id)->where('date', today())->value('is_available')` — abort if false
2. Create `Visit`: `is_appointment=true`, `visit_type='appointment'`, `triage_path='returning'`, `branch_id=auth()->user()->branch_id`
3. `$visit->moveToStage('triage')` (sets `current_stage = 'triage'`, creates VisitStage record)
4. `$appointment->update(['status' => 'checked_in', 'checked_in_at' => now(), 'visit_id' => $visit->id])`
5. No data transfer needed — the appointment remains linked to the visit via `appointment.visit_id`. The Payment/Cashier stage reads `$visit->appointment->insurance_provider_id` directly from the appointment relationship to pre-populate the payer selection. `visit.services_required` (a JSON column on `IntakeAssessment`, not on `Visit`) must NOT be written to here.
6. `NotificationService::send($client, 'check_in_confirmation', ['service' => ..., 'time' => now()])`

### WalkInQueueWidget
Query: `Visit::where('current_stage', 'reception')->where('is_appointment', false)->where('branch_id', auth()->user()->branch_id)->count()`.
Displays "N walk-ins at reception" — links to `ReceptionResource`. Refreshes every 30 s via `protected static ?string $pollingInterval = '30s'`.

### ServiceAvailabilityWidget
Reads `ServiceAvailability::where('branch_id', ...)->where('date', today())->with('department')`.
Green pill = available, Red pill = unavailable + reason. Warns receptionist before checking in.

**Polling:** Each widget uses `protected static ?string $pollingInterval = '30s'`.

---

## 7. Department AppointmentResource

**Type:** Full Filament Resource on `Appointment` model.
**Navigation group:** Service Delivery.
**Roles:** `service_provider`, `customer_care`, `admin`, `super_admin`.
**Scoping:** Filtered to user's `department_id` via global scope or `shouldRegisterNavigation` + query modification.

### Table
Tabs: **Today** | **Upcoming** | **Past** | **All**
Columns: Date/Time, Client (UCI + name), Service, Provider, Status badge, Payment Status, SMS Sent (icon)
Filters: provider, service, date range, status
Row actions: Confirm, Cancel, Mark No Show, Reschedule, View

### Create/Edit form
- **Client search** — `Select::make('client_id')->getSearchResultsUsing(fn($s) => Client::withoutGlobalScope('branch')->where(...)->pluck('full_name','id'))` — cross-branch required, explicit scope bypass
- Service — filtered to department's services
- Provider — filtered to users with `service_provider` role in this department
- Date + Time
- Duration — pre-filled from service average; editable
- Appointment type — `follow_up | review | therapy_session | new_assessment`
- Session notes / reason
- Payment method — `Select::make('insurance_provider_id')->relationship('insuranceProvider','name')->optionsUsing(fn() => InsuranceProvider::orderBy('type')->pluck('name','id'))`
- SMS reminder checkbox — on save, triggers `NotificationService::send(..., 'appointment_reminder', ...)`

### Validation
Before save: check `ServiceAvailability::where('department_id', ...)->where('date', $appointmentDate)->where('is_available', false)->exists()` — if true, validation error: "[Department] is unavailable on [date]: [reason]".

**Note:** No concurrent-booking slot capacity enforcement in this build. Two receptionists booking the same slot simultaneously is accepted as a known limitation. The binary available/unavailable flag is the current mitigation.

---

## 8. Post-Service Booking (ServiceQueueResource Extension)

### 8.1 "Complete Service" — two-step action
Step 1: existing completion logic (mark queue entry complete, check if all services done, advance visit).
Step 2: a second `Action` is triggered from within the first action's `after()` callback:

```php
->after(fn($action, $record) => $action->mountAction('bookFollowUp', ['queue_entry_id' => $record->id]))
```

"Book Follow-up?" modal (pre-filled):
- Client (read-only display)
- Service (pre-filled from queue entry's service)
- Provider (pre-filled from current user)
- Date — defaults to today + 7 days
- Time + Notes

Buttons: **Book & Close** | **Skip**

"Book & Close" creates `Appointment` + queues `follow_up_booking` notification (mock).
"Skip" dismisses with no side effects.

**Note (T2):** The optional appointment booking is implemented as a second `Action` triggered from the first, not as a nested action inside a slide-over. Filament v3 supports this via `$this->mountAction()` chaining.

### 8.2 "Served Today" tab
New tab in `ServiceQueueResource`.
Query: `QueueEntry::where('status', 'completed')->whereDate('completed_at', today())->with(['client','service','serviceBooking'])`.
Columns: Client, Service, Completed At, Provider, Appointment Booked (badge from `Appointment::where('visit_id', ...)->whereDate('appointment_date', '>', today())->exists()`).
Row action: **Book Appointment** — same modal as 8.1, pre-filled with client + service.

---

## 9. Specialist Hub Enhancements

### 9.1 Current visit services panel
Replaces "coming soon" with a live service booking card list.

Per `ServiceBooking` on the current visit, display:

| Element | Detail |
|---|---|
| Service name | Bold |
| service_type badge | e.g. "Therapy" |
| Status badge | Scheduled → In Queue → In Service → Completed |
| Sessions pill | "3 / 12" — shown only if `service->requires_sessions` |
| **Open Form** buttons | One per linked schema (from `service->assessmentForms`); absent if no forms linked |
| **Add Session** button | Shown only if `service->requires_sessions` |
| **Book Appointment** button | Shown on non-completed bookings |

### 9.2 Session tracking panel
Shown below each session-based service card. Collapsible.

**Header:** "Sessions — 3 of 12 completed" + progress bar.

**Session list:**
Columns: `session_sequence`, Date, Conducted By, Progress, Attendance — ordered by `session_sequence` DESC.

**"Add Session" Livewire action** (implemented as a Filament `Action::make()->slideOver()`):

Fields (using correct existing column names):
- `session_date` DATE — defaults today
- `session_goals` TEXT
- `activities_performed` TEXT
- `progress_status` ENUM (Improving / Stable / Regressing / Completed)
- `attendance` ENUM (Present / Absent / Caregiver Only)
- Notes (free text)
- `next_session_date` DATE (optional)

On save:
- Creates `ServiceSession` record; observer fires to set `session_sequence` and check completion
- If `next_session_date` is filled: show a separate "Book appointment for next session?" confirm button **outside** the slide-over (not nested inside it) — Filament v3 limitation
- If therapy is now complete: show "Therapy Complete" badge on the service card

### 9.3 SpecialistHub implementation surgery required

The existing `app/Filament/Pages/SpecialistHub.php` contains a heuristic `getAssessmentFormsAttribute` accessor on the `Service` model (or equivalent logic in the hub page) that attempts to guess which forms belong to a service without a real relationship. This must be **removed entirely** and replaced with the pivot-based approach:

```php
// OLD — delete this
public function getAssessmentFormsAttribute() { /* heuristic guessing */ }

// NEW — in SpecialistHub or Service model
$forms = $serviceBooking->service->assessmentForms; // via belongsToMany pivot
```

`SpecialistHub::getServiceFormsProperty()` (or equivalent computed property) must be rewritten to use `$serviceBooking->service->assessmentForms` from the `service_form_schemas` pivot.

### 9.4 Previous visits
No changes to existing structure.

---

## 10. Service Availability Resource

**Type:** Filament Resource on `ServiceAvailability` model.
**Navigation group:** System Settings.
**Roles:** `customer_care`, `admin`, `super_admin`.
**Scoping:** User's department/branch.

### Daily update form
Pre-populated with all services in the department. Per service:
- Toggle: Available / Unavailable
- If Unavailable: `reason_code` select + `comment` text

Submit → upserts `ServiceAvailability` rows for today (`updateOrCreate(['department_id' => ..., 'date' => today()], [...])`).

### Affected clients notification
After save, if any service was marked unavailable:
- Query `Appointment::where('department_id', ...)->whereDate('appointment_date', today())->where('status', 'scheduled/confirmed')`
- Show list: Time, Client name, Phone, Service. All rows checked by default.
- **"Send Disruption SMS"** button → `NotificationService::send()` for each checked client → logs `disruption_alert` with `status = mock`

---

## 11. NotificationService

**File:** `app/Services/NotificationService.php`

```php
public function send(Client $client, string $type, array $data, ?int $appointmentId = null): NotificationLog
```

**Message templates:**

| Type | Template |
|---|---|
| `appointment_reminder` | "Dear [Name], your appointment at KISE on [Date] at [Time] for [Service] is confirmed. Reply STOP to opt out." |
| `check_in_confirmation` | "Dear [Name], you have been checked in at KISE for [Service]. Please proceed to Triage." |
| `disruption_alert` | "Dear [Name], [Service] at KISE is unavailable on [Date] ([Reason]). We will contact you to reschedule." |
| `follow_up_booking` | "Dear [Name], your next [Service] appointment at KISE has been booked for [Date] at [Time]." |

**Mock implementation:**
- Always writes `NotificationLog` with `status = mock`
- `recipient_phone` taken from `$client->phone_primary`
- Contains commented-out Celcom gateway stub at the end of the method with expected endpoint + payload structure — wiring the real gateway = uncomment + add `CELCOM_API_KEY` and `CELCOM_ENDPOINT` to `.env`

---

## 12. Full Build Sequence (13 Phases)

| Phase | Deliverable | Depends on |
|---|---|---|
| 0a | Migration: `services` add `service_type`, `requires_sessions`, `default_session_count` | — |
| 0b | Migration: ALTER `insurance_providers.type` ENUM + data migration for existing rows | — |
| 0c | Migration: `service_insurance_prices` data cleanup + unique constraint | 0b |
| 0d | Migration: `service_form_schemas` pivot table | — |
| 0e | Migration: ALTER `visits.triage_path` to ENUM | — |
| 0f | Migration: `appointments` add `branch_id` | — |
| 0g | Migration: `service_sessions` add `progress_status`, `next_session_date`, `session_sequence`; fix `$fillable` | — |
| 0h | Migration: `service_availability` table | — |
| 0i | Migration: `notification_logs` table | — |
| 1 | Seeder: InsuranceProvider system records (SHA/NCPWD/E-citizen); Services from PDF list | 0a, 0b |
| 2 | ServiceResource enhancements: pricing matrix, session config, form pivot link, service_type | 0a–0d, 1 |
| 3 | `finalize()` refactor: replace hardcoded ratios with `ServiceInsurancePrice` lookup | 0c, 2 |
| 4 | `NotificationService` stub + `NotificationLog` model | 0i |
| 5 | `ServiceSession` observer for auto-sequence + completion; fix `$fillable` | 0g |
| 6 | Return visit routing: `TriageQueueResource` auto-route on `is_appointment`/`triage_path` | 0e |
| 7 | `AppointmentsHubPage` + three Widgets (today's appts, walk-ins, availability) | 0f, 0h, 4, 6 |
| 8 | `AppointmentResource` (full CRUD, department-scoped, cross-branch client search) | 0f, 0h, 4 |
| 9 | `ServiceAvailabilityResource` (Customer Care daily updates + SMS) | 0h, 4 |
| 10 | `ServiceQueueResource`: "Served Today" tab + post-service booking two-step action | 8 |
| 11 | `SpecialistHub`: current services panel + Open Form buttons | 0d, 2 |
| 12 | `SpecialistHub`: session tracking panel + Add Session action | 5, 11, 8 |
| 13 | `php artisan shield:generate --all`; add `customer_care` to `RoleSeeder` | 12 |

---

## 13. Out of Scope (This Build)

- Real Celcom SMS gateway (stub + NotificationLog only)
- Concurrent appointment slot capacity enforcement (binary available/unavailable flag is accepted mitigation)
- Client self-booking portal
- Reporting dashboards (referenced in PDF — separate spec)
- External referral UI (model exists; deferred)
- Date-range pricing on `ServiceInsurancePrice` (simple single-row-per-payer approach chosen)
