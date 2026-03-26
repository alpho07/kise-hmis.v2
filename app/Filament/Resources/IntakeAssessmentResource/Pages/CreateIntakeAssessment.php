<?php

namespace App\Filament\Resources\IntakeAssessmentResource\Pages;

use App\Filament\Resources\IntakeAssessmentResource;
use App\Models\AssessmentAutoReferral;
use App\Models\ClientAllergy;
use App\Models\ClientDisability;
use App\Models\ClientDocument;
use App\Models\ClientEducation;
use App\Models\ClientMedicalHistory;
use App\Models\ClientSocioDemographic;
use App\Models\Department;
use App\Models\FunctionalScreening;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\Visit;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateIntakeAssessment extends CreateRecord
{
    protected static string $resource = IntakeAssessmentResource::class;

    protected function getRedirectUrl(): string
    {
        return route('filament.admin.resources.intake-queues.index');
    }

    // Strip non-intake_assessments fields; set required system fields
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $visitId = $data['visit_id'] ?? request()->query('visit');
        $visit   = Visit::with('client')->findOrFail($visitId);
        $client  = $visit->client;

        // Determine age band from client DOB
        $ageMonths = $client->date_of_birth
            ? (int) Carbon::parse($client->date_of_birth)->diffInMonths(now())
            : 9999;
        $bandKey = IntakeAssessmentResource::detectBandKey($ageMonths);

        // Collect screening answers for the detected band only
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

        $functionalScreeningScores = [
            'band'       => $bandKey,
            'age_months' => $ageMonths,
            'answers'    => $screeningAnswers,
        ];

        return [
            'visit_id'                    => $visit->id,
            'client_id'                   => $client->id,
            'branch_id'                   => Auth::user()->branch_id,
            'verification_mode'           => $data['verification_mode']       ?? 'new_client',
            'verification_notes'          => $data['verification_notes']      ?? null,
            'data_verified'               => (bool) ($data['data_verified']   ?? true),
            'reason_for_visit'            => $data['reason_for_visit']        ?? null,
            'previous_interventions'      => $data['previous_interventions']  ?? null,
            'current_concerns'            => $data['current_concerns']        ?? null,
            'family_history'              => $data['family_history']           ?? null,
            'developmental_history'       => $data['developmental_history']   ?? null,
            'social_history'              => $data['social_history']          ?? null,
            'intake_summary'              => $data['assessment_summary']      ?? null,
            'recommendations'             => $data['recommendations']         ?? null,
            'priority_level'              => (int) ($data['priority_level']   ?? 3),
            'assessed_by'                 => Auth::id(),
            'assessed_at'                 => now(),
            'services_required'           => [
                'primary_service_id' => $data['i_primary_service_id']      ?? null,
                'service_categories' => $data['i_service_categories']      ?? [],
                'service_ids'        => $data['services_selected']         ?? [],
                'referral_source'  => (function () use ($data): array {
                    $sources = $data['referral_source'] ?? [];
                    if (in_array('other', $sources, true) && !empty($data['referral_source_other'])) {
                        $sources = array_filter($sources, fn($v) => $v !== 'other');
                        $sources[] = 'other: ' . $data['referral_source_other'];
                    }
                    return array_values($sources);
                })(),
                'referral_contact' => $data['referral_contact']           ?? null,
                'payment_method'   => $data['expected_payment_method']    ?? null,
                'sha_enrolled'     => (bool) ($data['sha_enrolled']       ?? false),
                'ncpwd_covered'    => (bool) ($data['ncpwd_covered']      ?? false),
                'has_insurance'    => (bool) ($data['has_private_insurance'] ?? false),
                'payment_notes'    => $data['payment_notes']              ?? null,
                'handover_note'    => $data['handover_note']              ?? null,
            ],
            'functional_screening_scores' => $functionalScreeningScores,
        ];
    }

    // Save all sub-table data after intake_assessment record is created
    protected function afterCreate(): void
    {
        DB::transaction(function () {
            $intake = $this->record;
            $data   = $this->data;
            $visit  = $intake->visit;
            $client = $intake->client;

            // ── Client B-section updates ─────────────────────────────────────────
            $clientUpdates = array_filter([
                'national_id'              => $data['b_national_id']             ?? null,
                'birth_certificate_number' => $data['b_birth_certificate']       ?? null,
                'phone_primary'            => $data['b_phone_primary']           ?? null,
                'phone_secondary'          => $data['b_phone_secondary']         ?? null,
                'preferred_communication'  => $data['b_preferred_communication'] ?? null,
                'consent_to_sms'           => isset($data['b_consent_to_sms']) ? (bool) $data['b_consent_to_sms'] : null,
                'sha_number'               => $data['b_sha_number']              ?? null,
                'ncpwd_number'             => $data['b_ncpwd_number']            ?? null,
                'county_id'                => $data['b_county_id']               ?? null,
                'sub_county_id'            => $data['b_sub_county_id']           ?? null,
                'ward_id'                  => $data['b_ward_id']                 ?? null,
                'primary_address'          => $data['b_primary_address']         ?? null,
                'landmark'                 => $data['b_landmark']                ?? null,
            ], fn($v) => $v !== null);

            if ($clientUpdates) {
                $client->update($clientUpdates);
            }

            // ── C — Client Disability ─────────────────────────────────────────────
            if (!empty($data['dis_is_disability_known'])) {
                // Enrich device entries with resolved other-specify values
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
                    ['client_id' => $client->id],
                    [
                        'is_disability_known'        => true,
                        'disability_categories'      => $data['dis_disability_categories']  ?? [],
                        'onset'                      => $data['dis_onset']                  ?? null,
                        'level_of_functioning'       => $data['dis_level_of_functioning']   ?? null,
                        'assistive_technology'        => $atDevices,
                        'assistive_technology_notes'  => $data['dis_disability_notes']       ?? null,
                        'disability_notes'            => $data['dis_disability_notes']       ?? null,
                    ]
                );

                // NCPWD — if registered, update client number + record verification status
                if (($data['dis_ncpwd_registered'] ?? null) === 'yes') {
                    // Update client ncpwd_number (prefer the Section C entry over Section B)
                    if (!empty($data['dis_ncpwd_number'])) {
                        $client->update(['ncpwd_number' => $data['dis_ncpwd_number']]);
                    }

                    // Record NCPWD card verification status as a document
                    ClientDocument::updateOrCreate(
                        ['client_id' => $client->id, 'document_type' => 'ncpwd_card'],
                        [
                            'document_number' => $data['dis_ncpwd_number']             ?? null,
                            'notes'           => $data['dis_ncpwd_verification_status'] ?? null,
                            'uploaded_by'     => Auth::id(),
                        ]
                    );

                    // Save each uploaded evidence file as a ClientDocument
                    foreach ($data['dis_evidence_files'] ?? [] as $filePath) {
                        ClientDocument::create([
                            'client_id'     => $client->id,
                            'document_type' => 'disability_evidence',
                            'file_path'     => $filePath,
                            'file_name'     => basename($filePath),
                            'uploaded_by'   => Auth::id(),
                        ]);
                    }
                }
            }

            // ── D — Socio-Demographics ───────────────────────────────────────────
            // Resolve "Other (specify)" free-text into stored values
            $maritalStatus = ($data['socio_marital_status'] ?? null) === 'other'
                ? ('other: ' . ($data['socio_marital_other'] ?? 'unspecified'))
                : ($data['socio_marital_status'] ?? null);

            $livingArrangement = ($data['socio_living_arrangement'] ?? null) === 'other'
                ? ('other: ' . ($data['socio_living_other'] ?? 'unspecified'))
                : ($data['socio_living_arrangement'] ?? null);

            $primaryCaregiver = ($data['socio_primary_caregiver'] ?? null) === 'other'
                ? ('other: ' . ($data['socio_caregiver_other'] ?? 'unspecified'))
                : ($data['socio_primary_caregiver'] ?? null);

            $primaryLanguage = ($data['socio_primary_language'] ?? null) === 'other'
                ? ('other: ' . ($data['socio_language_other'] ?? 'unspecified'))
                : ($data['socio_primary_language'] ?? null);

            $otherLangs = $data['socio_other_languages'] ?? null;

            ClientSocioDemographic::updateOrCreate(
                ['client_id' => $client->id],
                [
                    'marital_status'        => $maritalStatus,
                    'living_arrangement'    => $livingArrangement,
                    'household_size'        => $data['socio_household_size']       ?? null,
                    'primary_caregiver'     => $primaryCaregiver,
                    'source_of_support'     => $data['socio_source_of_support']    ?? [],
                    'other_support_source'  => $data['socio_other_support']        ?? null,
                    'primary_language'      => $primaryLanguage,
                    'other_languages'       => $otherLangs ? [$otherLangs] : [],
                    'accessibility_at_home' => $data['socio_accessibility_at_home'] ?? null,
                    'socio_notes'           => $data['socio_notes']                ?? null,
                ]
            );

            // ── F — Education & Occupation ───────────────────────────────────────
            // Resolve employment_status "Other (specify)"
            $employmentStatus = $data['edu_employment_status'] ?? null;
            if ($employmentStatus === 'other' && !empty($data['edu_employment_status_other'])) {
                $employmentStatus = 'other: ' . $data['edu_employment_status_other'];
            }

            ClientEducation::updateOrCreate(
                ['client_id' => $client->id],
                [
                    'education_level'      => $data['edu_education_level']      ?? null,
                    'school_type'          => $data['edu_school_type']          ?? null,
                    'school_name'          => $data['edu_school_name']          ?? null,
                    'grade_level'          => $data['edu_grade_level']          ?? null,
                    'currently_enrolled'   => ($data['edu_currently_enrolled'] ?? null) === 'yes',
                    'attendance_challenges'=> ($data['edu_attendance_challenges'] ?? null) === 'yes',
                    'attendance_notes'     => $data['edu_attendance_notes']     ?? null,
                    'performance_concern'  => ($data['edu_performance_concern']  ?? null) === 'yes',
                    'performance_notes'    => $data['edu_performance_notes']    ?? null,
                    'employment_status'    => $employmentStatus,
                    'occupation_type'      => $data['edu_occupation_type']      ?? null,
                    'education_notes'      => null,
                ]
            );

            // ── E1 — Medical History ─────────────────────────────────────────────
            $medConditions = $data['med_medical_conditions'] ?? [];
            if (!empty($data['med_conditions_other'])) {
                $medConditions[] = 'other: ' . $data['med_conditions_other'];
            }

            $prevAssessments = $data['med_previous_assessments'] ?? [];
            if (!empty($data['med_previous_assessments_other'])) {
                $prevAssessments[] = 'other: ' . $data['med_previous_assessments_other'];
            }

            // ── E3 — resolve "Other specify" into arrays ──────────────────────
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

            $devConcerns = $data['peri_developmental_concerns'] ?? [];
            if (!empty($data['peri_developmental_concerns_other'])) {
                $devConcerns = array_map(
                    fn($v) => $v === 'other' ? 'other: ' . $data['peri_developmental_concerns_other'] : $v,
                    $devConcerns
                );
            }

            $placeOfBirth = ($data['peri_place_of_birth'] ?? null) === 'other'
                ? 'other: ' . ($data['peri_place_of_birth_other'] ?? 'unspecified')
                : ($data['peri_place_of_birth'] ?? null);

            // Resolve feeding_swallowing_concerns: merge "other" free-text into array
            $feedingConcernsRaw = $data['feeding_swallowing_concerns'] ?? [];
            if (in_array('other', $feedingConcernsRaw, true) && !empty($data['feeding_swallowing_concerns_other'])) {
                $feedingConcernsRaw = array_filter($feedingConcernsRaw, fn($v) => $v !== 'other');
                $feedingConcernsRaw[] = 'other: ' . $data['feeding_swallowing_concerns_other'];
            }

            $feedingHistory = array_filter([
                'feeding_method'       => $data['feeding_method']          ?? null,
                'diet_appetite'        => $data['feeding_diet_appetite']   ?? null,
                'diet_foods_brief'     => $data['feeding_diet_foods_brief'] ?? null,
                'swallowing_concerns'  => $feedingConcernsRaw ?: null,
                'growth_concern'       => $data['feeding_growth_concern']  ?? null,
                'nutrition_notes'      => $data['feeding_nutrition_notes'] ?? null,
            ], fn($v) => $v !== null && $v !== [] && $v !== '');

            ClientMedicalHistory::updateOrCreate(
                ['client_id' => $client->id],
                [
                    'medical_conditions'          => $medConditions ?: null,
                    'current_medications'         => $data['med_current_medications']    ?? null,
                    'surgical_history'            => $data['med_surgical_history']       ?? null,
                    'family_medical_history'      => $data['med_family_medical_history'] ?? null,
                    'immunization_status'         => implode(' | ', array_filter([
                        $data['med_immunization_status'] ?? null,
                        ($data['imm_missed_doses'] ?? null) === 'yes'
                            ? 'Missed doses: ' . ($data['imm_missed_doses_which'] ?? 'unspecified')
                            : null,
                        ($data['imm_recent_illness_post_vaccine'] ?? null) === 'yes'
                            ? 'Post-vaccine illness: ' . ($data['imm_recent_illness_notes'] ?? 'see notes')
                            : null,
                    ])) ?: null,
                    'feeding_history'             => $feedingHistory ?: null,
                    'previous_assessments'        => $prevAssessments,
                    'developmental_concerns'      => $devConcerns,
                    'developmental_concerns_notes'=> $data['developmental_history']      ?? null,
                    'assistive_devices_history'   => $data['e2_previous_devices']        ?? [],
                    'assistive_devices_notes'     => null,
                ]
            );

            // ── E4 — EPI card photo ──────────────────────────────────────────────
            if (($data['imm_epi_card_seen'] ?? null) === 'yes' && !empty($data['imm_epi_card_photo'])) {
                $photo = $data['imm_epi_card_photo'];
                // FileUpload returns a string path or array; normalise to string
                $photoPath = is_array($photo) ? ($photo[0] ?? null) : $photo;
                if ($photoPath) {
                    ClientDocument::updateOrCreate(
                        ['client_id' => $client->id, 'document_type' => 'epi_card'],
                        [
                            'file_path'   => $photoPath,
                            'file_name'   => basename($photoPath),
                            'notes'       => 'EPI card uploaded at intake — '
                                . ($data['imm_missed_doses'] === 'yes'
                                    ? 'Missed doses: ' . ($data['imm_missed_doses_which'] ?? 'see notes')
                                    : 'No missed doses reported'),
                            'uploaded_by' => Auth::id(),
                        ]
                    );
                }
            }

            // ── H — Uploaded reports / documents ─────────────────────────────────
            foreach ($data['h_reports_uploaded'] ?? [] as $filePath) {
                if (empty($filePath)) continue;
                ClientDocument::create([
                    'client_id'     => $client->id,
                    'document_type' => 'intake_report',
                    'file_path'     => $filePath,
                    'file_name'     => basename($filePath),
                    'notes'         => 'Uploaded at intake — ' . now()->toDateString(),
                    'uploaded_by'   => Auth::id(),
                ]);
            }

            // ── Allergies repeater ───────────────────────────────────────────────
            foreach ($data['allergy_items'] ?? [] as $item) {
                $allergyType = $item['allergy_type'] ?? 'other';
                if ($allergyType === 'none_known') continue;

                // allergen_names is a multi-select array; replace 'other' key with free-text value
                $allergenNames = $item['allergen_names'] ?? [];
                if (is_array($allergenNames)) {
                    $allergenNames = array_map(
                        fn($v) => $v === 'other' ? ($item['allergen_other'] ?? 'Other') : $v,
                        $allergenNames
                    );
                }
                $allergenStr = is_array($allergenNames)
                    ? implode(', ', $allergenNames)
                    : ($allergenNames ?? null);

                if (empty($allergenStr)) continue;

                ClientAllergy::create([
                    'client_id'        => $client->id,
                    'allergy_type'     => $allergyType,
                    'allergen_name'    => $allergenStr,
                    'typical_reactions'=> $item['reactions'] ?? [],
                    'severity'         => $item['severity']  ?? 'mild',
                ]);
            }

            // ── G — Functional Screening ─────────────────────────────────────────
            $scores = $intake->functional_screening_scores ?? [];
            $bandKey = $scores['band']       ?? null;
            $ageMonths = $scores['age_months'] ?? null;
            $screeningAnswers = $scores['answers'] ?? [];

            FunctionalScreening::create([
                'intake_assessment_id' => $intake->id,
                'client_id'            => $client->id,
                'age_band'             => $bandKey,
                'screening_answers'    => $screeningAnswers,
                'overall_summary'      => $data['func_overall_summary'] ?? null,
            ]);

            // ── Auto-referrals from functional screening ──────────────────────────
            // Deduplication key: service_point slug (mob_fine + selfcare → same OT slot)
            if ($bandKey && isset(IntakeAssessmentResource::screeningQuestions()[$bandKey])) {
                $allQuestions  = IntakeAssessmentResource::screeningQuestions();
                $bandDomains   = $allQuestions[$bandKey]['domains'];
                $domainSvcMap  = IntakeAssessmentResource::screeningDomainServiceMap();

                // Pre-load minimum service base price per department name for costing hints.
                // One department may have many services — we surface the lowest base_price
                // as an indicative cost so billing has a starting reference.
                $deptNames = array_unique(array_column($domainSvcMap, 'department'));
                $deptIds   = Department::whereIn('name', $deptNames)
                    ->pluck('id', 'name')
                    ->all();
                $deptMinPrice = Service::where('is_active', true)
                    ->whereIn('department_id', array_values($deptIds))
                    ->selectRaw('department_id, MIN(base_price) as min_price')
                    ->groupBy('department_id')
                    ->pluck('min_price', 'department_id')
                    ->all();
                // Invert: department_name → min_price
                $servicePricesByDept = [];
                foreach ($deptIds as $deptName => $deptId) {
                    if (isset($deptMinPrice[$deptId])) {
                        $servicePricesByDept[$deptName] = $deptMinPrice[$deptId];
                    }
                }

                $createdSvcPoints = []; // deduplicate by service_point slug

                foreach ($bandDomains as $domainKey => $domain) {
                    $route = $domainSvcMap[$domainKey] ?? null;
                    if (!$route) continue; // unknown domain — skip

                    $spSlug = $route['service_point'];
                    if (isset($createdSvcPoints[$spSlug])) continue; // already referred to this service point

                    $domainAnswers = $screeningAnswers[$domainKey] ?? [];
                    $flaggedQuestions = [];
                    $highPriority     = false;

                    foreach ($domain['questions'] as $qKey => $q) {
                        $answer = $domainAnswers[$qKey] ?? null;
                        if ($answer === $q['flag']) {
                            $flaggedQuestions[] = $q['text'];
                            if (($q['priority'] ?? 'routine') === 'high') {
                                $highPriority = true;
                            }
                        }
                    }

                    if (empty($flaggedQuestions)) continue; // no flags in this domain

                    $estimatedCost = $servicePricesByDept[$route['department']] ?? null;

                    $createdSvcPoints[$spSlug] = true;
                    AssessmentAutoReferral::create([
                        'form_response_id' => null,
                        'client_id'        => $client->id,
                        'visit_id'         => $visit->id,
                        'service_point'    => $spSlug,
                        'department'       => $route['department'],
                        'priority'         => $highPriority ? 'high' : 'routine',
                        'reason'           => "Functional screening — {$domain['label']} (band: {$bandKey}): "
                                             . implode('; ', $flaggedQuestions),
                        'trigger_data'     => [
                            'domain'           => $domainKey,
                            'band'             => $bandKey,
                            'flagged_questions'=> $flaggedQuestions,
                            'referral_dest'    => $domain['referral'],
                            'estimated_cost'   => $estimatedCost,
                            'source'           => 'intake_functional_screening',
                        ],
                        'status' => 'pending',
                    ]);
                }
            }

            // ── AT Referral Routing (E2) — category-specific, deduplicated ──────
            $routingMap      = IntakeAssessmentResource::atReferralMap();
            $createdReferrals = []; // track service_point keys already referred

            foreach ($data['e2_current_devices'] ?? [] as $device) {
                $category = $device['category'] ?? 'other';
                $route    = $routingMap[$category] ?? $routingMap['other'];
                $spKey    = $route['service_point'];

                // Determine trigger reason and priority
                $reasons   = [];
                $priority  = 'routine';

                if (($device['fit_comfort'] ?? null) === 'poor') {
                    $reasons[] = 'Poor fit / comfort';
                    $priority  = 'high';
                }
                if (($device['condition'] ?? null) === 'broken') {
                    $reasons[] = 'Broken / parts missing';
                    $priority  = 'high';
                }
                if (($device['condition'] ?? null) === 'battery_fault') {
                    $reasons[] = 'Battery fault';
                    if ($category === 'hearing') $priority = 'high';
                }
                if (!empty($device['safety_concerns'])) {
                    $reasons[] = 'Safety concerns: ' . implode(', ', (array) $device['safety_concerns']);
                    $priority  = 'high';
                }
                if (($device['repairs_needed'] ?? null) === 'yes') {
                    $reasons[] = 'Repairs / review needed: ' . ($device['repairs_notes'] ?? 'see notes');
                }
                if (\in_array($device['use_frequency'] ?? null, ['rarely', 'not_using'], true)) {
                    $reasons[] = 'Low use frequency (' . $device['use_frequency'] . ')';
                }

                if (empty($reasons)) continue; // no concern — no referral for this device

                // One referral per service point per intake (deduplicate)
                if (isset($createdReferrals[$spKey])) continue;
                $createdReferrals[$spKey] = true;

                AssessmentAutoReferral::create([
                    'form_response_id' => null,
                    'client_id'        => $client->id,
                    'visit_id'         => $visit->id,
                    'service_point'    => $spKey,
                    'department'       => $route['department'],
                    'priority'         => $priority,
                    'reason'           => 'AT Review (' . $category . '): ' . implode('; ', $reasons),
                    'trigger_data'     => [
                        'category'       => $category,
                        'device_type'    => $device['device_type']   ?? null,
                        'condition'      => $device['condition']      ?? null,
                        'fit_comfort'    => $device['fit_comfort']    ?? null,
                        'safety_concerns'=> $device['safety_concerns'] ?? [],
                        'source'         => 'intake_at_section',
                    ],
                    'status' => 'pending',
                ]);
            }

            // Unmet AT need — one referral per identified need category
            if (($data['e2_needs_at'] ?? null) === 'yes') {
                $satisfaction = (int) ($data['e2_satisfaction'] ?? 5);
                $needsPriority = $satisfaction <= 2 ? 'high' : 'routine';

                foreach ($data['e2_needs_categories'] ?? [] as $needCat) {
                    $route = $routingMap[$needCat] ?? $routingMap['other'];
                    $spKey = $route['service_point'];
                    if (isset($createdReferrals[$spKey])) continue;
                    $createdReferrals[$spKey] = true;

                    AssessmentAutoReferral::create([
                        'form_response_id' => null,
                        'client_id'        => $client->id,
                        'visit_id'         => $visit->id,
                        'service_point'    => $spKey,
                        'department'       => $route['department'],
                        'priority'         => $needsPriority,
                        'reason'           => 'Unmet AT need identified at intake: ' . $needCat
                            . ($satisfaction <= 2 ? ' (low satisfaction score: ' . $satisfaction . ')' : ''),
                        'trigger_data'     => [
                            'category'    => $needCat,
                            'need_priority'=> $data['e2_needs_priority'] ?? null,
                            'satisfaction' => $satisfaction,
                            'source'       => 'intake_at_needs_gap',
                        ],
                        'status' => 'pending',
                    ]);
                }
            }

            // ── Feeding/swallowing referrals from E5 ─────────────────────────────
            // $feedingConcernsRaw already resolved above (with "other" text merged)
            $hasClinicalConcern = array_filter($feedingConcernsRaw, fn($v) =>
                in_array($v, ['sucking_difficulty', 'swallowing_difficulty', 'choking'], true)
                || str_starts_with($v, 'other: ') // custom other concern also triggers referral
            );
            if (!empty($hasClinicalConcern)) {
                AssessmentAutoReferral::create([
                    'form_response_id' => null,
                    'client_id'        => $client->id,
                    'visit_id'         => $visit->id,
                    'service_point'    => 'feeding',
                    'department'       => 'Speech & Language / Nutrition',
                    'priority'         => 'routine',
                    'reason'           => 'Feeding/swallowing concerns flagged: ' . implode(', ', $hasClinicalConcern),
                    'trigger_data'     => ['concerns' => $feedingConcernsRaw, 'source' => 'intake_feeding_screening'],
                    'status'           => 'pending',
                ]);
            }

            // ── Service Bookings — primary + cross-posting ───────────────────────
            $primaryServiceId  = $data['i_primary_service_id'] ?? null;
            $crossServiceIds   = $data['services_selected']    ?? [];

            // Collect all service IDs to book; primary first, then cross-postings
            // (exclude primary from cross-posting list to avoid duplicates)
            $allServiceIds = array_unique(array_filter(array_merge(
                $primaryServiceId ? [$primaryServiceId] : [],
                $crossServiceIds,
            )));

            if (!empty($allServiceIds)) {
                foreach (Service::whereIn('id', $allServiceIds)->get() as $service) {
                    $isPrimary   = $service->id == $primaryServiceId;
                    $bookingType = $isPrimary ? 'primary' : 'cross_posting';

                    ServiceBooking::create([
                        'visit_id'       => $visit->id,
                        'client_id'      => $client->id,
                        'service_id'     => $service->id,
                        'department_id'  => $service->department_id,
                        'booking_type'   => $bookingType,
                        'booking_date'   => today(),
                        'payment_status' => 'pending',
                        'service_status' => 'scheduled',
                        'priority_level' => $isPrimary ? 1 : ($intake->priority_level ?? 3),
                        'priority'       => $isPrimary ? 'urgent' : match ((int) ($intake->priority_level ?? 3)) {
                            1 => 'urgent', 2 => 'high', default => 'routine',
                        },
                        'booked_by'      => Auth::id(),
                        'source'         => 'intake',
                        'status'         => 'pending',
                    ]);
                }
            }

            // ── L — Record assessed_at timestamp ────────────────────────────────
            $intake->update(['assessed_at' => now()]);

            // ── K — Handle deferral ──────────────────────────────────────────────
            if (!empty($data['defer_client'])) {
                // Resolve deferral_reason "Other (specify)"
                $deferralReason = $data['deferral_reason'] ?? null;
                if ($deferralReason === 'other' && !empty($data['deferral_reason_other'])) {
                    $deferralReason = 'other: ' . $data['deferral_reason_other'];
                }

                $visit->update([
                    'status'               => 'deferred',
                    'next_appointment_date'=> $data['next_appointment_date'] ?? null,
                    'deferral_reason'      => $deferralReason,
                    'deferral_notes'       => $data['deferral_notes'] ?? null,
                ]);
                Notification::make()->warning()
                    ->title('Client Deferred')
                    ->body('Visit marked as deferred. Schedule a follow-up appointment.')
                    ->send();
                return;
            }

            // ── Create Invoice with sponsor/client split ─────────────────────────
            $paymentMethod = $data['expected_payment_method'] ?? 'cash';
            $hasSponsor    = in_array($paymentMethod, ['sha', 'ncpwd', 'insurance_private', 'waiver', 'combination'], true);

            $branchCode    = $visit->branch ? strtoupper(substr($visit->branch->name, 0, 3)) : 'HQ';
            $invYear       = now()->format('Y');
            $invMonth      = now()->format('m');
            $invSeq        = Invoice::whereYear('created_at', $invYear)->whereMonth('created_at', $invMonth)->count() + 1;
            $invoiceNumber = "{$branchCode}/INV/{$invYear}/{$invMonth}/" . str_pad($invSeq, 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'visit_id'              => $visit->id,
                'client_id'             => $client->id,
                'branch_id'             => $visit->branch_id,
                'invoice_number'        => $invoiceNumber,
                'payment_method'        => $paymentMethod,
                'insurance_provider_id' => $data['insurance_provider_id'] ?? null,
                'has_sponsor'           => $hasSponsor,
                'status'                => 'pending',
                'issued_by'             => Auth::id(),
                'payment_notes'         => $data['payment_notes'] ?? null,
                'total_amount'          => 0,
                'total_sponsor_amount'  => 0,
                'total_client_amount'   => 0,
            ]);

            // Client co-payment ratio (billing admin adjusts during verification)
            $clientRatio = match ($paymentMethod) {
                'sha'               => 0.20, // SHA covers 80%
                'ncpwd'             => 0.10, // NCPWD covers 90%
                'waiver'            => 0.00, // Fully waived
                'insurance_private' => 0.30, // Private insurance covers ~70%
                'combination'       => 0.50, // Split — admin adjusts
                default             => 1.00, // Cash/M-PESA: client pays full
            };

            $totalAmount = $totalSponsorAmount = $totalClientAmount = 0.0;

            $createdBookings = ServiceBooking::where('visit_id', $visit->id)
                ->where('source', 'intake')
                ->with('service')
                ->get();

            foreach ($createdBookings as $booking) {
                $svc         = $booking->service;
                $baseCost    = (float) ($svc?->base_price ?? 0);
                $clientPays  = round($baseCost * $clientRatio, 2);
                $sponsorPays = round($baseCost - $clientPays, 2);

                $item = InvoiceItem::create([
                    'invoice_id'            => $invoice->id,
                    'service_id'            => $booking->service_id,
                    'department_id'         => $booking->department_id,
                    'description'           => $svc?->name ?? 'Service',
                    'quantity'              => 1,
                    'unit_price'            => $baseCost,
                    'subtotal'              => $baseCost,
                    'sponsor_type'          => $hasSponsor ? $paymentMethod : null,
                    'sponsor_percentage'    => $hasSponsor ? round((1 - $clientRatio) * 100, 2) : 0,
                    'sponsor_amount'        => $sponsorPays,
                    'client_amount'         => $clientPays,
                    'client_payment_status' => 'pending',
                    'sponsor_claim_status'  => $hasSponsor ? 'pending' : null,
                ]);

                $booking->update(['invoice_item_id' => $item->id]);

                $totalAmount        += $baseCost;
                $totalSponsorAmount += $sponsorPays;
                $totalClientAmount  += $clientPays;
            }

            $invoice->update([
                'total_amount'        => $totalAmount,
                'total_sponsor_amount'=> $totalSponsorAmount,
                'total_client_amount' => $totalClientAmount,
                'has_sponsor'         => $totalSponsorAmount > 0,
            ]);

            // ── Route visit: sponsor methods → billing admin, cash/mpesa → cashier ─
            $visit->completeStage();

            if ($hasSponsor) {
                $visit->moveToStage('billing');
                $routeLabel = 'Payment Admin (' . strtoupper($paymentMethod) . ')';
            } else {
                $visit->moveToStage('cashier');
                $routeLabel = 'Cashier — KES ' . number_format($totalClientAmount, 2) . ' to collect';
            }

            Notification::make()->success()
                ->title('Intake Complete')
                ->body("{$client->full_name} → {$routeLabel}. Invoice #{$invoiceNumber} created.")
                ->send();
        });
    }
}
