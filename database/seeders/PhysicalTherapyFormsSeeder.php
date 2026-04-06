<?php

namespace Database\Seeders;

use App\Models\AssessmentFormSchema;
use Illuminate\Database\Seeder;

class PhysicalTherapyFormsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding Physical Therapy Forms...');

        // 1. PT ELIGIBILITY SCREENING CHECKLIST
        AssessmentFormSchema::create([
            'name' => 'PT Eligibility Screening Checklist',
            'slug' => 'pt-eligibility-screening',
            'version' => 1,
            'category' => 'physiotherapy',
            'description' => 'Initial eligibility screening to determine if referral to physiotherapy is warranted',
            'estimated_minutes' => 20,
            'allow_draft' => true,
            'allow_partial_submission' => false,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getEligibilityScreeningSchema(),
        ]);
        $this->command->info('  ✓ PT Eligibility Screening Checklist');

        // 2. INITIAL PT ASSESSMENT (ADULT)
        AssessmentFormSchema::create([
            'name' => 'Initial Physiotherapy Assessment (Adult)',
            'slug' => 'pt-initial-assessment-adult',
            'version' => 1,
            'category' => 'physiotherapy',
            'description' => 'Comprehensive initial assessment for adult patients (18+)',
            'estimated_minutes' => 60,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getAdultInitialAssessmentSchema(),
        ]);
        $this->command->info('  ✓ Initial PT Assessment (Adult)');

        // 3. PEDIATRIC PT ASSESSMENT
        AssessmentFormSchema::create([
            'name' => 'Pediatric Physiotherapy Assessment',
            'slug' => 'pt-pediatric-assessment',
            'version' => 1,
            'category' => 'physiotherapy',
            'description' => 'Comprehensive physiotherapy assessment for pediatric patients (under 18)',
            'estimated_minutes' => 60,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getPediatricAssessmentSchema(),
        ]);
        $this->command->info('  ✓ Pediatric PT Assessment');

        // 4. HYDROTHERAPY SCREENING TOOL
        AssessmentFormSchema::create([
            'name' => 'Hydrotherapy Screening Tool',
            'slug' => 'pt-hydrotherapy-screening',
            'version' => 1,
            'category' => 'physiotherapy',
            'description' => 'Screening checklist for hydrotherapy suitability and contraindications',
            'estimated_minutes' => 15,
            'allow_draft' => true,
            'allow_partial_submission' => false,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getHydrotherapyScreeningSchema(),
        ]);
        $this->command->info('  ✓ Hydrotherapy Screening Tool');
    }

    private function getEligibilityScreeningSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Basic Information',
                    'icon' => 'heroicon-o-identification',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'referral_source', 'type' => 'select', 'label' => 'Referral Source', 'options' => ['Medical Officer', 'Paediatrician', 'Specialist', 'Self-Referral', 'Other'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'referral_date', 'type' => 'date', 'label' => 'Referral Date', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'presenting_diagnosis', 'type' => 'text', 'label' => 'Presenting Diagnosis / Reason for Referral', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'onset_date', 'type' => 'date', 'label' => 'Date of Onset / Diagnosis', 'columnSpan' => 1],
                        ['id' => 'referral_priority', 'type' => 'radio', 'label' => 'Referral Priority', 'options' => ['Urgent (within 24-48h)', 'High (within 1 week)', 'Routine (within 4 weeks)'], 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'General Observations & Screening Indicators',
                    'icon' => 'heroicon-o-clipboard-document-check',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'obs_note', 'type' => 'placeholder', 'label' => 'Check all that apply. Any checked item supports a physiotherapy referral.'],
                        ['id' => 'limited_mobility', 'type' => 'checkbox', 'label' => 'Limited range of motion or restricted mobility in joints/limbs'],
                        ['id' => 'gait_abnormality', 'type' => 'checkbox', 'label' => 'Observed gait abnormality or asymmetry during walking'],
                        ['id' => 'postural_deviation', 'type' => 'checkbox', 'label' => 'Postural deviation (scoliosis, kyphosis, lordosis, pelvic tilt)'],
                        ['id' => 'muscle_weakness', 'type' => 'checkbox', 'label' => 'Muscle weakness or decreased muscle tone (hypotonia)'],
                        ['id' => 'high_muscle_tone', 'type' => 'checkbox', 'label' => 'High muscle tone / spasticity (hypertonia)'],
                        ['id' => 'contractures', 'type' => 'checkbox', 'label' => 'Contractures or joint stiffness limiting function'],
                        ['id' => 'balance_deficit', 'type' => 'checkbox', 'label' => 'Balance deficits or history of falls'],
                        ['id' => 'pain_movement', 'type' => 'checkbox', 'label' => 'Pain during movement or at rest'],
                        ['id' => 'delayed_milestones', 'type' => 'checkbox', 'label' => 'Delayed gross motor milestones (rolling, sitting, standing, walking)'],
                        ['id' => 'post_surgical', 'type' => 'checkbox', 'label' => 'Post-surgical rehabilitation needs'],
                    ],
                ],
                [
                    'title' => 'Developmental Concerns (Children)',
                    'icon' => 'heroicon-o-user-group',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'dev_note', 'type' => 'placeholder', 'label' => 'Complete for patients under 18 years'],
                        ['id' => 'cannot_walk_18m', 'type' => 'checkbox', 'label' => 'Not walking independently by 18 months'],
                        ['id' => 'toe_walking', 'type' => 'checkbox', 'label' => 'Persistent toe walking (after age 2)'],
                        ['id' => 'cp_risk', 'type' => 'checkbox', 'label' => 'Known or suspected cerebral palsy risk factors'],
                        ['id' => 'spina_bifida', 'type' => 'checkbox', 'label' => 'Spina bifida or other neural tube defects'],
                        ['id' => 'down_syndrome_motor', 'type' => 'checkbox', 'label' => 'Down syndrome with motor delays'],
                        ['id' => 'birth_injury', 'type' => 'checkbox', 'label' => 'Birth injury affecting movement (erb\'s palsy, brachial plexus)'],
                    ],
                ],
                [
                    'title' => 'Communication & Behaviour',
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'cooperation_level', 'type' => 'radio', 'label' => 'Patient Cooperation Level', 'options' => ['Fully cooperative', 'Cooperative with prompting', 'Limited cooperation', 'Unable to cooperate'], 'columnSpan' => 1],
                        ['id' => 'communication_method', 'type' => 'select', 'label' => 'Primary Communication Method', 'options' => ['Verbal', 'AAC Device', 'Sign Language', 'Visual Supports', 'Non-verbal/Gestures'], 'columnSpan' => 1],
                        ['id' => 'caregiver_present', 'type' => 'radio', 'label' => 'Caregiver / Guardian Present', 'options' => ['Yes', 'No'], 'columnSpan' => 1],
                        ['id' => 'notes_behaviour', 'type' => 'textarea', 'label' => 'Additional Behaviour / Communication Notes', 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Referral Decision',
                    'icon' => 'heroicon-o-check-badge',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'screening_outcome', 'type' => 'radio', 'label' => 'Screening Outcome', 'options' => ['Eligible — Refer to PT', 'Not Eligible at this time', 'Refer to Other Service', 'Requires Further Review'], 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'other_referral', 'type' => 'text', 'label' => 'If referring elsewhere, specify service', 'columnSpan' => 1],
                        ['id' => 'screener_name', 'type' => 'text', 'label' => 'Screened by', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'screening_date', 'type' => 'date', 'label' => 'Date of Screening', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'additional_notes', 'type' => 'textarea', 'label' => 'Additional Notes', 'columnSpan' => 2],
                    ],
                ],
            ],
        ];
    }

    private function getAdultInitialAssessmentSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Presenting Complaint & History',
                    'icon' => 'heroicon-o-document-text',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'chief_complaint', 'type' => 'textarea', 'label' => 'Chief Complaint (in patient\'s words)', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'onset', 'type' => 'select', 'label' => 'Onset', 'options' => ['Sudden', 'Gradual', 'Following injury', 'Post-surgical', 'Unknown'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'onset_date', 'type' => 'date', 'label' => 'Date of Onset', 'columnSpan' => 1],
                        ['id' => 'symptom_behaviour', 'type' => 'select', 'label' => 'Symptom Behaviour', 'options' => ['Constant', 'Intermittent', 'Variable', 'Activity-related'], 'columnSpan' => 1],
                        ['id' => 'aggravating_factors', 'type' => 'textarea', 'label' => 'Aggravating Factors', 'columnSpan' => 1],
                        ['id' => 'relieving_factors', 'type' => 'textarea', 'label' => 'Relieving Factors', 'columnSpan' => 1],
                        ['id' => 'pain_scale', 'type' => 'select', 'label' => 'Pain Scale (VAS 0-10)', 'options' => ['0 - No pain', '1', '2', '3', '4', '5 - Moderate', '6', '7', '8', '9', '10 - Worst possible'], 'columnSpan' => 1],
                        ['id' => 'previous_treatment', 'type' => 'textarea', 'label' => 'Previous Treatment / Interventions', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Medical History',
                    'icon' => 'heroicon-o-heart',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'relevant_pmh', 'type' => 'textarea', 'label' => 'Relevant Past Medical History', 'columnSpan' => 2],
                        ['id' => 'surgeries', 'type' => 'textarea', 'label' => 'Previous Surgeries / Procedures', 'columnSpan' => 1],
                        ['id' => 'current_medications', 'type' => 'textarea', 'label' => 'Current Medications', 'columnSpan' => 1],
                        ['id' => 'allergies', 'type' => 'text', 'label' => 'Known Allergies', 'columnSpan' => 1],
                        ['id' => 'comorbidities', 'type' => 'textarea', 'label' => 'Comorbidities (Diabetes, Hypertension, Osteoporosis, etc.)', 'columnSpan' => 2],
                        ['id' => 'xrays_scans', 'type' => 'textarea', 'label' => 'Relevant X-Rays / Scans / Reports (findings)', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Functional & Social History',
                    'icon' => 'heroicon-o-home',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'occupation', 'type' => 'text', 'label' => 'Occupation / Daily Activity Level', 'columnSpan' => 1],
                        ['id' => 'living_situation', 'type' => 'select', 'label' => 'Living Situation', 'options' => ['Lives alone', 'With family', 'Supported living', 'Institution'], 'columnSpan' => 1],
                        ['id' => 'adl_independence', 'type' => 'radio', 'label' => 'ADL Independence', 'options' => ['Independent', 'Requires assistance', 'Fully dependent'], 'columnSpan' => 1],
                        ['id' => 'mobility_aids', 'type' => 'select', 'label' => 'Current Mobility Aids', 'options' => ['None', 'Walking stick', 'Crutches', 'Walker / Zimmer frame', 'Wheelchair', 'Other'], 'columnSpan' => 1],
                        ['id' => 'patient_goals', 'type' => 'textarea', 'label' => 'Patient\'s Goals / Expectations from PT', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Objective Assessment',
                    'icon' => 'heroicon-o-magnifying-glass',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'posture_obs', 'type' => 'textarea', 'label' => 'Posture Observation (Standing, Sitting)', 'columnSpan' => 2],
                        ['id' => 'gait_obs', 'type' => 'textarea', 'label' => 'Gait Observation', 'columnSpan' => 1],
                        ['id' => 'palpation', 'type' => 'textarea', 'label' => 'Palpation Findings', 'columnSpan' => 1],
                        ['id' => 'rom_note', 'type' => 'placeholder', 'label' => 'Range of Motion (ROM) — document affected joints'],
                        ['id' => 'rom_right_active', 'type' => 'text', 'label' => 'Active ROM - Right', 'columnSpan' => 1],
                        ['id' => 'rom_left_active', 'type' => 'text', 'label' => 'Active ROM - Left', 'columnSpan' => 1],
                        ['id' => 'rom_right_passive', 'type' => 'text', 'label' => 'Passive ROM - Right', 'columnSpan' => 1],
                        ['id' => 'rom_left_passive', 'type' => 'text', 'label' => 'Passive ROM - Left', 'columnSpan' => 1],
                        ['id' => 'mmt_note', 'type' => 'placeholder', 'label' => 'Manual Muscle Testing (MMT) — Grade 0-5'],
                        ['id' => 'mmt_findings', 'type' => 'textarea', 'label' => 'MMT Findings (key muscle groups)', 'columnSpan' => 2],
                        ['id' => 'neurological', 'type' => 'textarea', 'label' => 'Neurological Assessment (sensation, reflexes, coordination)', 'columnSpan' => 2],
                        ['id' => 'special_tests', 'type' => 'textarea', 'label' => 'Special Tests Performed & Results', 'columnSpan' => 2],
                        ['id' => 'balance_outcome', 'type' => 'select', 'label' => 'Balance Assessment Outcome', 'options' => ['Normal', 'Mildly impaired', 'Moderately impaired', 'Severely impaired', 'Not assessed'], 'columnSpan' => 1],
                        ['id' => 'functional_tests', 'type' => 'textarea', 'label' => 'Functional Tests (TUG, 6MWT, SLS, etc.)', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Clinical Impression & Treatment Plan',
                    'icon' => 'heroicon-o-light-bulb',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'clinical_impression', 'type' => 'textarea', 'label' => 'Clinical Impression / Problem List', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'short_term_goals', 'type' => 'textarea', 'label' => 'Short-Term Goals (2-4 weeks)', 'columnSpan' => 1],
                        ['id' => 'long_term_goals', 'type' => 'textarea', 'label' => 'Long-Term Goals (6-12 weeks)', 'columnSpan' => 1],
                        ['id' => 'interventions_planned', 'type' => 'textarea', 'label' => 'Interventions Planned', 'columnSpan' => 2],
                        ['id' => 'frequency', 'type' => 'select', 'label' => 'Treatment Frequency', 'options' => ['Daily', '3x per week', '2x per week', 'Weekly', 'Fortnightly'], 'columnSpan' => 1],
                        ['id' => 'prognosis', 'type' => 'select', 'label' => 'Prognosis', 'options' => ['Good', 'Fair', 'Guarded', 'Poor'], 'columnSpan' => 1],
                        ['id' => 'home_programme', 'type' => 'textarea', 'label' => 'Home Exercise Programme Given', 'columnSpan' => 2],
                        ['id' => 'referrals_needed', 'type' => 'textarea', 'label' => 'Further Referrals / Investigations Needed', 'columnSpan' => 2],
                        ['id' => 'therapist_name', 'type' => 'text', 'label' => 'Therapist Name', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'assessment_date', 'type' => 'date', 'label' => 'Date of Assessment', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }

    private function getPediatricAssessmentSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Referral & Background',
                    'icon' => 'heroicon-o-document-text',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'referral_reason', 'type' => 'textarea', 'label' => 'Reason for Referral', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'referred_by', 'type' => 'text', 'label' => 'Referred by (Name & Designation)', 'columnSpan' => 1],
                        ['id' => 'referral_date', 'type' => 'date', 'label' => 'Referral Date', 'columnSpan' => 1],
                        ['id' => 'primary_diagnosis', 'type' => 'text', 'label' => 'Primary Diagnosis', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Family & Social History',
                    'icon' => 'heroicon-o-home',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'family_structure', 'type' => 'text', 'label' => 'Family Structure (e.g., lives with both parents)', 'columnSpan' => 2],
                        ['id' => 'siblings', 'type' => 'text', 'label' => 'Siblings (number, ages)', 'columnSpan' => 1],
                        ['id' => 'primary_caregiver', 'type' => 'text', 'label' => 'Primary Caregiver', 'columnSpan' => 1],
                        ['id' => 'school_placement', 'type' => 'select', 'label' => 'Current School Placement', 'options' => ['Mainstream school', 'Special school', 'Early intervention', 'Home-based', 'Not in school', 'Too young'], 'columnSpan' => 1],
                        ['id' => 'social_concerns', 'type' => 'textarea', 'label' => 'Social / Environmental Concerns', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Prenatal & Birth History',
                    'icon' => 'heroicon-o-heart',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'pregnancy_complications', 'type' => 'textarea', 'label' => 'Pregnancy Complications', 'columnSpan' => 2],
                        ['id' => 'delivery_type', 'type' => 'select', 'label' => 'Delivery Type', 'options' => ['Normal vaginal delivery', 'Assisted (forceps/vacuum)', 'Elective C-section', 'Emergency C-section'], 'columnSpan' => 1],
                        ['id' => 'gestation_weeks', 'type' => 'text', 'label' => 'Gestation at Birth (weeks)', 'columnSpan' => 1],
                        ['id' => 'birth_weight', 'type' => 'text', 'label' => 'Birth Weight (kg)', 'columnSpan' => 1],
                        ['id' => 'apgar_score', 'type' => 'text', 'label' => 'APGAR Score (if known)', 'columnSpan' => 1],
                        ['id' => 'nicu_admission', 'type' => 'radio', 'label' => 'NICU Admission', 'options' => ['Yes', 'No'], 'columnSpan' => 1],
                        ['id' => 'nicu_duration', 'type' => 'text', 'label' => 'If yes, NICU duration', 'columnSpan' => 1],
                        ['id' => 'birth_complications', 'type' => 'textarea', 'label' => 'Birth Complications', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Postnatal & Medical History',
                    'icon' => 'heroicon-o-clipboard-document',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'hospitalizations', 'type' => 'textarea', 'label' => 'Hospitalizations (reason, duration)', 'columnSpan' => 2],
                        ['id' => 'surgeries', 'type' => 'textarea', 'label' => 'Surgeries / Procedures', 'columnSpan' => 1],
                        ['id' => 'current_medications', 'type' => 'textarea', 'label' => 'Current Medications', 'columnSpan' => 1],
                        ['id' => 'known_diagnoses', 'type' => 'textarea', 'label' => 'Known Diagnoses / Conditions', 'columnSpan' => 2],
                        ['id' => 'seizures', 'type' => 'radio', 'label' => 'Seizures / Epilepsy', 'options' => ['Yes', 'No', 'History of'], 'columnSpan' => 1],
                        ['id' => 'seizure_details', 'type' => 'text', 'label' => 'If yes, frequency and type', 'columnSpan' => 1],
                        ['id' => 'immunizations_uptodate', 'type' => 'radio', 'label' => 'Immunizations Up to Date', 'options' => ['Yes', 'No', 'Unknown'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Developmental Milestones',
                    'icon' => 'heroicon-o-arrow-trending-up',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'milestones_note', 'type' => 'placeholder', 'label' => 'Record age of attainment (months). Write "Not yet" if not achieved.'],
                        ['id' => 'ms_head_control', 'type' => 'text', 'label' => 'Head Control (expected ~3m)', 'columnSpan' => 1],
                        ['id' => 'ms_rolling', 'type' => 'text', 'label' => 'Rolling (expected ~5m)', 'columnSpan' => 1],
                        ['id' => 'ms_sitting', 'type' => 'text', 'label' => 'Sitting independently (expected ~7-9m)', 'columnSpan' => 1],
                        ['id' => 'ms_crawling', 'type' => 'text', 'label' => 'Crawling (expected ~9m)', 'columnSpan' => 1],
                        ['id' => 'ms_standing', 'type' => 'text', 'label' => 'Standing with support (expected ~10m)', 'columnSpan' => 1],
                        ['id' => 'ms_walking', 'type' => 'text', 'label' => 'Walking independently (expected ~12-15m)', 'columnSpan' => 1],
                        ['id' => 'ms_running', 'type' => 'text', 'label' => 'Running (expected ~18-24m)', 'columnSpan' => 1],
                        ['id' => 'ms_stairs', 'type' => 'text', 'label' => 'Climbing stairs (expected ~24m)', 'columnSpan' => 1],
                        ['id' => 'milestones_concerns', 'type' => 'textarea', 'label' => 'Parent / Caregiver Concerns about Development', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Objective Assessment',
                    'icon' => 'heroicon-o-magnifying-glass',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'observation_general', 'type' => 'textarea', 'label' => 'General Observation (posture, movement quality, alertness)', 'columnSpan' => 2],
                        ['id' => 'muscle_tone', 'type' => 'radio', 'label' => 'Muscle Tone', 'options' => ['Normal', 'Hypotonic', 'Hypertonic', 'Mixed/Fluctuating'], 'columnSpan' => 1],
                        ['id' => 'tone_details', 'type' => 'textarea', 'label' => 'Tone Details (location, severity)', 'columnSpan' => 1],
                        ['id' => 'primitive_reflexes', 'type' => 'textarea', 'label' => 'Primitive Reflexes Present (if age-appropriate concern)', 'columnSpan' => 2],
                        ['id' => 'rom_findings', 'type' => 'textarea', 'label' => 'ROM Findings (joints of concern)', 'columnSpan' => 2],
                        ['id' => 'strength_assessment', 'type' => 'textarea', 'label' => 'Strength Assessment (key muscle groups)', 'columnSpan' => 2],
                        ['id' => 'gross_motor_function', 'type' => 'select', 'label' => 'Gross Motor Function Classification (GMFCS)', 'options' => ['Level I', 'Level II', 'Level III', 'Level IV', 'Level V', 'Not applicable'], 'columnSpan' => 1],
                        ['id' => 'gait_pattern', 'type' => 'textarea', 'label' => 'Gait Pattern (if ambulatory)', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Activities of Daily Living',
                    'icon' => 'heroicon-o-user',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'adl_dressing', 'type' => 'select', 'label' => 'Dressing', 'options' => ['Independent', 'Supervised', 'Partial assist', 'Full assist'], 'columnSpan' => 1],
                        ['id' => 'adl_feeding', 'type' => 'select', 'label' => 'Feeding / Self-feeding', 'options' => ['Independent', 'Supervised', 'Partial assist', 'Full assist'], 'columnSpan' => 1],
                        ['id' => 'adl_mobility', 'type' => 'select', 'label' => 'Mobility (household)', 'options' => ['Independent', 'Supervised', 'With device', 'Full assist'], 'columnSpan' => 1],
                        ['id' => 'adl_toilet', 'type' => 'select', 'label' => 'Toileting', 'options' => ['Independent', 'Supervised', 'Partial assist', 'Full assist', 'N/A (too young)'], 'columnSpan' => 1],
                        ['id' => 'adl_play', 'type' => 'textarea', 'label' => 'Play Skills Description', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Clinical Impression & Plan',
                    'icon' => 'heroicon-o-light-bulb',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'problem_list', 'type' => 'textarea', 'label' => 'Problem List / Clinical Impression', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'short_term_goals', 'type' => 'textarea', 'label' => 'Short-Term Goals', 'columnSpan' => 1],
                        ['id' => 'long_term_goals', 'type' => 'textarea', 'label' => 'Long-Term Goals', 'columnSpan' => 1],
                        ['id' => 'intervention_plan', 'type' => 'textarea', 'label' => 'Intervention Plan', 'columnSpan' => 2],
                        ['id' => 'parent_education', 'type' => 'textarea', 'label' => 'Parent / Caregiver Education & Home Programme', 'columnSpan' => 2],
                        ['id' => 'aids_equipment', 'type' => 'textarea', 'label' => 'Assistive Devices / Equipment Recommended', 'columnSpan' => 2],
                        ['id' => 'therapist_name', 'type' => 'text', 'label' => 'Therapist Name', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'assessment_date', 'type' => 'date', 'label' => 'Date of Assessment', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }

    private function getHydrotherapyScreeningSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Medical Status & Contraindications',
                    'icon' => 'heroicon-o-shield-exclamation',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'contra_note', 'type' => 'placeholder', 'label' => 'CONTRAINDICATIONS — Any checked item means hydrotherapy is NOT suitable at this time.'],
                        ['id' => 'open_wounds', 'type' => 'checkbox', 'label' => 'Open wounds, skin lesions, or active skin infections'],
                        ['id' => 'incontinence_unmanaged', 'type' => 'checkbox', 'label' => 'Unmanaged urinary / faecal incontinence'],
                        ['id' => 'uncontrolled_epilepsy', 'type' => 'checkbox', 'label' => 'Uncontrolled epilepsy / seizures (within last 3 months)'],
                        ['id' => 'acute_infection', 'type' => 'checkbox', 'label' => 'Active acute infection (respiratory, UTI, etc.)'],
                        ['id' => 'cardiac_unstable', 'type' => 'checkbox', 'label' => 'Unstable cardiac condition'],
                        ['id' => 'fear_water_extreme', 'type' => 'checkbox', 'label' => 'Extreme water phobia (patient refuses to enter water)'],
                        ['id' => 'colostomy_unmanaged', 'type' => 'checkbox', 'label' => 'Unmanaged colostomy or stoma'],
                        ['id' => 'allergy_pool_chemicals', 'type' => 'checkbox', 'label' => 'Known allergy to pool chemicals (chlorine/bromine)'],
                    ],
                ],
                [
                    'title' => 'Precautions (Requires Therapist Judgement)',
                    'icon' => 'heroicon-o-exclamation-triangle',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'prec_note', 'type' => 'placeholder', 'label' => 'PRECAUTIONS — These require therapist assessment before proceeding.'],
                        ['id' => 'prec_epilepsy_controlled', 'type' => 'checkbox', 'label' => 'Controlled epilepsy (seizure-free >3 months) — 2:1 supervision required'],
                        ['id' => 'prec_tracheostomy', 'type' => 'checkbox', 'label' => 'Tracheostomy / stoma — waterproofing required'],
                        ['id' => 'prec_cardiac', 'type' => 'checkbox', 'label' => 'Stable cardiac condition — cardiac clearance required'],
                        ['id' => 'prec_pressure_sores', 'type' => 'checkbox', 'label' => 'Healed pressure sores / fragile skin — extra monitoring'],
                        ['id' => 'prec_orthopaedic', 'type' => 'checkbox', 'label' => 'Post-surgical orthopaedic — check weight-bearing restrictions'],
                        ['id' => 'prec_autism_behaviour', 'type' => 'checkbox', 'label' => 'Autism with challenging behaviour — additional familiarisation sessions needed'],
                    ],
                ],
                [
                    'title' => 'Functional Suitability',
                    'icon' => 'heroicon-o-user-circle',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'water_confidence', 'type' => 'radio', 'label' => 'Water Confidence', 'options' => ['Comfortable', 'Mild anxiety', 'Fearful but manageable', 'Extreme fear (contraindication)'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'continence_management', 'type' => 'radio', 'label' => 'Continence Management', 'options' => ['Continent', 'Managed with nappies/pad', 'Unmanaged (contraindication)'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'swimming_ability', 'type' => 'radio', 'label' => 'Swimming / Water Experience', 'options' => ['Swimmer', 'Non-swimmer', 'Previously had hydrotherapy', 'Unknown'], 'columnSpan' => 1],
                        ['id' => 'supervision_ratio', 'type' => 'select', 'label' => 'Recommended Supervision Ratio', 'options' => ['1:1 therapist', '1:2 (therapist + assistant)', '2:1 (2 therapists for complex needs)', 'Group (1:4 max)'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Goals & Treatment Plan',
                    'icon' => 'heroicon-o-flag',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'hydrotherapy_goals', 'type' => 'textarea', 'label' => 'Hydrotherapy Goals', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'specific_techniques', 'type' => 'textarea', 'label' => 'Planned Techniques (Halliwick, Bad Ragaz, free play, etc.)', 'columnSpan' => 2],
                        ['id' => 'frequency', 'type' => 'select', 'label' => 'Planned Frequency', 'options' => ['Weekly', '2x per week', 'Fortnightly', 'Monthly'], 'columnSpan' => 1],
                        ['id' => 'session_duration', 'type' => 'select', 'label' => 'Session Duration', 'options' => ['15 minutes', '30 minutes', '45 minutes', '60 minutes'], 'columnSpan' => 1],
                        ['id' => 'outcome', 'type' => 'radio', 'label' => 'Screening Outcome', 'options' => ['Suitable for Hydrotherapy', 'Not suitable at this time', 'Suitable with precautions (document above)'], 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'therapist_name', 'type' => 'text', 'label' => 'Therapist Name', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'screening_date', 'type' => 'date', 'label' => 'Screening Date', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }
}
