<?php

namespace Database\Seeders;

use App\Models\AssessmentFormSchema;
use Illuminate\Database\Seeder;

class OccupationalTherapyFormsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding Occupational Therapy Forms...');

        // 1. PAEDIATRIC OT REFERRAL SCREENING
        AssessmentFormSchema::create([
            'name' => 'Paediatric OT Referral Screening Tool',
            'slug' => 'ot-paediatric-screening',
            'version' => 1,
            'category' => 'occupational therapy',
            'description' => 'Comprehensive paediatric OT referral screening including birth, medical and developmental history',
            'estimated_minutes' => 45,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getPaediatricScreeningSchema(),
        ]);
        $this->command->info('  ✓ Paediatric OT Referral Screening Tool');

        // 2. ADULT OT INITIAL ASSESSMENT
        AssessmentFormSchema::create([
            'name' => 'Initial Occupational Therapy Assessment (Adult)',
            'slug' => 'ot-adult-initial-assessment',
            'version' => 1,
            'category' => 'occupational therapy',
            'description' => 'Comprehensive initial OT assessment for adult patients (18+)',
            'estimated_minutes' => 60,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getAdultInitialAssessmentSchema(),
        ]);
        $this->command->info('  ✓ Initial OT Assessment (Adult)');
    }

    private function getPaediatricScreeningSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Referral Information',
                    'icon' => 'heroicon-o-document-arrow-up',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'referral_source', 'type' => 'select', 'label' => 'Referred by', 'options' => ['Paediatrician', 'Medical Officer', 'Physiotherapist', 'Teacher / School', 'Parent / Guardian', 'Other'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'referral_date', 'type' => 'date', 'label' => 'Referral Date', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'reason_for_referral', 'type' => 'textarea', 'label' => 'Reason for Referral', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'presenting_diagnosis', 'type' => 'text', 'label' => 'Presenting Diagnosis', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Birth & Prenatal History',
                    'icon' => 'heroicon-o-heart',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'pregnancy_complications', 'type' => 'radio', 'label' => 'Pregnancy Complications', 'options' => ['Yes', 'No'], 'columnSpan' => 1],
                        ['id' => 'pregnancy_details', 'type' => 'text', 'label' => 'If yes, specify', 'columnSpan' => 1],
                        ['id' => 'maternal_health', 'type' => 'textarea', 'label' => 'Maternal Health During Pregnancy (infections, medications, alcohol/substance use)', 'columnSpan' => 2],
                        ['id' => 'delivery_type', 'type' => 'select', 'label' => 'Mode of Delivery', 'options' => ['Normal vaginal delivery', 'Assisted (forceps/vacuum)', 'Elective C-section', 'Emergency C-section'], 'columnSpan' => 1],
                        ['id' => 'gestation_weeks', 'type' => 'text', 'label' => 'Gestational Age at Birth (weeks)', 'columnSpan' => 1],
                        ['id' => 'birth_weight_kg', 'type' => 'text', 'label' => 'Birth Weight (kg)', 'columnSpan' => 1],
                        ['id' => 'apgar', 'type' => 'text', 'label' => 'APGAR Score (if known)', 'columnSpan' => 1],
                        ['id' => 'nicu', 'type' => 'radio', 'label' => 'NICU Admission', 'options' => ['Yes', 'No'], 'columnSpan' => 1],
                        ['id' => 'nicu_details', 'type' => 'text', 'label' => 'If yes, reason and duration', 'columnSpan' => 1],
                        ['id' => 'birth_complications', 'type' => 'textarea', 'label' => 'Other Birth Complications', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Postnatal & Medical History',
                    'icon' => 'heroicon-o-clipboard-document',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'postnatal_illnesses', 'type' => 'textarea', 'label' => 'Significant Illnesses in First Year', 'columnSpan' => 2],
                        ['id' => 'hospitalizations', 'type' => 'textarea', 'label' => 'Hospitalisations (reason and age)', 'columnSpan' => 2],
                        ['id' => 'surgeries', 'type' => 'textarea', 'label' => 'Surgeries / Procedures', 'columnSpan' => 1],
                        ['id' => 'current_medications', 'type' => 'textarea', 'label' => 'Current Medications', 'columnSpan' => 1],
                        ['id' => 'seizures', 'type' => 'radio', 'label' => 'History of Seizures', 'options' => ['Yes', 'No'], 'columnSpan' => 1],
                        ['id' => 'seizure_details', 'type' => 'text', 'label' => 'If yes, frequency and management', 'columnSpan' => 1],
                        ['id' => 'hearing_vision_concerns', 'type' => 'textarea', 'label' => 'Hearing / Vision Concerns', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Motor Developmental History',
                    'icon' => 'heroicon-o-arrow-trending-up',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'motor_note', 'type' => 'placeholder', 'label' => 'Record age at attainment (months). Write "Not yet" if not achieved.'],
                        ['id' => 'ms_head_control', 'type' => 'text', 'label' => 'Head Control (~3m)', 'columnSpan' => 1],
                        ['id' => 'ms_rolling', 'type' => 'text', 'label' => 'Rolling (~5m)', 'columnSpan' => 1],
                        ['id' => 'ms_sitting', 'type' => 'text', 'label' => 'Sitting independently (~7-9m)', 'columnSpan' => 1],
                        ['id' => 'ms_pincer_grasp', 'type' => 'text', 'label' => 'Pincer grasp (~9-12m)', 'columnSpan' => 1],
                        ['id' => 'ms_standing', 'type' => 'text', 'label' => 'Standing with support (~10m)', 'columnSpan' => 1],
                        ['id' => 'ms_walking', 'type' => 'text', 'label' => 'Walking independently (~12-15m)', 'columnSpan' => 1],
                        ['id' => 'ms_hand_dominance', 'type' => 'text', 'label' => 'Hand Dominance established (~4-6 years)', 'columnSpan' => 1],
                        ['id' => 'motor_regression', 'type' => 'radio', 'label' => 'Loss of previously acquired skills (regression)', 'options' => ['Yes', 'No'], 'columnSpan' => 1],
                        ['id' => 'motor_regression_details', 'type' => 'text', 'label' => 'If yes, describe', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Sensory Processing',
                    'icon' => 'heroicon-o-sparkles',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'sensory_note', 'type' => 'placeholder', 'label' => 'Parent / caregiver reports — check all that apply'],
                        ['id' => 'tactile_sensitivity', 'type' => 'checkbox', 'label' => 'Tactile sensitivity — dislikes certain textures, avoids touch, refuses certain clothing'],
                        ['id' => 'oral_sensitivity', 'type' => 'checkbox', 'label' => 'Oral sensitivity — selective eating, dislikes toothbrushing, mouths objects excessively'],
                        ['id' => 'sound_sensitivity', 'type' => 'checkbox', 'label' => 'Auditory sensitivity — covers ears, distressed by loud sounds'],
                        ['id' => 'visual_sensitivity', 'type' => 'checkbox', 'label' => 'Visual sensitivity — bothered by bright lights, avoids eye contact'],
                        ['id' => 'movement_seeking', 'type' => 'checkbox', 'label' => 'Vestibular seeking — spins, jumps, swings excessively'],
                        ['id' => 'movement_avoidance', 'type' => 'checkbox', 'label' => 'Vestibular avoidance — fearful of movement, dislikes swings / heights'],
                        ['id' => 'proprioception_seeking', 'type' => 'checkbox', 'label' => 'Proprioceptive seeking — crashing into objects, rough play, body pressure needs'],
                        ['id' => 'self_regulation_diff', 'type' => 'checkbox', 'label' => 'Difficulty with self-regulation — frequent meltdowns, difficulty transitioning'],
                    ],
                ],
                [
                    'title' => 'Fine Motor & Self-Care Skills',
                    'icon' => 'heroicon-o-hand-raised',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'handwriting', 'type' => 'select', 'label' => 'Handwriting / Pre-writing (age-appropriate)', 'options' => ['Age appropriate', 'Below age level', 'Not yet expected', 'N/A'], 'columnSpan' => 1],
                        ['id' => 'scissors', 'type' => 'select', 'label' => 'Scissor Use', 'options' => ['Age appropriate', 'Emerging', 'Unable', 'N/A'], 'columnSpan' => 1],
                        ['id' => 'self_feeding', 'type' => 'select', 'label' => 'Self-Feeding', 'options' => ['Independent', 'Supervised', 'Partial assist', 'Full assist'], 'columnSpan' => 1],
                        ['id' => 'dressing', 'type' => 'select', 'label' => 'Dressing', 'options' => ['Independent', 'Supervised', 'Partial assist', 'Full assist'], 'columnSpan' => 1],
                        ['id' => 'toileting', 'type' => 'select', 'label' => 'Toileting', 'options' => ['Independent', 'Supervised', 'Partial assist', 'Full assist', 'N/A (too young)'], 'columnSpan' => 1],
                        ['id' => 'play_skills', 'type' => 'textarea', 'label' => 'Play Skills & Interests', 'columnSpan' => 2],
                        ['id' => 'school_participation', 'type' => 'textarea', 'label' => 'School / Classroom Participation Concerns', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Family & Home Environment',
                    'icon' => 'heroicon-o-home',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'primary_caregiver', 'type' => 'text', 'label' => 'Primary Caregiver', 'columnSpan' => 1],
                        ['id' => 'family_structure', 'type' => 'text', 'label' => 'Family Structure', 'columnSpan' => 1],
                        ['id' => 'home_equipment', 'type' => 'textarea', 'label' => 'Home Adaptations / Equipment in Use', 'columnSpan' => 2],
                        ['id' => 'family_concerns', 'type' => 'textarea', 'label' => 'Family\'s Primary Concerns & Priorities', 'columnSpan' => 2],
                        ['id' => 'cultural_considerations', 'type' => 'textarea', 'label' => 'Cultural / Religious Considerations Relevant to OT', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Screening Outcome',
                    'icon' => 'heroicon-o-check-badge',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'screening_result', 'type' => 'radio', 'label' => 'Screening Outcome', 'options' => ['Eligible — Proceed to Full OT Assessment', 'Not eligible at this time', 'Refer to Other Service', 'Watchful Waiting — Review in 3 months'], 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'priority', 'type' => 'radio', 'label' => 'Priority', 'options' => ['Urgent', 'High', 'Routine'], 'columnSpan' => 1],
                        ['id' => 'areas_to_assess', 'type' => 'textarea', 'label' => 'Priority Areas for Full Assessment', 'columnSpan' => 2],
                        ['id' => 'screener_name', 'type' => 'text', 'label' => 'Screened by', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'screening_date', 'type' => 'date', 'label' => 'Date', 'validation' => ['required'], 'columnSpan' => 1],
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
                    'title' => 'Referral & Presenting Problem',
                    'icon' => 'heroicon-o-document-text',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'referral_source', 'type' => 'text', 'label' => 'Referred by', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'referral_reason', 'type' => 'textarea', 'label' => 'Reason for Referral / Chief Complaint', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'diagnosis', 'type' => 'text', 'label' => 'Medical Diagnosis', 'columnSpan' => 1],
                        ['id' => 'onset', 'type' => 'select', 'label' => 'Onset', 'options' => ['Sudden', 'Gradual', 'Following injury', 'Congenital', 'Unknown'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Medical & Psychological History',
                    'icon' => 'heroicon-o-heart',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'pmh', 'type' => 'textarea', 'label' => 'Past Medical History', 'columnSpan' => 2],
                        ['id' => 'surgeries', 'type' => 'textarea', 'label' => 'Surgeries / Procedures', 'columnSpan' => 1],
                        ['id' => 'medications', 'type' => 'textarea', 'label' => 'Current Medications', 'columnSpan' => 1],
                        ['id' => 'mental_health', 'type' => 'textarea', 'label' => 'Mental Health / Psychiatric History', 'columnSpan' => 2],
                        ['id' => 'substance_use', 'type' => 'text', 'label' => 'Substance Use (if relevant)', 'columnSpan' => 1],
                        ['id' => 'allergies', 'type' => 'text', 'label' => 'Allergies', 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Occupational Profile',
                    'icon' => 'heroicon-o-briefcase',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'occupation', 'type' => 'text', 'label' => 'Occupation / Profession', 'columnSpan' => 1],
                        ['id' => 'work_status', 'type' => 'select', 'label' => 'Work Status', 'options' => ['Employed (full-time)', 'Employed (part-time)', 'Unemployed', 'Student', 'Retired', 'Unable to work due to disability'], 'columnSpan' => 1],
                        ['id' => 'roles', 'type' => 'textarea', 'label' => 'Major Life Roles (parent, carer, volunteer, etc.)', 'columnSpan' => 2],
                        ['id' => 'meaningful_activities', 'type' => 'textarea', 'label' => 'Meaningful Activities / Hobbies (prior to onset)', 'columnSpan' => 2],
                        ['id' => 'living_situation', 'type' => 'select', 'label' => 'Living Situation', 'options' => ['Alone', 'With family', 'With partner', 'Supported living', 'Institution / care home'], 'columnSpan' => 1],
                        ['id' => 'home_environment', 'type' => 'textarea', 'label' => 'Home Environment Description (stairs, access)', 'columnSpan' => 2],
                        ['id' => 'support_available', 'type' => 'textarea', 'label' => 'Support Available (informal carers, community services)', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Activities of Daily Living',
                    'icon' => 'heroicon-o-user',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'adl_self_care', 'type' => 'select', 'label' => 'Personal Care (bathing, grooming, dressing)', 'options' => ['Independent', 'Requires supervision', 'Requires moderate assist', 'Fully dependent'], 'columnSpan' => 1],
                        ['id' => 'adl_feeding', 'type' => 'select', 'label' => 'Feeding & Meal Preparation', 'options' => ['Independent', 'Requires supervision', 'Requires moderate assist', 'Fully dependent'], 'columnSpan' => 1],
                        ['id' => 'adl_mobility', 'type' => 'select', 'label' => 'Functional Mobility (transfers, ambulation)', 'options' => ['Independent', 'With device', 'Requires supervision', 'Requires assist'], 'columnSpan' => 1],
                        ['id' => 'adl_toileting', 'type' => 'select', 'label' => 'Toileting', 'options' => ['Independent', 'Requires supervision', 'Requires assist', 'Fully dependent'], 'columnSpan' => 1],
                        ['id' => 'iadl_home', 'type' => 'select', 'label' => 'Home Management (cleaning, laundry)', 'options' => ['Independent', 'With difficulty', 'With assist', 'Unable'], 'columnSpan' => 1],
                        ['id' => 'iadl_community', 'type' => 'select', 'label' => 'Community Mobility (transport, shopping)', 'options' => ['Independent', 'With difficulty', 'With support', 'Unable'], 'columnSpan' => 1],
                        ['id' => 'adl_concerns', 'type' => 'textarea', 'label' => 'Key ADL Concerns (client\'s own words)', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Standardised Assessments & Observations',
                    'icon' => 'heroicon-o-magnifying-glass',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'cognitive_screen', 'type' => 'select', 'label' => 'Cognitive Screening Tool Used', 'options' => ['MMSE', 'MoCA', 'Clock Drawing', 'KICA', 'Not performed', 'Other'], 'columnSpan' => 1],
                        ['id' => 'cognitive_score', 'type' => 'text', 'label' => 'Score / Summary', 'columnSpan' => 1],
                        ['id' => 'upper_limb', 'type' => 'textarea', 'label' => 'Upper Limb Function (ROM, strength, coordination, spasticity)', 'columnSpan' => 2],
                        ['id' => 'hand_function', 'type' => 'textarea', 'label' => 'Hand Function (grip, pinch, dexterity)', 'columnSpan' => 2],
                        ['id' => 'perceptual_visual', 'type' => 'textarea', 'label' => 'Perceptual / Visual Processing Observations', 'columnSpan' => 2],
                        ['id' => 'fatigue_pain', 'type' => 'textarea', 'label' => 'Fatigue / Pain Impact on Occupational Performance', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Goals & Intervention Plan',
                    'icon' => 'heroicon-o-flag',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'client_goals', 'type' => 'textarea', 'label' => 'Client\'s Goals (verbatim)', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'short_term_goals', 'type' => 'textarea', 'label' => 'Short-Term OT Goals (2-4 weeks)', 'columnSpan' => 1],
                        ['id' => 'long_term_goals', 'type' => 'textarea', 'label' => 'Long-Term OT Goals (3-6 months)', 'columnSpan' => 1],
                        ['id' => 'interventions', 'type' => 'textarea', 'label' => 'Planned Interventions', 'columnSpan' => 2],
                        ['id' => 'equipment_recommended', 'type' => 'textarea', 'label' => 'Equipment / Adaptive Devices Recommended', 'columnSpan' => 2],
                        ['id' => 'home_programme', 'type' => 'textarea', 'label' => 'Home Programme', 'columnSpan' => 2],
                        ['id' => 'referrals', 'type' => 'textarea', 'label' => 'Further Referrals Recommended', 'columnSpan' => 2],
                        ['id' => 'therapist_name', 'type' => 'text', 'label' => 'Occupational Therapist', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'assessment_date', 'type' => 'date', 'label' => 'Date of Assessment', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }
}
