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
use Carbon\Carbon;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class IntakeAssessmentEditor extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.intake-assessment-editor';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Intake Assessment Editor';

    #[Url]
    public int $intakeId = 0;

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
        abort_unless(static::canAccess(), 403);

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
        ];

        $disability = ClientDisability::where('client_id', $client->id)->first();
        $this->sectionData['C'] = $disability ? [
            'dis_is_disability_known'   => $disability->is_disability_known,
            'dis_disability_categories' => $disability->disability_categories ?? [],
            'dis_onset'                 => $disability->onset,
            'dis_level_of_functioning'  => $disability->level_of_functioning,
            'e2_current_devices'        => $disability->assistive_technology ?? [],
            'dis_disability_notes'      => $disability->disability_notes,
        ] : [];

        $socio = ClientSocioDemographic::where('client_id', $client->id)->first();
        $this->sectionData['D'] = $socio ? [
            'socio_marital_status'        => $socio->marital_status,
            'socio_living_arrangement'    => $socio->living_arrangement,
            'socio_household_size'        => $socio->household_size,
            'socio_primary_caregiver'     => $socio->primary_caregiver,
            'socio_source_of_support'     => $socio->source_of_support ?? [],
            'socio_primary_language'      => $socio->primary_language,
            'socio_other_languages'       => $socio->other_languages[0] ?? null,
            'socio_accessibility_at_home' => $socio->accessibility_at_home,
            'socio_notes'                 => $socio->socio_notes,
        ] : [];

        $med = ClientMedicalHistory::where('client_id', $client->id)->first();
        $this->sectionData['E'] = $med ? [
            'med_medical_conditions'     => $med->medical_conditions ?? [],
            'med_current_medications'    => $med->current_medications,
            'med_surgical_history'       => $med->surgical_history,
            'med_family_medical_history' => $med->family_medical_history,
            'med_immunization_status'    => $med->immunization_status,
            'med_previous_assessments'   => $med->previous_assessments ?? [],
            'peri_developmental_concerns'=> $med->developmental_concerns ?? [],
        ] : [];

        $edu = ClientEducation::where('client_id', $client->id)->first();
        $this->sectionData['F'] = $edu ? [
            'edu_education_level'    => $edu->education_level,
            'edu_school_type'        => $edu->school_type,
            'edu_school_name'        => $edu->school_name,
            'edu_grade_level'        => $edu->grade_level,
            'edu_currently_enrolled' => $edu->currently_enrolled ? 'yes' : 'no',
            'edu_employment_status'  => $edu->employment_status,
            'edu_occupation_type'    => $edu->occupation_type,
            'edu_education_notes'    => $edu->education_notes,
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
        ];

        $this->sectionData['I'] = [
            'visit_id'             => $intake->visit_id,
            'i_primary_service_id' => $sr['primary_service_id'] ?? null,
            'i_service_categories' => $sr['service_categories'] ?? [],
            'services_selected'    => $sr['service_ids'] ?? [],
            'priority_level'       => $intake->priority_level,
        ];

        $this->sectionData['J'] = [
            'expected_payment_method' => $sr['payment_method'] ?? null,
            'sha_enrolled'            => $sr['sha_enrolled'] ?? false,
            'ncpwd_covered'           => $sr['ncpwd_covered'] ?? false,
            'has_private_insurance'   => $sr['has_insurance'] ?? false,
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
        $this->activeSection = $section;
    }

    public function prevSection(): void
    {
        $idx = array_search($this->activeSection, $this->sections);
        if ($idx > 0) $this->activeSection = $this->sections[$idx - 1];
    }

    public function nextSection(): void
    {
        $idx = array_search($this->activeSection, $this->sections);
        if ($idx < count($this->sections) - 1) $this->activeSection = $this->sections[$idx + 1];
    }

    /**
     * Called by Alpine.js capture div in the blade when any input blurs/changes.
     * Alpine wraps each section form with a 1s debounced listener that calls this method.
     */
    public function saveSectionData(string $section): void
    {
        if (!in_array($section, $this->sections) || $section === 'A') return;
        $this->isSaving = true;
        try {
            $method = 'saveSection' . $section;
            if (method_exists($this, $method)) {
                $this->$method($this->sectionData[$section] ?? []);
            }
            $this->updateSectionStatus($section);
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Autosave failed')
                ->body("Section {$section} could not be saved. Please try again.")
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

    public function getProgressProperty(): int
    {
        return count(array_filter($this->sectionStatus, fn($s) => $s === 'complete'));
    }

    public function getSectionLabelProperty(): array
    {
        return [
            'A' => 'Client Overview',   'B' => 'ID & Contact',
            'C' => 'Disability',        'D' => 'Socio-Demographics',
            'E' => 'Medical History',   'F' => 'Education',
            'G' => 'Funct. Screening',  'H' => 'Presenting Concern',
            'I' => 'Service Plan',      'J' => 'Payment',
            'K' => 'Deferral',          'L' => 'Summary',
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
    }

    protected function saveSectionC(array $data): void
    {
        if (empty($data['dis_is_disability_known'])) return;
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
            [
                'is_disability_known'   => true,
                'disability_categories' => $data['dis_disability_categories'] ?? [],
                'onset'                 => $data['dis_onset']                 ?? null,
                'level_of_functioning'  => $data['dis_level_of_functioning']  ?? null,
                'assistive_technology'  => $atDevices,
                'disability_notes'      => $data['dis_disability_notes']      ?? null,
            ]
        );
        if (($data['dis_ncpwd_registered'] ?? null) === 'yes' && !empty($data['dis_ncpwd_number'])) {
            $this->client->update(['ncpwd_number' => $data['dis_ncpwd_number']]);
        }
    }

    protected function saveSectionD(array $data): void
    {
        // marital_status is an ENUM — store only the enum value, not 'other: ...'
        $maritalStatus = $data['socio_marital_status'] ?? null;

        // primary_language is a VARCHAR — safe to store free-text detail
        $primaryLanguage = ($data['socio_primary_language'] ?? null) === 'other'
            ? 'other: ' . ($data['socio_language_other'] ?? 'unspecified')
            : ($data['socio_primary_language'] ?? null);

        ClientSocioDemographic::updateOrCreate(
            ['client_id' => $this->client->id],
            [
                'marital_status'     => $maritalStatus,
                'living_arrangement' => $data['socio_living_arrangement'] ?? null,
                'household_size'     => $data['socio_household_size']     ?? null,
                'source_of_support'  => $data['socio_source_of_support']  ?? [],
                'primary_language'   => $primaryLanguage,
                'other_languages'    => !empty($data['socio_other_languages']) ? [$data['socio_other_languages']] : [],
                'socio_notes'        => $data['socio_notes']               ?? null,
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
            'feeding_method'      => $data['feeding_method']          ?? null,
            'diet_appetite'       => $data['feeding_diet_appetite']   ?? null,
            'swallowing_concerns' => $feedingConcernsRaw ?: null,
            'growth_concern'      => $data['feeding_growth_concern']  ?? null,
            'nutrition_notes'     => $data['feeding_nutrition_notes'] ?? null,
        ], fn($v) => $v !== null && $v !== []);

        ClientMedicalHistory::updateOrCreate(
            ['client_id' => $this->client->id],
            [
                'medical_conditions'           => $medConditions ?: null,
                'current_medications'          => $data['med_current_medications']    ?? null,
                'surgical_history'             => $data['med_surgical_history']       ?? null,
                'family_medical_history'       => $data['med_family_medical_history'] ?? null,
                'immunization_status'          => $data['med_immunization_status']    ?? null,
                'feeding_history'              => $feedingHistory ?: null,
                'previous_assessments'         => $prevAssessments,
                'developmental_concerns'       => $devConcerns,
                'developmental_concerns_notes' => $data['developmental_history']      ?? null,
                'assistive_devices_history'    => $data['e2_previous_devices']        ?? [],
            ]
        );
    }

    protected function saveSectionF(array $data): void
    {
        // employment_status is an ENUM — store only the enum value, not 'other: ...'
        $employmentStatus = $data['edu_employment_status'] ?? null;

        ClientEducation::updateOrCreate(
            ['client_id' => $this->client->id],
            [
                'education_level'    => $data['edu_education_level'] ?? null,
                'school_type'        => $data['edu_school_type']     ?? null,
                'school_name'        => $data['edu_school_name']     ?? null,
                'grade_level'        => $data['edu_grade_level']     ?? null,
                'currently_enrolled' => ($data['edu_currently_enrolled'] ?? null) === 'yes',
                'employment_status'  => $employmentStatus,
                'occupation_type'    => $data['edu_occupation_type'] ?? null,
                'education_notes'    => $data['edu_education_notes'] ?? null,
            ]
        );
    }

    protected function saveSectionG(array $data): void {}
    protected function saveSectionH(array $data): void {}
    protected function saveSectionI(array $data): void {}
    protected function saveSectionJ(array $data): void {}
    protected function saveSectionK(array $data): void {}
    protected function saveSectionL(array $data): void {}

    // ── Finalize (implemented in Task 9) ──────────────────────────────────────
    public function finalize(): void {}
}
