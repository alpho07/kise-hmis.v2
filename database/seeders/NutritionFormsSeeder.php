<?php

namespace Database\Seeders;

use App\Models\AssessmentFormSchema;
use Illuminate\Database\Seeder;

class NutritionFormsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding Nutrition Forms...');

        // 1. ADIME NUTRITIONAL ASSESSMENT TOOL
        AssessmentFormSchema::create([
            'name' => 'ADIME Nutritional Assessment Tool',
            'slug' => 'nutrition-adime-assessment',
            'version' => 1,
            'category' => 'nutrition',
            'description' => 'Comprehensive nutritional assessment using ADIME format (Assessment, Diagnosis, Intervention, Monitoring & Evaluation)',
            'estimated_minutes' => 45,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getAdimeAssessmentSchema(),
        ]);
        $this->command->info('  ✓ ADIME Nutritional Assessment Tool');

        // 2. NCP MONITORING & FOLLOW-UP FORM
        AssessmentFormSchema::create([
            'name' => 'Nutrition Care Plan Monitoring Form',
            'slug' => 'nutrition-ncp-monitoring',
            'version' => 1,
            'category' => 'nutrition',
            'description' => 'Follow-up monitoring form for Nutrition Care Plan implementation and progress review',
            'estimated_minutes' => 20,
            'allow_draft' => true,
            'allow_partial_submission' => false,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getNcpMonitoringSchema(),
        ]);
        $this->command->info('  ✓ NCP Monitoring Form');

        // 3. NUTRITION DISCHARGE & REFERRAL FORM
        AssessmentFormSchema::create([
            'name' => 'Nutrition Discharge & Referral Form',
            'slug' => 'nutrition-discharge-referral',
            'version' => 1,
            'category' => 'nutrition',
            'description' => 'Discharge summary and referral form for patients completing nutrition services',
            'estimated_minutes' => 15,
            'allow_draft' => true,
            'allow_partial_submission' => false,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getDischargeReferralSchema(),
        ]);
        $this->command->info('  ✓ Nutrition Discharge & Referral Form');
    }

    private function getAdimeAssessmentSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'A — Assessment: Anthropometric Data',
                    'icon' => 'heroicon-o-calculator',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'weight_kg', 'type' => 'text', 'label' => 'Weight (kg)', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'height_cm', 'type' => 'text', 'label' => 'Height (cm)', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'bmi', 'type' => 'text', 'label' => 'BMI (kg/m²) — auto-calculated if available', 'columnSpan' => 1],
                        ['id' => 'bmi_classification', 'type' => 'select', 'label' => 'BMI Classification', 'options' => ['Underweight (<18.5)', 'Normal (18.5-24.9)', 'Overweight (25-29.9)', 'Obese class I (30-34.9)', 'Obese class II (35-39.9)', 'Obese class III (≥40)', 'N/A (child — use growth chart)'], 'columnSpan' => 1],
                        ['id' => 'weight_change', 'type' => 'text', 'label' => 'Recent Weight Change (kg/timeframe)', 'columnSpan' => 1],
                        ['id' => 'weight_change_significance', 'type' => 'radio', 'label' => 'Significance of Weight Change', 'options' => ['Intentional', 'Unintentional loss', 'Unintentional gain', 'Stable', 'Not applicable'], 'columnSpan' => 1],
                        ['id' => 'muac', 'type' => 'text', 'label' => 'MUAC — Mid-Upper Arm Circumference (cm)', 'columnSpan' => 1],
                        ['id' => 'waist_circumference', 'type' => 'text', 'label' => 'Waist Circumference (cm)', 'columnSpan' => 1],
                        ['id' => 'growth_chart_note', 'type' => 'placeholder', 'label' => 'Children: Plot on WHO/KeMSA growth chart. Record z-scores below.'],
                        ['id' => 'weight_for_age_z', 'type' => 'text', 'label' => 'Weight-for-Age z-score', 'columnSpan' => 1],
                        ['id' => 'height_for_age_z', 'type' => 'text', 'label' => 'Height-for-Age z-score', 'columnSpan' => 1],
                        ['id' => 'weight_for_height_z', 'type' => 'text', 'label' => 'Weight-for-Height z-score', 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'A — Assessment: Biochemical & Clinical',
                    'icon' => 'heroicon-o-beaker',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'lab_haemoglobin', 'type' => 'text', 'label' => 'Haemoglobin (g/dL)', 'columnSpan' => 1],
                        ['id' => 'lab_ferritin', 'type' => 'text', 'label' => 'Ferritin (if available)', 'columnSpan' => 1],
                        ['id' => 'lab_albumin', 'type' => 'text', 'label' => 'Serum Albumin (g/dL)', 'columnSpan' => 1],
                        ['id' => 'lab_glucose', 'type' => 'text', 'label' => 'Random / Fasting Blood Glucose (mmol/L)', 'columnSpan' => 1],
                        ['id' => 'lab_hba1c', 'type' => 'text', 'label' => 'HbA1c (% if diabetic)', 'columnSpan' => 1],
                        ['id' => 'lab_cholesterol', 'type' => 'text', 'label' => 'Total Cholesterol (mmol/L)', 'columnSpan' => 1],
                        ['id' => 'lab_other', 'type' => 'textarea', 'label' => 'Other Relevant Labs', 'columnSpan' => 2],
                        ['id' => 'clinical_signs', 'type' => 'textarea', 'label' => 'Clinical Signs of Nutritional Deficiency (hair, skin, nails, oedema)', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'A — Assessment: Dietary & Food History',
                    'icon' => 'heroicon-o-cake',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'diet_type', 'type' => 'select', 'label' => 'Dietary Pattern', 'options' => ['Omnivore', 'Vegetarian', 'Vegan', 'Cultural/Religious restrictions', 'Therapeutic diet (medical)', 'Tube fed', 'Other'], 'columnSpan' => 1],
                        ['id' => 'meal_frequency', 'type' => 'select', 'label' => 'Meal Frequency', 'options' => ['1 meal/day', '2 meals/day', '3 meals/day', '3 meals + snacks', 'Irregular'], 'columnSpan' => 1],
                        ['id' => 'appetite', 'type' => 'radio', 'label' => 'Current Appetite', 'options' => ['Good', 'Fair', 'Poor', 'Very poor'], 'columnSpan' => 1],
                        ['id' => 'food_security', 'type' => 'radio', 'label' => 'Food Security', 'options' => ['Food secure', 'Mild food insecurity', 'Moderate food insecurity', 'Severe food insecurity'], 'columnSpan' => 1],
                        ['id' => '24hr_recall', 'type' => 'textarea', 'label' => '24-Hour Dietary Recall', 'columnSpan' => 2],
                        ['id' => 'food_allergies_intolerances', 'type' => 'textarea', 'label' => 'Food Allergies / Intolerances', 'columnSpan' => 2],
                        ['id' => 'supplements', 'type' => 'textarea', 'label' => 'Nutritional Supplements in Use', 'columnSpan' => 2],
                        ['id' => 'feeding_difficulties', 'type' => 'textarea', 'label' => 'Feeding Difficulties (dysphagia, poor self-feeding, sensory issues)', 'columnSpan' => 2],
                        ['id' => 'fluid_intake', 'type' => 'text', 'label' => 'Estimated Daily Fluid Intake', 'columnSpan' => 1],
                        ['id' => 'breastfeeding', 'type' => 'radio', 'label' => 'Breastfeeding (if applicable)', 'options' => ['Exclusive breastfeeding', 'Mixed feeding', 'Not breastfeeding', 'N/A'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'A — Assessment: Medical & Social Context',
                    'icon' => 'heroicon-o-user-circle',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'medical_conditions', 'type' => 'textarea', 'label' => 'Relevant Medical Conditions (affecting nutrition)', 'columnSpan' => 2],
                        ['id' => 'gi_issues', 'type' => 'textarea', 'label' => 'GI Symptoms (nausea, vomiting, diarrhoea, constipation)', 'columnSpan' => 2],
                        ['id' => 'physical_activity', 'type' => 'select', 'label' => 'Physical Activity Level', 'options' => ['Sedentary', 'Lightly active', 'Moderately active', 'Very active', 'Non-ambulatory'], 'columnSpan' => 1],
                        ['id' => 'medications_nutrition_impact', 'type' => 'textarea', 'label' => 'Medications with Nutritional Interactions', 'columnSpan' => 2],
                        ['id' => 'nutrition_knowledge', 'type' => 'radio', 'label' => 'Client Nutrition Knowledge', 'options' => ['Good', 'Moderate', 'Limited'], 'columnSpan' => 1],
                        ['id' => 'motivation_to_change', 'type' => 'radio', 'label' => 'Motivation to Change Diet', 'options' => ['High', 'Moderate', 'Low'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'D — Nutrition Diagnosis',
                    'icon' => 'heroicon-o-light-bulb',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'nutrition_diagnosis', 'type' => 'textarea', 'label' => 'Nutrition Diagnosis (using PES format: Problem, Etiology, Signs/Symptoms)', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'malnutrition_screening', 'type' => 'select', 'label' => 'Malnutrition Risk (MUST / STAMP score)', 'options' => ['Low risk', 'Medium risk', 'High risk', 'Not screened'], 'columnSpan' => 1],
                        ['id' => 'malnutrition_score', 'type' => 'text', 'label' => 'Score (if applicable)', 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'I — Nutrition Intervention',
                    'icon' => 'heroicon-o-clipboard-document-check',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'energy_target', 'type' => 'text', 'label' => 'Target Energy Intake (kcal/day)', 'columnSpan' => 1],
                        ['id' => 'protein_target', 'type' => 'text', 'label' => 'Target Protein Intake (g/day)', 'columnSpan' => 1],
                        ['id' => 'nutrition_goals', 'type' => 'textarea', 'label' => 'Nutrition Goals (SMART)', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'dietary_advice', 'type' => 'textarea', 'label' => 'Specific Dietary Advice Given', 'columnSpan' => 2],
                        ['id' => 'meal_plan', 'type' => 'textarea', 'label' => 'Sample Meal Plan / Dietary Pattern Recommended', 'columnSpan' => 2],
                        ['id' => 'supplements_recommended', 'type' => 'textarea', 'label' => 'Supplements Recommended (type, dose, duration)', 'columnSpan' => 2],
                        ['id' => 'feeding_support', 'type' => 'textarea', 'label' => 'Feeding Support Strategies (texture modification, adaptive equipment, positioning)', 'columnSpan' => 2],
                        ['id' => 'education_provided', 'type' => 'textarea', 'label' => 'Nutrition Education Provided (topics covered)', 'columnSpan' => 2],
                        ['id' => 'referrals', 'type' => 'textarea', 'label' => 'Referrals to Other Services', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'M&E — Monitoring & Evaluation Plan',
                    'icon' => 'heroicon-o-arrow-path',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'monitoring_parameters', 'type' => 'textarea', 'label' => 'Parameters to Monitor (weight, labs, intake, symptoms)', 'columnSpan' => 2],
                        ['id' => 'review_frequency', 'type' => 'select', 'label' => 'Review Frequency', 'options' => ['Weekly', 'Fortnightly', 'Monthly', '3-monthly', 'As needed'], 'columnSpan' => 1],
                        ['id' => 'expected_outcomes', 'type' => 'textarea', 'label' => 'Expected Outcomes (timeframe)', 'columnSpan' => 2],
                        ['id' => 'dietitian_name', 'type' => 'text', 'label' => 'Dietitian / Nutritionist', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'assessment_date', 'type' => 'date', 'label' => 'Date of Assessment', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }

    private function getNcpMonitoringSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Visit Information',
                    'icon' => 'heroicon-o-calendar',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'visit_number', 'type' => 'select', 'label' => 'Follow-up Visit Number', 'options' => ['1st follow-up', '2nd follow-up', '3rd follow-up', '4th follow-up', '5+ follow-up'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'weeks_since_initial', 'type' => 'text', 'label' => 'Weeks Since Initial Assessment', 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Anthropometric & Biochemical Update',
                    'icon' => 'heroicon-o-calculator',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'current_weight', 'type' => 'text', 'label' => 'Current Weight (kg)', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'weight_change_since_last', 'type' => 'text', 'label' => 'Weight Change Since Last Visit (kg)', 'columnSpan' => 1],
                        ['id' => 'current_bmi', 'type' => 'text', 'label' => 'Current BMI', 'columnSpan' => 1],
                        ['id' => 'current_muac', 'type' => 'text', 'label' => 'Current MUAC (cm)', 'columnSpan' => 1],
                        ['id' => 'labs_update', 'type' => 'textarea', 'label' => 'Updated Lab Values (if available)', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Goal Progress Review',
                    'icon' => 'heroicon-o-chart-bar',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'goal_adherence', 'type' => 'radio', 'label' => 'Dietary Advice Adherence', 'options' => ['Excellent (>80%)', 'Good (60-79%)', 'Moderate (40-59%)', 'Poor (<40%)'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'barriers_adherence', 'type' => 'textarea', 'label' => 'Barriers to Adherence', 'columnSpan' => 2],
                        ['id' => 'goal_1_progress', 'type' => 'select', 'label' => 'Goal 1 Progress', 'options' => ['Achieved', 'On track', 'Partial progress', 'No progress', 'Deteriorated'], 'columnSpan' => 1],
                        ['id' => 'goal_2_progress', 'type' => 'select', 'label' => 'Goal 2 Progress', 'options' => ['Achieved', 'On track', 'Partial progress', 'No progress', 'Deteriorated', 'N/A'], 'columnSpan' => 1],
                        ['id' => 'clinical_improvement', 'type' => 'textarea', 'label' => 'Clinical Improvement / Changes Noted', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Plan Update',
                    'icon' => 'heroicon-o-pencil-square',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'plan_change', 'type' => 'radio', 'label' => 'Nutrition Care Plan', 'options' => ['Continue as planned', 'Modify goals', 'Change intervention', 'Refer / Escalate', 'Discharge planned'], 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'updated_goals', 'type' => 'textarea', 'label' => 'Updated / New Goals', 'columnSpan' => 2],
                        ['id' => 'updated_advice', 'type' => 'textarea', 'label' => 'Updated Dietary Advice', 'columnSpan' => 2],
                        ['id' => 'next_review', 'type' => 'select', 'label' => 'Next Review', 'options' => ['In 2 weeks', 'In 1 month', 'In 3 months', 'At discharge', 'As needed'], 'columnSpan' => 1],
                        ['id' => 'dietitian_name', 'type' => 'text', 'label' => 'Dietitian / Nutritionist', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'review_date', 'type' => 'date', 'label' => 'Date of Review', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }

    private function getDischargeReferralSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Discharge Summary',
                    'icon' => 'heroicon-o-document-check',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'reason_for_discharge', 'type' => 'radio', 'label' => 'Reason for Discharge', 'options' => ['Goals achieved', 'Transferred to another facility', 'Client request', 'Non-attendance (3+ missed appointments)', 'Deceased', 'Other'], 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'initial_assessment_date', 'type' => 'date', 'label' => 'Initial Assessment Date', 'columnSpan' => 1],
                        ['id' => 'discharge_date', 'type' => 'date', 'label' => 'Discharge Date', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'total_sessions', 'type' => 'text', 'label' => 'Total Sessions Attended', 'columnSpan' => 1],
                        ['id' => 'duration_months', 'type' => 'text', 'label' => 'Duration of Care (months)', 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Nutritional Status at Discharge',
                    'icon' => 'heroicon-o-scale',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'discharge_weight', 'type' => 'text', 'label' => 'Weight at Discharge (kg)', 'columnSpan' => 1],
                        ['id' => 'discharge_bmi', 'type' => 'text', 'label' => 'BMI at Discharge', 'columnSpan' => 1],
                        ['id' => 'weight_change_total', 'type' => 'text', 'label' => 'Total Weight Change During Care (kg)', 'columnSpan' => 1],
                        ['id' => 'nutritional_status_outcome', 'type' => 'select', 'label' => 'Nutritional Status at Discharge', 'options' => ['Improved', 'Maintained / Stable', 'No significant change', 'Deteriorated'], 'columnSpan' => 1],
                        ['id' => 'goals_achieved', 'type' => 'textarea', 'label' => 'Goals Achieved', 'columnSpan' => 2],
                        ['id' => 'ongoing_concerns', 'type' => 'textarea', 'label' => 'Ongoing Nutritional Concerns', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Referral & Handover',
                    'icon' => 'heroicon-o-arrow-right-on-rectangle',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'referral_required', 'type' => 'radio', 'label' => 'Referral at Discharge', 'options' => ['Yes — external service', 'Yes — internal KISE service', 'No referral needed'], 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'referral_destination', 'type' => 'text', 'label' => 'Referral Destination (if applicable)', 'columnSpan' => 1],
                        ['id' => 'referral_reason', 'type' => 'text', 'label' => 'Reason for Referral', 'columnSpan' => 1],
                        ['id' => 'discharge_plan', 'type' => 'textarea', 'label' => 'Discharge Nutrition Plan (for client / family)', 'columnSpan' => 2],
                        ['id' => 'education_at_discharge', 'type' => 'textarea', 'label' => 'Education Provided at Discharge', 'columnSpan' => 2],
                        ['id' => 'dietitian_name', 'type' => 'text', 'label' => 'Dietitian / Nutritionist', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'discharge_date_sign', 'type' => 'date', 'label' => 'Date', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }
}
