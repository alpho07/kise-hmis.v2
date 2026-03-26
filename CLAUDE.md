# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

KISE HMIS is a Health Management Information System built with Laravel 12 + Filament v3. It manages patient visits through a multi-stage clinical workflow across multiple branches.

## Commands

```bash
# Initial setup
composer run setup

# Start development (runs server, queue, pail log viewer, and Vite concurrently)
composer run dev

# Run all tests
composer run test
# or
php artisan test

# Run a single test
php artisan test --filter=TestName

# Code style (Laravel Pint)
./vendor/bin/pint

# Seed the database
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=BranchSeeder
php artisan db:seed  # runs DatabaseSeeder

# Shield (role/permission sync after adding resources)
php artisan shield:generate --all
```

## Architecture

### Admin Panel
The entire UI is a Filament admin panel at `/admin`. All resources in `app/Filament/Resources/` are auto-discovered — each resource file pairs with a `Pages/` subfolder containing List/Create/Edit/View page classes. Widgets live in `app/Filament/Widgets/`. The single custom page `ClientProfileHub` acts as a central client hub.

Navigation is organized into groups defined in `AdminPanelProvider`: Client Management, Clinical Workflow, Service Delivery, Billing & Payments, Reports & Analytics, System Settings. Each resource controls its own navigation group and visibility via `shouldRegisterNavigation()`.

### Visit Workflow (Core Business Logic)
The system tracks patients through sequential stages on the `Visit` model:

```
Reception → Triage → Intake → Billing → Payment → Service → Completed
```

- `Visit::current_stage` tracks where the patient currently is
- `Visit::moveToStage(string $stage)` advances the visit and creates a `VisitStage` record with timestamp
- `Visit::completeStage()` marks the current stage as done before moving
- Stage-specific actions appear conditionally in queue resource tables based on `current_stage`

### Multi-Branch Data Isolation
The `BelongsToBranch` trait (used by `Client` and other models) automatically:
- Sets `branch_id` from the authenticated user on record creation
- Applies a global Eloquent scope restricting queries to the user's branch

`super_admin` role bypasses the global scope and sees all branches. To query across branches explicitly, use `->withoutGlobalScope('branch')` or the `->allBranches()` scope.

### Role-Based Access Control
FilamentShield + Spatie Laravel Permission manage access. Roles defined in `RoleSeeder`:
- `super_admin` — full access, all branches
- `admin` — full access within their branch
- `branch_manager`, `receptionist`, `triage_nurse`, `intake_officer`, `billing_admin`, `cashier`, `service_provider`, `queue_manager` — scoped to their workflow stage

Resources control visibility with `shouldRegisterNavigation()` checking `auth()->user()->hasRole([...])`.

After adding new Filament resources, run `php artisan shield:generate --all` to create permissions.

### Key Services (`app/Services/`)
- `PaymentProcessingService` / `HybridPaymentService` / `PaymentRoutingService` — payment flow (cash, insurance, credit, hybrid)
- `InsuranceClaimService` / `BatchInvoiceService` — insurance billing and batch claim processing
- `ServicePointVerification` — validates service delivery at point of care
- `DynamicFormBuilder` — powers the configurable intake assessment forms

### Invoice & Payment Model
Invoices have `has_sponsor` (bool) to indicate insurance involvement. Key amounts:
- `total_amount` — full service cost
- `total_sponsor_amount` — insurance portion
- `total_client_amount` — client out-of-pocket

`BillingResource` only shows sponsor invoices (`has_sponsor = true`) needing admin review; `CashierQueueResource` handles patient-facing payment collection.

### UCI (Unique Client Identifier)
Clients receive a UCI auto-generated in format `KISE/A/000XXX/YEAR`. Visit numbers follow `VST-YYYYMMDD-XXXX`.

### Dynamic Assessment Forms
`DynamicFormBuilder` service and the `AssessmentFormSchema` / `AssessmentFormVersion` / `AssessmentFormResponse` models support configurable clinical intake forms seeded via `VisionCentreFormSchemasSeeder` and `IntakeAssessmentSeeder`.
