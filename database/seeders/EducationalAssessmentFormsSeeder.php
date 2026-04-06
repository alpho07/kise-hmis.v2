<?php

namespace Database\Seeders;

use App\Models\AssessmentFormSchema;
use Illuminate\Database\Seeder;

class EducationalAssessmentFormsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding Educational Assessment Forms...');

        // 1. INITIAL EDUCATIONAL SCREENING & REFERRAL TOOL (IESRT)
        AssessmentFormSchema::create([
            'name' => 'Initial Educational Screening & Referral Tool (IESRT)',
            'slug' => 'edu-iesrt',
            'version' => 1,
            'category' => 'educational assessment',
            'description' => 'Initial educational screening to determine learning needs, barriers, and appropriate referral pathway',
            'estimated_minutes' => 30,
            'allow_draft' => true,
            'allow_partial_submission' => false,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getIesrtSchema(),
        ]);
        $this->command->info('  ✓ Initial Educational Screening & Referral Tool (IESRT)');

        // 2. ASD EDUCATIONAL ASSESSMENT TOOL
        AssessmentFormSchema::create([
            'name' => 'ASD Educational Assessment Tool',
            'slug' => 'edu-asd-assessment',
            'version' => 1,
            'category' => 'educational assessment',
            'description' => 'Specialised educational assessment for learners with Autism Spectrum Disorder',
            'estimated_minutes' => 60,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getAsdAssessmentSchema(),
        ]);
        $this->command->info('  ✓ ASD Educational Assessment Tool');

        // 3. FUNCTIONAL ASSESSMENT BACKGROUND INFORMATION
        AssessmentFormSchema::create([
            'name' => 'Functional Assessment Background Information',
            'slug' => 'edu-functional-background',
            'version' => 1,
            'category' => 'educational assessment',
            'description' => 'Comprehensive background information form for functional educational assessment (existing KISE form)',
            'estimated_minutes' => 40,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getFunctionalBackgroundSchema(),
        ]);
        $this->command->info('  ✓ Functional Assessment Background Information');
    }

    private function getIesrtSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Referral & Background',
                    'icon' => 'heroicon-o-document-text',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'referral_source', 'type' => 'select', 'label' => 'Referred by', 'options' => ['Parent / Guardian', 'Class Teacher', 'Head Teacher / Principal', 'Medical Officer', 'Social Worker', 'Self-referral (adult learner)', 'Other'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'referral_date', 'type' => 'date', 'label' => 'Referral Date', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'referral_reason', 'type' => 'textarea', 'label' => 'Reason for Referral (describe learning concern)', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'school_name', 'type' => 'text', 'label' => 'Current School / Institution', 'columnSpan' => 1],
                        ['id' => 'current_class', 'type' => 'text', 'label' => 'Current Class / Grade', 'columnSpan' => 1],
                        ['id' => 'previous_assessments', 'type' => 'radio', 'label' => 'Previous Educational Assessments', 'options' => ['Yes', 'No', 'Unknown'], 'columnSpan' => 1],
                        ['id' => 'previous_assessments_details', 'type' => 'text', 'label' => 'If yes, specify', 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Academic Performance',
                    'icon' => 'heroicon-o-academic-cap',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'reading_level', 'type' => 'select', 'label' => 'Reading Level (compared to peers)', 'options' => ['At or above grade level', 'Slightly below (1 grade)', 'Significantly below (2+ grades)', 'Non-reader', 'Not applicable (pre-school)'], 'columnSpan' => 1],
                        ['id' => 'writing_level', 'type' => 'select', 'label' => 'Writing Level (compared to peers)', 'options' => ['At or above grade level', 'Slightly below (1 grade)', 'Significantly below (2+ grades)', 'Non-writer', 'Not applicable'], 'columnSpan' => 1],
                        ['id' => 'numeracy_level', 'type' => 'select', 'label' => 'Numeracy Level (compared to peers)', 'options' => ['At or above grade level', 'Slightly below', 'Significantly below', 'Unable to engage with numbers', 'Not applicable'], 'columnSpan' => 1],
                        ['id' => 'overall_school_performance', 'type' => 'radio', 'label' => 'Overall School Performance', 'options' => ['Performing well', 'Struggling in some areas', 'Significantly struggling', 'Not yet in school'], 'columnSpan' => 1],
                        ['id' => 'subjects_concern', 'type' => 'textarea', 'label' => 'Subjects / Areas of Greatest Concern', 'columnSpan' => 2],
                        ['id' => 'strengths_academic', 'type' => 'textarea', 'label' => 'Academic Strengths', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Learning Behaviour Indicators',
                    'icon' => 'heroicon-o-eye',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'lb_note', 'type' => 'placeholder', 'label' => 'Check all that apply (teacher / parent report)'],
                        ['id' => 'lb_attention', 'type' => 'checkbox', 'label' => 'Difficulty sustaining attention / easily distracted'],
                        ['id' => 'lb_instructions', 'type' => 'checkbox', 'label' => 'Difficulty following multi-step instructions'],
                        ['id' => 'lb_memory', 'type' => 'checkbox', 'label' => 'Poor memory / forgets learned material quickly'],
                        ['id' => 'lb_reading_fluency', 'type' => 'checkbox', 'label' => 'Slow, laboured reading or decoding difficulties'],
                        ['id' => 'lb_reversals', 'type' => 'checkbox', 'label' => 'Letter/number reversals beyond expected age (after age 7)'],
                        ['id' => 'lb_comprehension', 'type' => 'checkbox', 'label' => 'Poor reading or listening comprehension'],
                        ['id' => 'lb_written_expression', 'type' => 'checkbox', 'label' => 'Difficulty expressing ideas in writing'],
                        ['id' => 'lb_maths_reasoning', 'type' => 'checkbox', 'label' => 'Difficulty with maths reasoning / problem solving'],
                        ['id' => 'lb_organisation', 'type' => 'checkbox', 'label' => 'Poor organisation of tasks and materials'],
                        ['id' => 'lb_social_interaction', 'type' => 'checkbox', 'label' => 'Social interaction difficulties with peers'],
                        ['id' => 'lb_behaviour', 'type' => 'checkbox', 'label' => 'Challenging classroom behaviour (aggression, withdrawal, non-compliance)'],
                        ['id' => 'lb_fatigue', 'type' => 'checkbox', 'label' => 'Excessive fatigue during school tasks'],
                    ],
                ],
                [
                    'title' => 'Language & Communication',
                    'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'primary_language', 'type' => 'text', 'label' => 'Primary Language at Home', 'columnSpan' => 1],
                        ['id' => 'language_instruction', 'type' => 'text', 'label' => 'Language of Instruction at School', 'columnSpan' => 1],
                        ['id' => 'expressive_language', 'type' => 'radio', 'label' => 'Expressive Language (speaking)', 'options' => ['Age appropriate', 'Delayed', 'Significantly delayed', 'Non-verbal'], 'columnSpan' => 1],
                        ['id' => 'receptive_language', 'type' => 'radio', 'label' => 'Receptive Language (understanding)', 'options' => ['Age appropriate', 'Delayed', 'Significantly delayed', 'Unable to assess'], 'columnSpan' => 1],
                        ['id' => 'speech_concerns', 'type' => 'textarea', 'label' => 'Speech / Communication Concerns', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Screening Outcome & Recommendations',
                    'icon' => 'heroicon-o-check-badge',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'screening_impression', 'type' => 'textarea', 'label' => 'Screening Impression Summary', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'recommended_assessment', 'type' => 'radio', 'label' => 'Recommended Next Step', 'options' => ['Full Psychoeducational Assessment', 'ASD-Specific Educational Assessment', 'Functional Assessment', 'Speech & Language Assessment', 'Occupational Therapy Assessment', 'No further assessment needed at this time'], 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'suspected_area', 'type' => 'textarea', 'label' => 'Suspected Areas of Learning Difficulty', 'columnSpan' => 2],
                        ['id' => 'urgency', 'type' => 'radio', 'label' => 'Urgency', 'options' => ['Urgent', 'Routine'], 'columnSpan' => 1],
                        ['id' => 'screener_name', 'type' => 'text', 'label' => 'Screened by', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'screening_date', 'type' => 'date', 'label' => 'Date', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }

    private function getAsdAssessmentSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Referral & Background',
                    'icon' => 'heroicon-o-document-text',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'referral_reason', 'type' => 'textarea', 'label' => 'Reason for ASD Educational Assessment', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'confirmed_diagnosis', 'type' => 'radio', 'label' => 'ASD Diagnosis Confirmed?', 'options' => ['Yes — confirmed by clinician', 'Suspected / under investigation', 'No diagnosis — assessing for ASD features'], 'columnSpan' => 2],
                        ['id' => 'diagnosis_source', 'type' => 'text', 'label' => 'Diagnosed by (name / facility)', 'columnSpan' => 1],
                        ['id' => 'diagnosis_date', 'type' => 'date', 'label' => 'Date of Diagnosis', 'columnSpan' => 1],
                        ['id' => 'current_support', 'type' => 'textarea', 'label' => 'Current Support / Interventions in Place', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Social Communication & Interaction',
                    'icon' => 'heroicon-o-users',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'eye_contact', 'type' => 'select', 'label' => 'Eye Contact', 'options' => ['Consistent and appropriate', 'Inconsistent', 'Minimal', 'Absent', 'Avoidant'], 'columnSpan' => 1],
                        ['id' => 'joint_attention', 'type' => 'select', 'label' => 'Joint Attention (pointing, sharing interests)', 'options' => ['Present and functional', 'Emerging', 'Limited', 'Absent'], 'columnSpan' => 1],
                        ['id' => 'peer_interaction', 'type' => 'select', 'label' => 'Peer Interaction', 'options' => ['Seeks peers appropriately', 'Prefers adults', 'Parallel play only', 'Avoids peers', 'Unaware of peers'], 'columnSpan' => 1],
                        ['id' => 'reciprocal_communication', 'type' => 'textarea', 'label' => 'Reciprocal Communication Description (turn-taking, conversation)', 'columnSpan' => 2],
                        ['id' => 'social_scripts', 'type' => 'textarea', 'label' => 'Use of Social Scripts / Echolalia', 'columnSpan' => 2],
                        ['id' => 'emotional_recognition', 'type' => 'select', 'label' => 'Emotional Recognition in Others', 'options' => ['Intact', 'Emerging', 'Limited', 'Unable to assess'], 'columnSpan' => 1],
                        ['id' => 'empathy', 'type' => 'select', 'label' => 'Empathy / Perspective-Taking', 'options' => ['Age appropriate', 'Limited', 'Significantly impaired', 'Unable to assess'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Restricted, Repetitive Behaviours & Interests',
                    'icon' => 'heroicon-o-arrow-path',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'rrb_note', 'type' => 'placeholder', 'label' => 'Check all observed / reported'],
                        ['id' => 'rrb_routines', 'type' => 'checkbox', 'label' => 'Insistence on sameness / rigid routines — distress with changes'],
                        ['id' => 'rrb_restricted_interests', 'type' => 'checkbox', 'label' => 'Highly restricted, intense interests (e.g., specific topic, objects)'],
                        ['id' => 'rrb_repetitive_motor', 'type' => 'checkbox', 'label' => 'Repetitive motor movements (hand flapping, rocking, spinning)'],
                        ['id' => 'rrb_object_use', 'type' => 'checkbox', 'label' => 'Unusual or repetitive use of objects (lining up, spinning)'],
                        ['id' => 'rrb_sensory_unusual', 'type' => 'checkbox', 'label' => 'Unusual sensory interests (smelling, licking, visual inspection)'],
                        ['id' => 'rrb_self_injurious', 'type' => 'checkbox', 'label' => 'Self-injurious behaviour (head-banging, biting, scratching)'],
                        ['id' => 'rrb_details', 'type' => 'textarea', 'label' => 'Details / Frequency of Identified Behaviours', 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Communication Profile',
                    'icon' => 'heroicon-o-chat-bubble-oval-left-ellipsis',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'verbal_status', 'type' => 'select', 'label' => 'Verbal Communication Status', 'options' => ['Verbal — age appropriate', 'Verbal — reduced / echolalic', 'Minimally verbal (few words)', 'Non-verbal', 'Uses AAC (specify)'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'aac_type', 'type' => 'text', 'label' => 'If AAC, specify type', 'columnSpan' => 1],
                        ['id' => 'language_level', 'type' => 'textarea', 'label' => 'Language Level (vocabulary, sentence structure, comprehension)', 'columnSpan' => 2],
                        ['id' => 'pragmatics', 'type' => 'textarea', 'label' => 'Pragmatic Language Skills (greetings, topic maintenance, inferencing)', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Learning & Academic Profile',
                    'icon' => 'heroicon-o-academic-cap',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'learning_strengths', 'type' => 'textarea', 'label' => 'Learning Strengths (visual, memory, specific subjects)', 'columnSpan' => 2],
                        ['id' => 'learning_challenges', 'type' => 'textarea', 'label' => 'Learning Challenges in Educational Setting', 'columnSpan' => 2],
                        ['id' => 'task_completion', 'type' => 'select', 'label' => 'Independent Task Completion', 'options' => ['Consistent', 'With prompting', 'Requires 1:1 support', 'Unable without constant support'], 'columnSpan' => 1],
                        ['id' => 'transitions_in_class', 'type' => 'select', 'label' => 'Transitions Between Activities', 'options' => ['Manages well', 'Mild difficulty', 'Significant difficulty', 'Extreme distress'], 'columnSpan' => 1],
                        ['id' => 'attention_span', 'type' => 'text', 'label' => 'Estimated Attention Span (minutes)', 'columnSpan' => 1],
                        ['id' => 'preferred_learning_modality', 'type' => 'select', 'label' => 'Preferred Learning Modality', 'options' => ['Visual', 'Auditory', 'Kinaesthetic', 'Mixed', 'Unclear'], 'columnSpan' => 1],
                        ['id' => 'literacy_level', 'type' => 'textarea', 'label' => 'Literacy Level (reading, writing, spelling)', 'columnSpan' => 2],
                        ['id' => 'numeracy_level', 'type' => 'textarea', 'label' => 'Numeracy Level', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Functional Skills in School',
                    'icon' => 'heroicon-o-building-library',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'self_care_school', 'type' => 'select', 'label' => 'Self-Care at School (toileting, eating)', 'options' => ['Independent', 'With support', 'Fully dependent'], 'columnSpan' => 1],
                        ['id' => 'classroom_behaviour', 'type' => 'textarea', 'label' => 'Classroom Behaviour Description', 'columnSpan' => 2],
                        ['id' => 'sensory_needs_school', 'type' => 'textarea', 'label' => 'Sensory Needs in School Environment', 'columnSpan' => 2],
                        ['id' => 'existing_adaptations', 'type' => 'textarea', 'label' => 'Existing Classroom Adaptations / Supports', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Assessment Summary & Recommendations',
                    'icon' => 'heroicon-o-light-bulb',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'overall_impression', 'type' => 'textarea', 'label' => 'Educational Assessment Summary', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'educational_needs', 'type' => 'textarea', 'label' => 'Identified Educational Support Needs', 'columnSpan' => 2],
                        ['id' => 'placement_recommendation', 'type' => 'select', 'label' => 'Placement Recommendation', 'options' => ['Mainstream with support', 'Mainstream with resource room', 'Special unit in mainstream school', 'Special school', 'Home-based programme', 'Early intervention centre', 'Review in 6 months'], 'columnSpan' => 1],
                        ['id' => 'iep_recommended', 'type' => 'radio', 'label' => 'Individual Education Plan (IEP) Recommended', 'options' => ['Yes', 'No', 'Already in place'], 'columnSpan' => 1],
                        ['id' => 'iep_goals', 'type' => 'textarea', 'label' => 'Suggested IEP Goal Areas', 'columnSpan' => 2],
                        ['id' => 'additional_referrals', 'type' => 'textarea', 'label' => 'Additional Referrals (SLT, OT, Psychology)', 'columnSpan' => 2],
                        ['id' => 'assessor_name', 'type' => 'text', 'label' => 'Assessed by', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'assessment_date', 'type' => 'date', 'label' => 'Date', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }

    private function getFunctionalBackgroundSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Informant Details',
                    'icon' => 'heroicon-o-user-circle',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'informant_name', 'type' => 'text', 'label' => 'Name of Person Completing This Form', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'relationship', 'type' => 'select', 'label' => 'Relationship to Client', 'options' => ['Parent', 'Guardian', 'Teacher', 'Social Worker', 'Caregiver', 'Self (adult)', 'Other'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'date_completed', 'type' => 'date', 'label' => 'Date Completed', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'informant_contact', 'type' => 'text', 'label' => 'Contact Number', 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Developmental & Medical History',
                    'icon' => 'heroicon-o-heart',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'birth_complications', 'type' => 'textarea', 'label' => 'Birth / Perinatal Complications', 'columnSpan' => 2],
                        ['id' => 'developmental_milestones', 'type' => 'textarea', 'label' => 'Developmental Milestones (delayed areas)', 'columnSpan' => 2],
                        ['id' => 'medical_diagnoses', 'type' => 'textarea', 'label' => 'Medical Diagnoses / Conditions', 'columnSpan' => 2],
                        ['id' => 'medications', 'type' => 'textarea', 'label' => 'Current Medications', 'columnSpan' => 1],
                        ['id' => 'hospitalisations', 'type' => 'textarea', 'label' => 'Significant Hospitalisations', 'columnSpan' => 1],
                        ['id' => 'vision_hearing', 'type' => 'textarea', 'label' => 'Vision / Hearing Status (screened / corrected?)', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Family & Social Background',
                    'icon' => 'heroicon-o-home',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'family_composition', 'type' => 'textarea', 'label' => 'Family Composition (who does the child live with?)', 'columnSpan' => 2],
                        ['id' => 'languages_home', 'type' => 'text', 'label' => 'Language(s) Spoken at Home', 'columnSpan' => 1],
                        ['id' => 'socioeconomic', 'type' => 'select', 'label' => 'Socioeconomic Status (rough estimate)', 'options' => ['Low income', 'Lower-middle income', 'Middle income', 'Upper income', 'Not disclosed'], 'columnSpan' => 1],
                        ['id' => 'family_disability_history', 'type' => 'textarea', 'label' => 'Family History of Learning / Developmental Disabilities', 'columnSpan' => 2],
                        ['id' => 'significant_life_events', 'type' => 'textarea', 'label' => 'Significant Life Events Affecting Learning (trauma, loss, relocation)', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Educational History',
                    'icon' => 'heroicon-o-academic-cap',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'first_school_age', 'type' => 'text', 'label' => 'Age When First Started School', 'columnSpan' => 1],
                        ['id' => 'schools_attended', 'type' => 'textarea', 'label' => 'Schools Attended (name, type, years)', 'columnSpan' => 2],
                        ['id' => 'school_changes', 'type' => 'textarea', 'label' => 'Reason for School Changes (if any)', 'columnSpan' => 2],
                        ['id' => 'class_repetitions', 'type' => 'radio', 'label' => 'Class Repetitions (repeated a year?)', 'options' => ['Yes', 'No'], 'columnSpan' => 1],
                        ['id' => 'class_repetition_details', 'type' => 'text', 'label' => 'If yes, which class and why', 'columnSpan' => 1],
                        ['id' => 'previous_support', 'type' => 'textarea', 'label' => 'Previous Special Education Support / Therapy', 'columnSpan' => 2],
                        ['id' => 'school_attendance', 'type' => 'radio', 'label' => 'Current School Attendance', 'options' => ['Regular', 'Irregular / frequent absences', 'Not in school'], 'columnSpan' => 1],
                        ['id' => 'attendance_reason', 'type' => 'text', 'label' => 'If irregular/absent, reason', 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Current Functioning & Strengths',
                    'icon' => 'heroicon-o-star',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'strengths', 'type' => 'textarea', 'label' => 'Key Strengths & Interests', 'columnSpan' => 2],
                        ['id' => 'daily_living_independence', 'type' => 'textarea', 'label' => 'Daily Living Skills (self-care, household tasks)', 'columnSpan' => 2],
                        ['id' => 'leisure_activities', 'type' => 'textarea', 'label' => 'Leisure Activities & Hobbies', 'columnSpan' => 2],
                        ['id' => 'communication_daily', 'type' => 'textarea', 'label' => 'Communication in Daily Life', 'columnSpan' => 2],
                        ['id' => 'behaviour_concerns', 'type' => 'textarea', 'label' => 'Behaviour Concerns at Home / Community', 'columnSpan' => 2],
                        ['id' => 'parent_priorities', 'type' => 'textarea', 'label' => 'Parent / Guardian Priorities for Assessment', 'validation' => ['required'], 'columnSpan' => 2],
                    ],
                ],
            ],
        ];
    }
}
