<?php

namespace App\Filament\Pages;

use App\Filament\Resources\IntakeAssessmentResource;
use App\Models\Client;
use App\Models\ClientDisability;
use App\Models\ClientEducation;
use App\Models\ClientMedicalHistory;
use App\Models\ClientSocioDemographic;
use App\Models\FunctionalScreening;
use App\Models\IntakeAssessment;
use App\Models\Visit;
// Pre-imported for Task 5-9 save methods and finalize():
use App\Models\AssessmentAutoReferral;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceInsurancePrice;
use Carbon\Carbon;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class IntakeAssessmentEditor extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.intake-assessment-editor';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Intake Assessment Editor';

    #[Url]
    public int $intakeId = 0;

    #[Url(as: 'section')]
    public string $activeSection = 'A';
    public array $sectionStatus  = [];
    public bool  $isSaving       = false;

    public ?IntakeAssessment $intake = null;
    public ?Client $client = null;

    /** One data bag per section — Filament forms bind to sectionData.X */
    public array $sectionData = [];

    protected array $sections = ['A','B','C','D','E','F','G','H','I','J','K','L'];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['intake_officer', 'admin', 'super_admin']) ?? false;
    }

    public function mount(): void
    {
        abort_unless($this->intakeId > 0, 404);

        $this->intake = IntakeAssessment::with([
            'visit.branch', 'client.county', 'client.subCounty', 'functionalScreening',
        ])->findOrFail($this->intakeId);

        $this->client = $this->intake->client;

        $this->sectionStatus = array_merge(
            array_fill_keys($this->sections, 'incomplete'),
            $this->intake->section_status ?? []
        );

        // Section A always auto-completes when client is linked
        if ($this->intake->client_id) {
            $this->sectionStatus['A'] = 'complete';
        }

        $this->fillSectionData();
    }

    protected function fillSectionData(): void
    {
        $intake = $this->intake;
        $client = $this->client;
        $visit  = $intake->visit;
        $sr     = $intake->services_required ?? [];

        if (!$client) {
            // Client not linked yet — leave section data empty
            return;
        }

        $this->sectionData['B'] = [
            'verification_mode'         => $intake->verification_mode,
            'verification_notes'        => $intake->verification_notes,
            'b_national_id'             => $client->national_id,
            'b_birth_certificate'       => $client->birth_certificate_number,
            'b_phone_primary'           => $client->phone_primary,
            'b_phone_secondary'         => $client->phone_secondary,
            'b_preferred_communication' => $client->preferred_communication,
            'b_consent_to_sms'          => $client->consent_to_sms,
            'b_sha_number'              => $client->sha_number,
            'b_ncpwd_number'            => $client->ncpwd_number,
            'b_county_id'               => $client->county_id,
            'b_sub_county_id'           => $client->sub_county_id,
            'b_ward_id'                 => $client->ward_id,
            'b_primary_address'         => $client->primary_address,
            'b_landmark'                => $client->landmark,
            'b_email'                   => $client->email,
        ];

        $disability = ClientDisability::where('client_id', $client->id)->first();
        // Auto-infer ncpwd_registered from the client record when it hasn't been explicitly set:
        // if a number exists on the client, the client is registered — mark 'yes' as the default.
        $ncpwdRegistered = $disability?->ncpwd_registered;
        if (empty($ncpwdRegistered) && !empty($client->ncpwd_number)) {
            $ncpwdRegistered = 'yes';
        }
        $this->sectionData['C'] = $disability ? [
            'dis_is_disability_known'        => $disability->is_disability_known,
            'dis_disability_categories'      => $disability->disability_categories ?? [],
            'dis_onset'                      => $disability->onset,
            'dis_level_of_functioning'       => $disability->level_of_functioning,
            'dis_disability_notes'           => $disability->disability_notes,
            'dis_evidence_files'             => $this->wrapFileState($disability->evidence_files ?? []),
            'dis_ncpwd_registered'           => $ncpwdRegistered,
            'dis_ncpwd_number'               => $client->ncpwd_number,
            'dis_ncpwd_verification_status'  => $disability->ncpwd_verification_status,
        ] : [
            // No disability record yet — still pre-fill NCPWD fields from client if the number exists
            'dis_ncpwd_registered' => !empty($client->ncpwd_number) ? 'yes' : null,
            'dis_ncpwd_number'     => $client->ncpwd_number,
        ];

        $socio = ClientSocioDemographic::where('client_id', $client->id)->first();
        // primary_caregiver is stored as plain value or 'other: <text>' for the free-text case
        $caregiverRaw = $socio?->primary_caregiver ?? null;
        $caregiverIsOther = str_starts_with($caregiverRaw ?? '', 'other: ');
        // primary_language is similarly stored as plain value or 'other: <text>'
        $langRaw = $socio?->primary_language ?? null;
        $langIsOther = str_starts_with($langRaw ?? '', 'other: ');
        $this->sectionData['D'] = $socio ? [
            'socio_marital_status'        => $socio->marital_status,
            'socio_marital_other'         => $socio->marital_status_other,
            'socio_living_arrangement'    => $socio->living_arrangement,
            'socio_living_other'          => $socio->living_arrangement_other,
            'socio_household_size'        => $socio->household_size,
            'socio_primary_caregiver'     => $caregiverIsOther ? 'other' : $caregiverRaw,
            'socio_caregiver_other'       => $caregiverIsOther ? substr($caregiverRaw, 7) : null,
            'socio_source_of_support'     => $socio->source_of_support ?? [],
            'socio_other_support'         => $socio->other_support_source,
            'socio_school_enrolled'       => $socio->school_enrolled,
            'socio_primary_language'      => $langIsOther ? 'other' : $langRaw,
            'socio_language_other'        => $langIsOther ? substr($langRaw, 7) : null,
            'socio_other_languages'       => $socio->other_languages[0] ?? null,
            'socio_accessibility_at_home' => $socio->accessibility_at_home,
            'socio_notes'                 => $socio->socio_notes,
        ] : [];

        $feedingHistory = [];
        $med = ClientMedicalHistory::where('client_id', $client->id)->first();
        if ($med?->feeding_history) {
            $feedingHistory = is_array($med->feeding_history) ? $med->feeding_history : [];
        }
        $peri    = $med?->perinatal_history          ?? [];
        $imm     = $med?->immunization_records        ?? [];
        $atNeeds = $med?->assistive_technology_needs  ?? [];
        $this->sectionData['E'] = [
            // E1 — Medical History
            'med_medical_conditions'        => $med?->medical_conditions ?? [],
            'med_current_medications'       => $med?->current_medications,
            'med_surgical_history'          => $med?->surgical_history,
            'med_family_medical_history'    => $med?->family_medical_history,
            'family_history'                => $intake->family_history,
            'med_immunization_status'       => $med?->immunization_status,
            'med_previous_assessments'      => $med?->previous_assessments ?? [],
            'med_has_at_history'            => (!empty($disability?->assistive_technology) || !empty($med?->assistive_devices_history)) ? 'yes' : null,
            'allergy_items'                 => $med?->allergies ?? [],
            // E2 — Assistive Technology
            'e2_has_at'                     => !empty($disability?->assistive_technology) ? 'yes' : null,
            'e2_current_devices'            => $disability?->assistive_technology ?? [],
            'e2_previous_at'                => !empty($med?->assistive_devices_history) ? 'yes' : null,
            'e2_previous_devices'           => $med?->assistive_devices_history ?? [],
            'e2_needs_at'                   => $atNeeds['needs_at']         ?? null,
            'e2_needs_categories'           => $atNeeds['needs_categories'] ?? [],
            'e2_needs_priority'             => $atNeeds['needs_priority']   ?? null,
            'e2_needs_notes'                => $atNeeds['needs_notes']      ?? null,
            'e2_satisfaction'               => $atNeeds['satisfaction']     ?? null,
            // E3 — Perinatal History
            'peri_pregnancy_complications'  => $peri['pregnancy_complications'] ?? [],
            'peri_place_of_birth'           => $peri['place_of_birth']          ?? null,
            'peri_mode_of_delivery'         => $peri['mode_of_delivery']        ?? null,
            'peri_gestation_weeks'          => $peri['gestation_weeks']         ?? null,
            'peri_birth_weight_kg'          => $peri['birth_weight_kg']         ?? null,
            'peri_neonatal_care'            => $peri['neonatal_care']           ?? [],
            'peri_early_medical_issues'     => $peri['early_medical_issues']    ?? [],
            'peri_developmental_concerns'   => $peri['developmental_concerns']  ?? [],
            'developmental_history'         => $med?->developmental_concerns_notes,
            // E4 — Immunization
            'imm_epi_status'                  => $imm['epi_status']                  ?? [],
            'imm_epi_card_seen'               => $imm['epi_card_seen']               ?? null,
            'imm_epi_card_photo'              => $this->wrapFileState($imm['epi_card_photo'] ?? null),
            'imm_missed_doses'                => $imm['missed_doses']                ?? null,
            'imm_missed_doses_which'          => $imm['missed_doses_which']          ?? null,
            'imm_recent_illness_post_vaccine' => $imm['recent_illness_post_vaccine'] ?? null,
            'imm_recent_illness_notes'        => $imm['recent_illness_notes']        ?? null,
            // E5 — Feeding
            'feeding_method'                => $feedingHistory['feeding_method']    ?? null,
            'feeding_diet_appetite'         => $feedingHistory['diet_appetite']     ?? null,
            'feeding_diet_foods_brief'      => $feedingHistory['foods_brief']       ?? null,
            'feeding_swallowing_concerns'   => $feedingHistory['swallowing_concerns'] ?? [],
            'feeding_growth_concern'        => $feedingHistory['growth_concern']    ?? null,
            'social_history'                => $feedingHistory['nutrition_notes']   ?? null,
        ];

        $edu = ClientEducation::where('client_id', $client->id)->first();
        $this->sectionData['F'] = $edu ? [
            'edu_education_level'        => $edu->education_level,
            'edu_school_type'            => $edu->school_type,
            'edu_school_name'            => $edu->school_name,
            'edu_grade_level'            => $edu->grade_level,
            'edu_currently_enrolled'     => $edu->currently_enrolled ? 'yes' : 'no',
            'edu_attendance_challenges'  => $edu->attendance_challenges ? 'yes' : 'no',
            'edu_attendance_notes'       => $edu->attendance_notes,
            'edu_performance_concern'    => $edu->performance_concern ? 'yes' : 'no',
            'edu_performance_notes'      => $edu->performance_notes,
            'edu_employment_status'      => $edu->employment_status,
            'edu_occupation_type'        => $edu->occupation_type,
            'edu_education_notes'        => $edu->education_notes,
        ] : [];

        $scores = $intake->functional_screening_scores ?? [];
        $this->sectionData['G'] = array_merge(
            ['visit_id' => $intake->visit_id, 'func_overall_summary' => $intake->functionalScreening?->overall_summary],
            $this->flattenScreeningAnswers($scores)
        );

        $this->sectionData['H'] = [
            'referral_source'        => $sr['referral_source'] ?? [],
            'referral_contact'       => $sr['referral_contact'] ?? null,
            'reason_for_visit'       => $intake->reason_for_visit,
            'current_concerns'       => $intake->current_concerns,
            'previous_interventions' => $intake->previous_interventions,
            'h_reports_uploaded'     => $this->wrapFileState($intake->uploaded_reports ?? []),
        ];

        $this->sectionData['I'] = [
            'visit_id'             => $intake->visit_id,
            'i_primary_service_id' => $sr['primary_service_id'] ?? null,
            'i_service_categories' => $sr['service_categories'] ?? [],
            'services_selected'    => $sr['service_ids'] ?? [],
            'priority_level'       => $intake->priority_level,
        ];

        // Auto-detect enrolled schemes from client record on first load
        $shaAutoDetected  = !empty($client->sha_number);
        $ncpwdAutoDetected = !empty($client->ncpwd_number);
        $this->sectionData['J'] = [
            'expected_payment_method' => $sr['payment_method'] ?? (
                $shaAutoDetected ? 'sha' : ($ncpwdAutoDetected ? 'ncpwd' : null)
            ),
            'sha_enrolled'            => array_key_exists('sha_enrolled', $sr) ? (bool) $sr['sha_enrolled'] : $shaAutoDetected,
            'ncpwd_covered'           => array_key_exists('ncpwd_covered', $sr) ? (bool) $sr['ncpwd_covered'] : $ncpwdAutoDetected,
            'has_private_insurance'   => (bool) ($sr['has_insurance'] ?? false),
            'payment_notes'           => $sr['payment_notes'] ?? null,
        ];

        // TODO: deferral_reason, deferral_notes, next_appointment_date do not yet exist on
        // the visits table or intake_assessments table — they return null on initial load
        // until the deferral migration is added (Task 8).
        $this->sectionData['K'] = [
            'defer_client'          => $visit->status === 'deferred',
            'deferral_reason'       => $visit->deferral_reason,
            'deferral_notes'        => $visit->deferral_notes,
            'next_appointment_date' => $visit->next_appointment_date,
        ];

        $this->sectionData['L'] = [
            'assessment_summary' => $intake->assessment_summary,
            'recommendations'    => $intake->recommendations,
            'priority_level'     => $intake->priority_level,
            'data_verified'      => $intake->data_verified,
        ];
    }

    /**
     * Wrap raw DB file value(s) into the UUID-keyed format Filament FileUpload expects
     * internally (mirrors what afterStateHydrated produces). This must be done manually
     * because Form::fill() is never called in this custom editor page, so the
     * afterStateHydrated hook never fires on its own.
     *
     * Input:  null | 'path.jpg' | ['path1.jpg', 'path2.jpg'] | [uuid => 'path.jpg']
     * Output: [uuid => 'path.jpg', ...]
     */
    protected function wrapFileState(mixed $value): array
    {
        if (blank($value)) {
            return [];
        }

        $paths = is_string($value)
            ? [$value]
            : array_values(array_filter((array) $value, 'is_string'));

        $result = [];
        foreach ($paths as $path) {
            if ($path !== '') {
                $result[(string) \Illuminate\Support\Str::uuid()] = $path;
            }
        }

        return $result;
    }

    /**
     * Extract a plain file path from FileUpload state after saveUploadedFiles() runs.
     * saveUploadedFiles() produces a UUID-keyed array [{uuid} => 'path.jpg']; this
     * strips the key and returns the bare string (or null) for single-file fields.
     */
    protected function extractSingleFileState(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if (is_string($state)) {
            return $state ?: null;
        }

        if (is_array($state)) {
            $values = array_values($state);
            $first = $values[0] ?? null;
            return is_string($first) ? ($first ?: null) : null;
        }

        return null;
    }

    protected function flattenScreeningAnswers(array $scores): array
    {
        $flat = [];
        $band = $scores['band'] ?? null;
        if (!$band) return $flat;
        foreach ($scores['answers'] ?? [] as $domain => $questions) {
            foreach ($questions as $qKey => $answer) {
                $flat[$qKey === '_notes' ? "g_{$band}_{$domain}_notes" : "g_{$band}_{$domain}_{$qKey}"] = $answer;
            }
        }
        return $flat;
    }

    public function getForms(): array
    {
        return ['sectionBForm','sectionCForm','sectionDForm','sectionEForm','sectionFForm',
                'sectionGForm','sectionHForm','sectionIForm','sectionJForm','sectionKForm','sectionLForm'];
    }

    public function sectionBForm(Form $form): Form
    {
        return $form->statePath('sectionData.B')->schema(IntakeAssessmentResource::sectionBSchema($this->intake?->visit_id));
    }
    public function sectionCForm(Form $form): Form
    {
        return $form->statePath('sectionData.C')->schema(IntakeAssessmentResource::sectionCSchema($this->intake?->visit_id));
    }
    public function sectionDForm(Form $form): Form
    {
        return $form->statePath('sectionData.D')->schema(IntakeAssessmentResource::sectionDSchema($this->intake?->visit_id));
    }
    public function sectionEForm(Form $form): Form
    {
        return $form->statePath('sectionData.E')->schema(IntakeAssessmentResource::sectionESchema($this->intake?->visit_id));
    }
    public function sectionFForm(Form $form): Form
    {
        return $form->statePath('sectionData.F')->schema(IntakeAssessmentResource::sectionFSchema($this->intake?->visit_id));
    }
    public function sectionGForm(Form $form): Form
    {
        return $form->statePath('sectionData.G')->schema(IntakeAssessmentResource::sectionGSchema($this->intake?->visit_id));
    }
    public function sectionHForm(Form $form): Form
    {
        return $form->statePath('sectionData.H')->schema(IntakeAssessmentResource::sectionHSchema($this->intake?->visit_id));
    }
    public function sectionIForm(Form $form): Form
    {
        return $form->statePath('sectionData.I')->schema(IntakeAssessmentResource::sectionISchema($this->intake?->visit_id));
    }
    public function sectionJForm(Form $form): Form
    {
        return $form->statePath('sectionData.J')->schema(IntakeAssessmentResource::sectionJSchema($this->intake?->visit_id));
    }
    public function sectionKForm(Form $form): Form
    {
        return $form->statePath('sectionData.K')->schema(IntakeAssessmentResource::sectionKSchema($this->intake?->visit_id));
    }
    public function sectionLForm(Form $form): Form
    {
        return $form->statePath('sectionData.L')->schema(IntakeAssessmentResource::sectionLSchema($this->intake?->visit_id));
    }

    public function switchSection(string $section): void
    {
        if ($this->activeSection !== 'A' && $this->activeSection !== $section) {
            $this->saveSectionData($this->activeSection);
        }
        $this->activeSection = $section;
    }

    public function prevSection(): void
    {
        $idx = array_search($this->activeSection, $this->sections);
        if ($idx > 0) {
            if ($this->activeSection !== 'A') $this->saveSectionData($this->activeSection);
            $this->activeSection = $this->sections[$idx - 1];
        }
    }

    public function nextSection(): void
    {
        $idx = array_search($this->activeSection, $this->sections);
        if ($idx < count($this->sections) - 1) {
            if ($this->activeSection !== 'A') $this->saveSectionData($this->activeSection);
            $this->activeSection = $this->sections[$idx + 1];
        }
    }

    /**
     * Called by Alpine.js capture div in the blade when any input blurs/changes.
     * Alpine wraps each section form with a 1s debounced listener that calls this method.
     */
    /**
     * Save a section's data.
     * $explicit = true  → called from the Save button; runs form validation and sends a success notification.
     * $explicit = false → called on blur/navigation; saves silently without validation noise.
     */
    public function saveSectionData(string $section, bool $explicit = false): void
    {
        if (!in_array($section, $this->sections) || $section === 'A') return;

        // Run form validation when the user explicitly pressed Save
        if ($explicit) {
            $formMethod = 'section' . $section . 'Form';
            try {
                $this->$formMethod->validate();
            } catch (\Illuminate\Validation\ValidationException $e) {
                $fields = collect($e->errors())->keys()
                    ->map(fn($k) => str_replace(['sectionData.' . $section . '.', 'sectionData.'], '', $k))
                    ->implode(', ');
                Notification::make()->danger()
                    ->title('Please fix validation errors')
                    ->body("Required fields missing: {$fields}")
                    ->send();
                // Still persist partial data so work is not lost, but mark status accordingly
            }
        }

        $this->isSaving = true;
        try {
            $method = 'saveSection' . $section;
            if (method_exists($this, $method)) {
                // Trigger saveUploadedFiles() on any FileUpload components so that
                // temporary uploads are moved to permanent storage and the sectionData
                // property is updated to plain string paths before we read it.
                // We avoid calling getState() on the full form because that dehydrates
                // visible-only fields and can strip data from conditionally-hidden sections.
                $formMethod = 'section' . $section . 'Form';
                foreach ($this->$formMethod->getFlatComponents(withHidden: true) as $component) {
                    if ($component instanceof \Filament\Forms\Components\FileUpload) {
                        $component->saveUploadedFiles();
                    }
                }
                $this->$method($this->sectionData[$section] ?? []);
            }
            $this->updateSectionStatus($section);

            if ($explicit) {
                $label  = $this->sectionLabel[$section] ?? "Section {$section}";
                $status = $this->sectionStatus[$section] ?? 'incomplete';
                if ($status === 'complete') {
                    Notification::make()->success()
                        ->title("{$label} saved")
                        ->body('All required fields complete.')
                        ->send();
                } else {
                    Notification::make()->warning()
                        ->title("{$label} saved (incomplete)")
                        ->body('Some required fields are still missing — section marked as in progress.')
                        ->send();
                }
            }
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Save failed')
                ->body("Section {$section} could not be saved: " . $e->getMessage())
                ->send();
        } finally {
            $this->isSaving = false;
        }
    }

    protected function updateSectionStatus(string $section): void
    {
        $status = $this->computeSectionStatus($section);
        DB::transaction(function () use ($section, $status) {
            $this->intake->refresh();
            $updated = array_merge($this->intake->section_status ?? [], [$section => $status]);
            $this->intake->update(['section_status' => $updated]);
            $this->sectionStatus[$section] = $status;
        });
    }

    protected function computeSectionStatus(string $section): string
    {
        $data     = $this->sectionData[$section] ?? [];
        $required = $this->requiredFields()[$section] ?? [];

        // Section D: marital_status is only shown for clients >= 18.
        // Drop it from the required list for under-18 clients so they can complete the section.
        if ($section === 'D' && in_array('socio_marital_status', $required)) {
            $dob = $this->client?->date_of_birth;
            $ageYears = $dob ? (int) Carbon::parse($dob)->diffInYears(now()) : 999;
            if ($ageYears < 18) {
                $required = array_values(array_diff($required, ['socio_marital_status']));
            }
        }

        if (empty($required)) return 'complete';
        foreach ($required as $field) {
            $val = $data[$field] ?? null;
            if ($val === null || $val === '' || $val === []) return 'in_progress';
        }
        return 'complete';
    }

    protected function requiredFields(): array
    {
        return [
            'A' => [],
            'B' => ['verification_mode'],
            'C' => ['dis_is_disability_known'],
            'D' => ['socio_marital_status', 'socio_primary_language'],
            'E' => ['med_medical_conditions'],
            'F' => ['edu_education_level'],
            'G' => ['func_overall_summary'],
            'H' => ['reason_for_visit'],
            'I' => ['i_primary_service_id'],
            'J' => ['expected_payment_method'],
            'K' => [],  // deferral is optional; auto-completes on first save
            'L' => ['assessment_summary'],
        ];
    }

    #[Computed]
    public function progress(): int
    {
        return count(array_filter($this->sectionStatus, fn($s) => $s === 'complete'));
    }

    #[Computed]
    public function sectionLabel(): array
    {
        return [
            'A' => 'Client Overview',
            'B' => 'ID & Contact',
            'C' => 'Disability & NCPWD',
            'D' => 'Socio-Demographics',
            'E' => 'Medical History',
            'F' => 'Education & Work',
            'G' => 'Functional Screening',
            'H' => 'Presenting Concern',
            'I' => 'Service Plan',
            'J' => 'Payment Pathway',
            'K' => 'Deferral',
            'L' => 'Summary & Finalize',
        ];
    }

    #[Computed]
    public function sectionMeta(): array
    {
        return [
            'A' => ['icon' => 'user',              'description' => 'Auto-completed from client record — read only'],
            'B' => ['icon' => 'identification',    'description' => 'Verify contact details, address, and ID documents'],
            'C' => ['icon' => 'heart',             'description' => 'Disability category, onset, NCPWD registration'],
            'D' => ['icon' => 'home',              'description' => 'Household, language, caregiver, support sources'],
            'E' => ['icon' => 'beaker',            'description' => 'Medical conditions, medications, assistive technology history'],
            'F' => ['icon' => 'academic-cap',      'description' => 'Education level, school enrollment, employment status'],
            'G' => ['icon' => 'chart-bar',         'description' => 'Age-banded functional screening across all domains'],
            'H' => ['icon' => 'chat-bubble-left',  'description' => 'Referral source, reason for visit, current concerns'],
            'I' => ['icon' => 'clipboard-document','description' => 'Primary service, additional services, priority level'],
            'J' => ['icon' => 'banknotes',         'description' => 'Payment method, eligibility status, required documents'],
            'K' => ['icon' => 'calendar',          'description' => 'Defer client if they cannot be served today'],
            'L' => ['icon' => 'check-circle',      'description' => 'Final review, intake summary, finalize and route visit'],
        ];
    }

    // ── Save methods (stubs — implemented in Tasks 5–8) ───────────────────────
    protected function saveSectionB(array $data): void
    {
        $clientUpdates = array_filter([
            'national_id'              => $data['b_national_id']              ?? null,
            'birth_certificate_number' => $data['b_birth_certificate']        ?? null,
            'phone_primary'            => $data['b_phone_primary']            ?? null,
            'phone_secondary'          => $data['b_phone_secondary']          ?? null,
            'email'                    => $data['b_email']                    ?? null,
            'preferred_communication'  => $data['b_preferred_communication']  ?? null,
            'consent_to_sms'           => isset($data['b_consent_to_sms']) ? (bool) $data['b_consent_to_sms'] : null,
            'sha_number'               => $data['b_sha_number']               ?? null,
            'ncpwd_number'             => $data['b_ncpwd_number']             ?? null,
            'county_id'                => $data['b_county_id']                ?? null,
            'sub_county_id'            => $data['b_sub_county_id']            ?? null,
            'ward_id'                  => $data['b_ward_id']                  ?? null,
            'primary_address'          => $data['b_primary_address']          ?? null,
            'landmark'                 => $data['b_landmark']                 ?? null,
        ], fn($v) => $v !== null);

        if ($clientUpdates) $this->client->update($clientUpdates);

        $this->intake->update([
            'verification_mode'  => $data['verification_mode']  ?? null,
            'verification_notes' => $data['verification_notes'] ?? null,
        ]);

        // Cross-section sync: keep Section C's NCPWD fields in step with Section B's number field.
        // If a number is present → mark registered = 'yes' and mirror the number.
        // If the number is cleared → reset the registered flag so the user must re-confirm in C.
        $ncpwdNumber = $data['b_ncpwd_number'] ?? null;
        if (!empty($ncpwdNumber)) {
            $this->sectionData['C']['dis_ncpwd_number']   = $ncpwdNumber;
            // Only upgrade to 'yes'; don't overwrite an explicit 'no' / 'unknown' the user set in C.
            if (empty($this->sectionData['C']['dis_ncpwd_registered'] ?? null)) {
                $this->sectionData['C']['dis_ncpwd_registered'] = 'yes';
            }
        } else {
            // Number removed in B — clear the mirror in C so C doesn't show stale data.
            $this->sectionData['C']['dis_ncpwd_number'] = null;
            if (($this->sectionData['C']['dis_ncpwd_registered'] ?? null) === 'yes') {
                $this->sectionData['C']['dis_ncpwd_registered'] = null;
            }
        }
    }

    protected function saveSectionC(array $data): void
    {
        if (empty($data['dis_is_disability_known'])) return;

        ClientDisability::updateOrCreate(
            ['client_id' => $this->client->id],
            [
                'is_disability_known'        => true,
                'disability_categories'      => $data['dis_disability_categories']     ?? [],
                'onset'                      => $data['dis_onset']                      ?? null,
                'level_of_functioning'       => $data['dis_level_of_functioning']       ?? null,
                'disability_notes'           => $data['dis_disability_notes']           ?? null,
                'evidence_files'             => array_values($data['dis_evidence_files'] ?? []),
                'ncpwd_registered'           => $data['dis_ncpwd_registered']           ?? null,
                'ncpwd_verification_status'  => $data['dis_ncpwd_verification_status']  ?? null,
            ]
        );
        // Always sync ncpwd_number to the client record when a number is provided
        if (!empty($data['dis_ncpwd_number'])) {
            $this->client->update(['ncpwd_number' => $data['dis_ncpwd_number']]);
        }
    }

    protected function saveSectionD(array $data): void
    {
        // marital_status is an ENUM — store only the enum value; free text in separate column
        $maritalStatus = $data['socio_marital_status'] ?? null;

        // living_arrangement is an ENUM — same pattern
        $livingArrangement = $data['socio_living_arrangement'] ?? null;

        // primary_caregiver is a VARCHAR — store 'other: <text>' for free-text case
        $primaryCaregiver = ($data['socio_primary_caregiver'] ?? null) === 'other'
            ? 'other: ' . ($data['socio_caregiver_other'] ?? 'unspecified')
            : ($data['socio_primary_caregiver'] ?? null);

        // primary_language is a VARCHAR — store 'other: <text>' for free-text case
        $primaryLanguage = ($data['socio_primary_language'] ?? null) === 'other'
            ? 'other: ' . ($data['socio_language_other'] ?? 'unspecified')
            : ($data['socio_primary_language'] ?? null);

        ClientSocioDemographic::updateOrCreate(
            ['client_id' => $this->client->id],
            [
                'marital_status'           => $maritalStatus,
                'marital_status_other'     => $data['socio_marital_other']          ?? null,
                'living_arrangement'       => $livingArrangement,
                'living_arrangement_other' => $data['socio_living_other']           ?? null,
                'household_size'           => $data['socio_household_size']         ?? null,
                'primary_caregiver'        => $primaryCaregiver,
                'source_of_support'        => $data['socio_source_of_support']      ?? [],
                'other_support_source'     => $data['socio_other_support']          ?? null,
                'school_enrolled'          => $data['socio_school_enrolled']        ?? null,
                'primary_language'         => $primaryLanguage,
                'other_languages'          => !empty($data['socio_other_languages']) ? [$data['socio_other_languages']] : [],
                'accessibility_at_home'    => $data['socio_accessibility_at_home']  ?? null,
                'socio_notes'              => $data['socio_notes']                  ?? null,
            ]
        );
    }

    protected function saveSectionE(array $data): void
    {
        $medConditions = $data['med_medical_conditions'] ?? [];
        if (!empty($data['med_conditions_other'])) {
            $medConditions[] = 'other: ' . $data['med_conditions_other'];
        }

        $prevAssessments = $data['med_previous_assessments'] ?? [];
        if (!empty($data['med_previous_assessments_other'])) {
            $prevAssessments[] = 'other: ' . $data['med_previous_assessments_other'];
        }

        $devConcerns = array_map(
            fn($v) => $v === 'other' && !empty($data['peri_developmental_concerns_other'])
                ? 'other: ' . $data['peri_developmental_concerns_other'] : $v,
            $data['peri_developmental_concerns'] ?? []
        );

        $feedingConcernsRaw = $data['feeding_swallowing_concerns'] ?? [];
        if (in_array('other', $feedingConcernsRaw, true) && !empty($data['feeding_swallowing_concerns_other'])) {
            $feedingConcernsRaw = array_filter($feedingConcernsRaw, fn($v) => $v !== 'other');
            $feedingConcernsRaw[] = 'other: ' . $data['feeding_swallowing_concerns_other'];
        }
        $feedingHistory = array_filter([
            'feeding_method'      => $data['feeding_method']             ?? null,
            'diet_appetite'       => $data['feeding_diet_appetite']      ?? null,
            'foods_brief'         => $data['feeding_diet_foods_brief']   ?? null,
            'swallowing_concerns' => $feedingConcernsRaw ?: null,
            'growth_concern'      => $data['feeding_growth_concern']     ?? null,
            'nutrition_notes'     => $data['social_history']             ?? null,
        ], fn($v) => $v !== null && $v !== []);

        // Save E2 current AT devices to disability record
        if ($data['e2_has_at'] === 'yes' || !empty($data['e2_current_devices'])) {
            $atDevices = array_map(function ($device) {
                if (($device['device_type'] ?? null) === 'other' && !empty($device['device_type_other'])) {
                    $device['device_type'] = 'other: ' . $device['device_type_other'];
                }
                if (($device['source'] ?? null) === 'other' && !empty($device['source_other'])) {
                    $device['source'] = 'other: ' . $device['source_other'];
                }
                return $device;
            }, $data['e2_current_devices'] ?? []);

            ClientDisability::updateOrCreate(
                ['client_id' => $this->client->id],
                ['assistive_technology' => $atDevices]
            );
        }

        $periComplications = $data['peri_pregnancy_complications'] ?? [];
        if (!empty($data['peri_pregnancy_complications_other'])) {
            $periComplications = array_map(
                fn($v) => $v === 'other' ? 'other: ' . $data['peri_pregnancy_complications_other'] : $v,
                $periComplications
            );
        }
        $neonatalCare = $data['peri_neonatal_care'] ?? [];
        if (!empty($data['peri_neonatal_care_other'])) {
            $neonatalCare = array_map(
                fn($v) => $v === 'other' ? 'other: ' . $data['peri_neonatal_care_other'] : $v,
                $neonatalCare
            );
        }
        $earlyMedical = $data['peri_early_medical_issues'] ?? [];
        if (!empty($data['peri_early_medical_issues_other'])) {
            $earlyMedical = array_map(
                fn($v) => $v === 'other' ? 'other: ' . $data['peri_early_medical_issues_other'] : $v,
                $earlyMedical
            );
        }
        $placeOfBirth = ($data['peri_place_of_birth'] ?? null) === 'other'
            ? 'other: ' . ($data['peri_place_of_birth_other'] ?? 'unspecified')
            : ($data['peri_place_of_birth'] ?? null);

        $perinatHistory = array_filter([
            'pregnancy_complications' => $periComplications ?: null,
            'place_of_birth'          => $placeOfBirth,
            'mode_of_delivery'        => $data['peri_mode_of_delivery']  ?? null,
            'gestation_weeks'         => $data['peri_gestation_weeks']   ?? null,
            'birth_weight_kg'         => $data['peri_birth_weight_kg']   ?? null,
            'neonatal_care'           => $neonatalCare ?: null,
            'early_medical_issues'    => $earlyMedical ?: null,
            'developmental_concerns'  => $devConcerns ?: null,
        ], fn($v) => $v !== null && $v !== []);

        $immunizationRecords = array_filter([
            'epi_status'                  => $data['imm_epi_status']                  ?? [],
            'epi_card_seen'               => $data['imm_epi_card_seen']               ?? null,
            'epi_card_photo'              => $this->extractSingleFileState($data['imm_epi_card_photo'] ?? null),
            'missed_doses'                => $data['imm_missed_doses']                ?? null,
            'missed_doses_which'          => $data['imm_missed_doses_which']          ?? null,
            'recent_illness_post_vaccine' => $data['imm_recent_illness_post_vaccine'] ?? null,
            'recent_illness_notes'        => $data['imm_recent_illness_notes']        ?? null,
        ], fn($v) => $v !== null && $v !== []);

        $atNeeds = array_filter([
            'needs_at'         => $data['e2_needs_at']         ?? null,
            'needs_categories' => $data['e2_needs_categories'] ?? [],
            'needs_priority'   => $data['e2_needs_priority']   ?? null,
            'needs_notes'      => $data['e2_needs_notes']      ?? null,
            'satisfaction'     => $data['e2_satisfaction']     ?? null,
        ], fn($v) => $v !== null && $v !== []);

        ClientMedicalHistory::updateOrCreate(
            ['client_id' => $this->client->id],
            [
                'medical_conditions'           => $medConditions ?: null,
                'current_medications'          => $data['med_current_medications']    ?? null,
                'surgical_history'             => $data['med_surgical_history']       ?? null,
                'family_medical_history'       => $data['med_family_medical_history'] ?? null,
                'immunization_status'          => $data['med_immunization_status']    ?? null,
                'immunization_records'         => $immunizationRecords ?: null,
                'feeding_history'              => $feedingHistory ?: null,
                'previous_assessments'         => $prevAssessments,
                'developmental_concerns'       => $devConcerns,
                'developmental_concerns_notes' => $data['developmental_history']      ?? null,
                'assistive_devices_history'    => $data['e2_previous_devices']        ?? [],
                'perinatal_history'            => $perinatHistory ?: null,
                'allergies'                    => $data['allergy_items']              ?? [],
                'assistive_technology_needs'   => $atNeeds ?: null,
            ]
        );

        // family_history lives on the intake_assessments record itself
        $this->intake->update(['family_history' => $data['family_history'] ?? null]);
    }

    protected function saveSectionF(array $data): void
    {
        // employment_status is an ENUM — store only the enum value, not 'other: ...'
        $employmentStatus = $data['edu_employment_status'] ?? null;

        ClientEducation::updateOrCreate(
            ['client_id' => $this->client->id],
            [
                'education_level'        => $data['edu_education_level']       ?? null,
                'school_type'            => $data['edu_school_type']           ?? null,
                'school_name'            => $data['edu_school_name']           ?? null,
                'grade_level'            => $data['edu_grade_level']           ?? null,
                'currently_enrolled'     => ($data['edu_currently_enrolled']   ?? null) === 'yes',
                'attendance_challenges'  => ($data['edu_attendance_challenges'] ?? null) === 'yes',
                'attendance_notes'       => $data['edu_attendance_notes']      ?? null,
                'performance_concern'    => ($data['edu_performance_concern']  ?? null) === 'yes',
                'performance_notes'      => $data['edu_performance_notes']     ?? null,
                'employment_status'      => $employmentStatus,
                'occupation_type'        => $data['edu_occupation_type']       ?? null,
                'education_notes'        => $data['edu_education_notes']       ?? null,
            ]
        );
    }

    protected function saveSectionG(array $data): void
    {
        // Resolve age directly from client DOB — avoids the Get $get dependency
        $ageMonths = $this->client->date_of_birth
            ? (int) Carbon::parse($this->client->date_of_birth)->diffInMonths(now())
            : 9999;
        $bandKey = IntakeAssessmentResource::detectBandKey($ageMonths);

        // Rebuild screening answers from flat field names back into band→domain→question structure
        $screeningAnswers = [];
        $allQuestions = IntakeAssessmentResource::screeningQuestions();
        if (isset($allQuestions[$bandKey])) {
            foreach ($allQuestions[$bandKey]['domains'] as $domainKey => $domain) {
                foreach ($domain['questions'] as $qKey => $q) {
                    $fieldName = "g_{$bandKey}_{$domainKey}_{$qKey}";
                    $screeningAnswers[$domainKey][$qKey] = $data[$fieldName] ?? null;
                }
                $notesField = "g_{$bandKey}_{$domainKey}_notes";
                if (!empty($data[$notesField])) {
                    $screeningAnswers[$domainKey]['_notes'] = $data[$notesField];
                }
            }
        }

        $scores = ['band' => $bandKey, 'age_months' => $ageMonths, 'answers' => $screeningAnswers];

        $this->intake->update(['functional_screening_scores' => $scores]);

        FunctionalScreening::updateOrCreate(
            ['intake_assessment_id' => $this->intake->id],
            [
                'client_id'       => $this->client->id,
                'overall_summary' => $data['func_overall_summary'] ?? null,
            ]
        );
        // NOTE: Auto-referrals from functional screening are created only in finalize()
    }
    protected function saveSectionH(array $data): void
    {
        $sources = $data['referral_source'] ?? [];
        if (in_array('other', $sources, true) && !empty($data['referral_source_other'])) {
            $sources = array_filter($sources, fn($v) => $v !== 'other');
            $sources[] = 'other: ' . $data['referral_source_other'];
        }
        $sr = $this->intake->services_required ?? [];
        $sr['referral_source']  = array_values($sources);
        $sr['referral_contact'] = $data['referral_contact'] ?? null;
        $this->intake->update([
            'reason_for_visit'       => $data['reason_for_visit']       ?? null,
            'current_concerns'       => $data['current_concerns']       ?? null,
            'previous_interventions' => $data['previous_interventions'] ?? null,
            'services_required'      => $sr,
            'uploaded_reports'       => array_values($data['h_reports_uploaded'] ?? []),
        ]);
    }

    protected function saveSectionI(array $data): void
    {
        $sr = $this->intake->services_required ?? [];
        $sr['primary_service_id'] = $data['i_primary_service_id'] ?? null;
        $sr['service_categories'] = $data['i_service_categories'] ?? [];
        $sr['service_ids']        = $data['services_selected']    ?? [];
        $this->intake->update([
            'services_required' => $sr,
            'priority_level'    => (int) ($data['priority_level'] ?? 3),
        ]);
    }

    protected function saveSectionJ(array $data): void
    {
        $sr = $this->intake->services_required ?? [];
        $sr['payment_method'] = $data['expected_payment_method']   ?? null;
        $sr['sha_enrolled']   = (bool) ($data['sha_enrolled']      ?? false);
        $sr['ncpwd_covered']  = (bool) ($data['ncpwd_covered']     ?? false);
        $sr['has_insurance']  = (bool) ($data['has_private_insurance'] ?? false);
        $sr['payment_notes']  = $data['payment_notes']             ?? null;
        $this->intake->update(['services_required' => $sr]);
    }

    protected function saveSectionK(array $data): void
    {
        if (!empty($data['defer_client'])) {
            $deferralReason = $data['deferral_reason'] ?? null;
            if ($deferralReason === 'other' && !empty($data['deferral_reason_other'])) {
                $deferralReason = 'other: ' . $data['deferral_reason_other'];
            }
            $updateData = array_filter([
                'status'          => 'deferred',
                'deferral_reason' => $deferralReason,
                'deferral_notes'  => $data['deferral_notes']        ?? null,
                'deferred_at'     => now(),
            ], fn($v) => $v !== null);
            // next_appointment_date handled separately to avoid null-filtering a valid date
            if (!empty($data['next_appointment_date'])) {
                $updateData['next_appointment_date'] = $data['next_appointment_date'];
            }
            $this->intake->visit->update($updateData);
        }
        // Section K has no required fields — auto-completes on first save (handled by computeSectionStatus)
    }

    protected function saveSectionL(array $data): void
    {
        $this->intake->update([
            'assessment_summary'  => $data['assessment_summary'] ?? null,
            'recommendations'     => $data['recommendations']    ?? null,
            'priority_level'      => (int) ($data['priority_level'] ?? 3),
            'data_verified'       => (bool) ($data['data_verified'] ?? false),
        ]);
    }

    // ── Finalize ───────────────────────────────────────────────────────────────
    public function finalize(): void
    {
        // Guard: all sections complete
        $incomplete = array_keys(array_filter($this->sectionStatus, fn ($s) => $s !== 'complete'));
        if (! empty($incomplete)) {
            Notification::make()->danger()
                ->title('Cannot finalize')
                ->body('Incomplete sections: ' . implode(', ', $incomplete))
                ->send();
            return;
        }

        // Guard: deferred visits cannot be finalized
        if ($this->intake->visit->status === 'deferred') {
            Notification::make()->warning()
                ->title('Visit is deferred')
                ->body('Resolve the deferral before finalizing.')
                ->send();
            return;
        }

        DB::transaction(function () {
            $intake = $this->intake->refresh()->load(['visit.branch', 'functionalScreening']);
            $visit  = $intake->visit;
            $client = $this->client;
            $sr     = $intake->services_required ?? [];
            $scores = $intake->functional_screening_scores ?? [];

            // ── Auto-referrals from functional screening ──────────────────
            $bandKey          = $scores['band'] ?? null;
            $screeningAnswers = $scores['answers'] ?? [];

            if ($bandKey && isset(IntakeAssessmentResource::screeningQuestions()[$bandKey])) {
                $allQuestions = IntakeAssessmentResource::screeningQuestions();
                $bandDomains  = $allQuestions[$bandKey]['domains'];
                $domainSvcMap = IntakeAssessmentResource::screeningDomainServiceMap();
                $deptNames    = array_unique(array_column($domainSvcMap, 'department'));
                $deptIds      = Department::whereIn('name', $deptNames)->pluck('id', 'name')->all();
                $deptMinPrice = Service::where('is_active', true)
                    ->whereIn('department_id', array_values($deptIds))
                    ->selectRaw('department_id, MIN(base_price) as min_price')
                    ->groupBy('department_id')
                    ->pluck('min_price', 'department_id')
                    ->all();
                $servicePricesByDept = [];
                foreach ($deptIds as $deptName => $deptId) {
                    if (isset($deptMinPrice[$deptId])) {
                        $servicePricesByDept[$deptName] = $deptMinPrice[$deptId];
                    }
                }

                $createdSvcPoints = [];
                foreach ($bandDomains as $domainKey => $domain) {
                    $route = $domainSvcMap[$domainKey] ?? null;
                    if (! $route) {
                        continue;
                    }
                    $spSlug = $route['service_point'];
                    if (isset($createdSvcPoints[$spSlug])) {
                        continue;
                    }
                    $domainAnswers = $screeningAnswers[$domainKey] ?? [];
                    $flagged       = [];
                    $highPriority  = false;
                    foreach ($domain['questions'] as $qKey => $q) {
                        $answer = $domainAnswers[$qKey] ?? null;
                        if ($answer === ($q['flag'] ?? null)) {
                            $flagged[] = $q['text'];
                            if (($q['priority'] ?? 'routine') === 'high') {
                                $highPriority = true;
                            }
                        }
                    }
                    if (empty($flagged)) {
                        continue;
                    }
                    $createdSvcPoints[$spSlug] = true;
                    AssessmentAutoReferral::create([
                        'form_response_id' => null,   // nullable — auto-referrals from functional screening have no form response
                        'client_id'        => $client->id,
                        'visit_id'         => $visit->id,
                        'service_point'    => $spSlug,
                        'department'       => $route['department'],
                        'priority'         => $highPriority ? 'high' : 'routine',
                        'reason'           => "Functional screening — {$domain['label']} (band: {$bandKey}): "
                                              . implode('; ', $flagged),
                        'trigger_data'     => [
                            'domain'           => $domainKey,
                            'band'             => $bandKey,
                            'flagged_questions' => $flagged,
                            'estimated_cost'   => $servicePricesByDept[$route['department']] ?? null,
                            'source'           => 'intake_functional_screening',
                        ],
                        'status' => 'pending',
                    ]);
                }
            }

            // ── Service Bookings ──────────────────────────────────────────
            // Delete any from a prior failed finalize attempt, then recreate
            ServiceBooking::where('visit_id', $visit->id)
                ->where('notes', 'intake-editor')
                ->forceDelete();

            $primaryServiceId = $sr['primary_service_id'] ?? null;
            $allServiceIds    = array_unique(array_filter(array_merge(
                $primaryServiceId ? [$primaryServiceId] : [],
                $sr['service_ids'] ?? []
            )));

            foreach (Service::whereIn('id', $allServiceIds)->get() as $service) {
                ServiceBooking::create([
                    'visit_id'       => $visit->id,
                    'client_id'      => $client->id,
                    'service_id'     => $service->id,
                    'department_id'  => $service->department_id,
                    'quantity'       => 1,
                    'unit_price'     => $service->base_price ?? 0,
                    'total_price'    => $service->base_price ?? 0,
                    'booking_date'   => today(),
                    'payment_status' => 'pending',
                    'service_status' => 'scheduled',
                    'status'         => 'pending',
                    'booked_by'      => Auth::id(),
                    'notes'          => 'intake-editor',
                ]);
            }

            // ── Invoice ──────────────────────────────────────────────────
            $paymentMethod = $sr['payment_method'] ?? 'cash';
            $hasSponsor    = in_array($paymentMethod, ['sha', 'ncpwd', 'insurance_private', 'waiver', 'combination'], true);
            $branchCode    = $visit->branch ? strtoupper(substr($visit->branch->name, 0, 3)) : 'HQ';
            $invYear       = now()->format('Y');
            $invMonth      = now()->format('m');
            $invSeq        = Invoice::whereYear('created_at', $invYear)
                ->whereMonth('created_at', $invMonth)
                ->count() + 1;
            $invoiceNumber = "{$branchCode}/INV/{$invYear}/{$invMonth}/"
                             . str_pad($invSeq, 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'visit_id'        => $visit->id,
                'client_id'       => $client->id,
                'branch_id'       => $visit->branch_id,
                'invoice_number'  => $invoiceNumber,
                'payment_pathway' => $paymentMethod,
                'status'          => 'pending',
                'generated_by'    => Auth::id(),
                'notes'           => $sr['payment_notes'] ?? null,
                'total_amount'    => 0,
                'subtotal'        => 0,
                'covered_amount'  => 0,
                'balance_due'     => 0,
            ]);

            // Find the right price record for this service + insurer
            $insuranceProviderId = $sr['insurance_provider_id'] ?? null;

            $total = $totalCovered = $totalClient = 0.0;

            $bookings = ServiceBooking::where('visit_id', $visit->id)
                ->where('notes', 'intake-editor')
                ->with('service')
                ->get();

            foreach ($bookings as $booking) {
                $base = (float) ($booking->service?->base_price ?? 0);

                $price = ($insuranceProviderId && $booking->service)
                    ? ServiceInsurancePrice::where('service_id', $booking->service->id)
                          ->where('insurance_provider_id', $insuranceProviderId)
                          ->active()
                          ->first()
                    : null;

                $clientPays = $price ? (float) $price->client_copay   : $base;
                $covered    = $price ? (float) $price->covered_amount : 0.0;

                InvoiceItem::create([
                    'invoice_id'              => $invoice->id,
                    'service_booking_id'      => $booking->id,
                    'service_id'              => $booking->service_id,
                    'department_id'           => $booking->department_id,
                    'description'             => $booking->service?->name ?? 'Service',
                    'quantity'                => 1,
                    'unit_price'              => $base,
                    'subtotal'                => $base,
                    'total'                   => $base,
                    'covered_amount'          => $covered,
                    'insurance_covered_amount' => $covered,
                    'client_copay_amount'     => $clientPays,
                ]);

                $total        += $base;
                $totalCovered += $covered;
                $totalClient  += $clientPays;
            }

            $invoice->update([
                'subtotal'            => $total,
                'total_amount'        => $total,
                'covered_amount'      => $totalCovered,
                'total_sponsor_amount' => $totalCovered,
                'total_client_amount'  => $totalClient,
                'balance_due'         => $totalClient,
                'has_sponsor'         => $hasSponsor,
            ]);

            // ── Mark finalized ────────────────────────────────────────────
            $intake->update([
                'assessed_by'  => Auth::id(),
                'is_finalized' => true,
                'finalized_at' => now(),
            ]);

            // ── Route visit ───────────────────────────────────────────────
            $visit->completeStage();
            if ($hasSponsor) {
                $visit->moveToStage('billing');
                $routeLabel = 'Payment Admin (' . strtoupper($paymentMethod) . ')';
            } else {
                $visit->moveToStage('queue');
                $routeLabel = 'Cashier — KES ' . number_format($totalClient, 2);
            }

            Notification::make()->success()
                ->title('Intake Finalized')
                ->body("{$client->full_name} → {$routeLabel}. Invoice #{$invoiceNumber} created.")
                ->send();
        });

        $this->redirect(route('filament.admin.resources.intake-queues.index'));
    }
}
