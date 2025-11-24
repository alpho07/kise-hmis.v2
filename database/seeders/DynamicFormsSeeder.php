<?php

namespace Database\Seeders;

use App\Models\AssessmentFormSchema;
use Illuminate\Database\Seeder;

class DynamicFormsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Intake Assessment Form
        $this->createIntakeAssessmentForm();
        
        // Create Functional Screening Form
        $this->createFunctionalScreeningForm();
        
        // Create Sample Survey
        $this->createSatisfactionSurveyForm();
    }

    /**
     * Create Comprehensive Intake Assessment Form
     */
    protected function createIntakeAssessmentForm(): void
    {
        $schema = [
            'meta' => [
                'version' => '1.0.0',
                'title' => 'Comprehensive Intake Assessment',
                'description' => 'KISE client intake form - captures all demographic, medical, and service needs',
                'estimatedMinutes' => 30,
                'totalSections' => 12,
                'allowDraft' => true,
                'allowPartialSubmission' => true,
            ],
            
            'sections' => [
                // ============================================
                // SECTION A: HEADER & CONTEXT
                // ============================================
                [
                    'id' => 'header',
                    'title' => 'Client & Visit Information',
                    'description' => 'Auto-loaded from system',
                    'icon' => 'heroicon-o-information-circle',
                    'order' => 1,
                    'collapsible' => false,
                    'columns' => 2,
                    'fields' => [
                        [
                            'id' => 'client_uci',
                            'type' => 'placeholder',
                            'label' => 'UCI',
                            'content' => 'Auto-loaded',
                            'columnSpan' => 1,
                        ],
                        [
                            'id' => 'client_name',
                            'type' => 'placeholder',
                            'label' => 'Client Name',
                            'content' => 'Auto-loaded',
                            'columnSpan' => 1,
                        ],
                        [
                            'id' => 'visit_number',
                            'type' => 'placeholder',
                            'label' => 'Visit Number',
                            'content' => 'Auto-loaded',
                            'columnSpan' => 1,
                        ],
                        [
                            'id' => 'age_gender',
                            'type' => 'placeholder',
                            'label' => 'Age / Gender',
                            'content' => 'Auto-loaded',
                            'columnSpan' => 1,
                        ],
                    ],
                ],

                // ============================================
                // SECTION B: CONTACT INFORMATION
                // ============================================
                [
                    'id' => 'contact_info',
                    'title' => 'Contact Information',
                    'description' => 'Client contact details and communication preferences',
                    'icon' => 'heroicon-o-phone',
                    'order' => 2,
                    'collapsible' => true,
                    'columns' => 2,
                    'fields' => [
                        [
                            'id' => 'phone_primary',
                            'type' => 'text',
                            'inputType' => 'tel',
                            'label' => 'Primary Phone',
                            'placeholder' => '0712345678 or +254712345678',
                            'prefixIcon' => 'heroicon-o-phone',
                            'helperText' => 'Format: +254... or 07...',
                            'validation' => [
                                'required' => false,
                                'rules' => ['regex:/^(\+254|0)[17]\d{8}$/'],
                            ],
                        ],
                        [
                            'id' => 'phone_secondary',
                            'type' => 'text',
                            'inputType' => 'tel',
                            'label' => 'Secondary Phone',
                            'placeholder' => 'Optional',
                            'prefixIcon' => 'heroicon-o-phone',
                        ],
                        [
                            'id' => 'preferred_communication',
                            'type' => 'select',
                            'label' => 'Preferred Communication',
                            'options' => [
                                ['value' => 'sms', 'label' => 'SMS'],
                                ['value' => 'phone', 'label' => 'Phone Call'],
                                ['value' => 'email', 'label' => 'Email'],
                                ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                            ],
                            'default' => 'sms',
                            'prefixIcon' => 'heroicon-o-chat-bubble-left-right',
                            'validation' => [
                                'required' => true,
                            ],
                        ],
                        [
                            'id' => 'email',
                            'type' => 'text',
                            'inputType' => 'email',
                            'label' => 'Email Address',
                            'placeholder' => 'client@example.com',
                            'prefixIcon' => 'heroicon-o-envelope',
                            'conditionalDisplay' => [
                                'field' => 'preferred_communication',
                                'operator' => 'equals',
                                'value' => 'email',
                            ],
                            'validation' => [
                                'rules' => ['email'],
                            ],
                        ],
                        [
                            'id' => 'consent_to_sms',
                            'type' => 'toggle',
                            'label' => 'Consent to Receive SMS',
                            'helperText' => 'Client consents to receive appointment reminders via SMS',
                            'default' => true,
                            'columnSpan' => 'full',
                        ],
                        [
                            'id' => 'county_id',
                            'type' => 'select',
                            'label' => 'County',
                            'dataSource' => [
                                'model' => 'County',
                                'valueField' => 'id',
                                'labelField' => 'name',
                            ],
                            'searchable' => true,
                            'prefixIcon' => 'heroicon-o-map-pin',
                            'live' => true,
                            'onChangeActions' => [
                                ['action' => 'reset', 'target' => 'sub_county_id'],
                                ['action' => 'reset', 'target' => 'ward_id'],
                            ],
                        ],
                        [
                            'id' => 'sub_county_id',
                            'type' => 'select',
                            'label' => 'Sub-County',
                            'dataSource' => [
                                'model' => 'SubCounty',
                                'valueField' => 'id',
                                'labelField' => 'name',
                                'filterBy' => [
                                    'field' => 'county_id',
                                    'sourceField' => 'county_id',
                                ],
                            ],
                            'searchable' => true,
                            'live' => true,
                            'disabled' => 'if county_id == null',
                            'onChangeActions' => [
                                ['action' => 'reset', 'target' => 'ward_id'],
                            ],
                        ],
                        [
                            'id' => 'ward_id',
                            'type' => 'select',
                            'label' => 'Ward',
                            'dataSource' => [
                                'model' => 'Ward',
                                'valueField' => 'id',
                                'labelField' => 'name',
                                'filterBy' => [
                                    'field' => 'sub_county_id',
                                    'sourceField' => 'sub_county_id',
                                ],
                            ],
                            'searchable' => true,
                            'disabled' => 'if sub_county_id == null',
                        ],
                        [
                            'id' => 'village_estate',
                            'type' => 'text',
                            'label' => 'Village / Estate',
                            'placeholder' => 'Optional',
                        ],
                        [
                            'id' => 'nearest_landmark',
                            'type' => 'text',
                            'label' => 'Nearest Landmark',
                            'placeholder' => 'Optional',
                            'helperText' => 'Helps with home visits',
                        ],
                        [
                            'id' => 'physical_address',
                            'type' => 'textarea',
                            'label' => 'Physical Address',
                            'rows' => 2,
                            'placeholder' => 'Optional',
                            'columnSpan' => 'full',
                        ],
                    ],
                ],

                // ============================================
                // SECTION C: DISABILITY & REGISTRATION
                // ============================================
                [
                    'id' => 'disability_registration',
                    'title' => 'Disability & Registration',
                    'description' => 'NCPWD registration and disability information',
                    'icon' => 'heroicon-o-identification',
                    'order' => 3,
                    'collapsible' => true,
                    'columns' => 2,
                    'fields' => [
                        [
                            'id' => 'has_ncpwd',
                            'type' => 'toggle',
                            'label' => 'Has NCPWD Card?',
                            'live' => true,
                            'inline' => false,
                        ],
                        [
                            'id' => 'ncpwd_number',
                            'type' => 'text',
                            'label' => 'NCPWD Number',
                            'placeholder' => 'e.g., NCPWD/2023/12345',
                            'helperText' => 'Format: NCPWD/YYYY/NNNNN',
                            'conditionalDisplay' => [
                                'field' => 'has_ncpwd',
                                'operator' => 'equals',
                                'value' => true,
                            ],
                            'validation' => [
                                'rules' => ['regex:/^NCPWD\/\d{4}\/\d{5}$/'],
                            ],
                        ],
                        [
                            'id' => 'disability_categories',
                            'type' => 'checkbox_list',
                            'label' => 'Disability Categories (Select all that apply)',
                            'options' => [
                                ['value' => 'physical', 'label' => 'Physical'],
                                ['value' => 'visual', 'label' => 'Visual'],
                                ['value' => 'hearing', 'label' => 'Hearing'],
                                ['value' => 'intellectual', 'label' => 'Intellectual'],
                                ['value' => 'speech', 'label' => 'Speech & Language'],
                                ['value' => 'psychosocial', 'label' => 'Psychosocial'],
                                ['value' => 'multiple', 'label' => 'Multiple Disabilities'],
                                ['value' => 'albinism', 'label' => 'Albinism'],
                                ['value' => 'deafblind', 'label' => 'Deafblindness'],
                                ['value' => 'other', 'label' => 'Other (specify)'],
                            ],
                            'columns' => 3,
                            'columnSpan' => 'full',
                            'live' => true,
                            'validation' => [
                                'required' => true,
                            ],
                        ],
                        [
                            'id' => 'disability_other',
                            'type' => 'text',
                            'label' => 'Other Disability',
                            'placeholder' => 'Specify',
                            'conditionalDisplay' => [
                                'field' => 'disability_categories',
                                'operator' => 'contains',
                                'value' => 'other',
                            ],
                            'columnSpan' => 'full',
                        ],
                        [
                            'id' => 'primary_disability',
                            'type' => 'select',
                            'label' => 'Primary Disability',
                            'options' => [
                                ['value' => 'physical', 'label' => 'Physical'],
                                ['value' => 'visual', 'label' => 'Visual'],
                                ['value' => 'hearing', 'label' => 'Hearing'],
                                ['value' => 'intellectual', 'label' => 'Intellectual'],
                                ['value' => 'speech', 'label' => 'Speech & Language'],
                                ['value' => 'psychosocial', 'label' => 'Psychosocial'],
                                ['value' => 'multiple', 'label' => 'Multiple Disabilities'],
                            ],
                            'helperText' => 'Main disability for service routing',
                            'validation' => [
                                'required' => true,
                            ],
                        ],
                        [
                            'id' => 'disability_severity',
                            'type' => 'select',
                            'label' => 'Severity (Caregiver View)',
                            'options' => [
                                ['value' => 'mild', 'label' => 'Mild'],
                                ['value' => 'moderate', 'label' => 'Moderate'],
                                ['value' => 'severe', 'label' => 'Severe'],
                                ['value' => 'profound', 'label' => 'Profound'],
                            ],
                            'helperText' => 'Based on caregiver observation',
                        ],
                        [
                            'id' => 'disability_evidence',
                            'type' => 'file',
                            'label' => 'Evidence Documents',
                            'helperText' => 'Upload NCPWD card, medical reports, etc.',
                            'multiple' => true,
                            'acceptedFileTypes' => ['application/pdf', 'image/*'],
                            'directory' => 'disability-evidence',
                            'columnSpan' => 'full',
                        ],
                    ],
                ],

                // TO BE CONTINUED IN PART 2...
            ],
        ];

        $additionalSections = json_decode(
            file_get_contents(__DIR__ . '/INTAKE_SECTIONS_D_TO_H.json'),
            true
        )['sections'];
        
        // Merge with existing sections
        $schema['sections'] = array_merge(
            $schema['sections'],
            $additionalSections
        );

        AssessmentFormSchema::create([
            'name' => 'Comprehensive Intake Assessment',
            'slug' => 'intake-assessment',
            'version' => '1.0.0',
            'category' => 'clinical_assessment',
            'description' => 'Complete intake assessment capturing demographic, medical, and service needs for KISE clients',
            'schema' => $schema,
            'estimated_minutes' => 30,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1, // Assumes admin user exists
        ]);
    }

    /**
     * TO BE CONTINUED...
     */
    protected function createFunctionalScreeningForm(): void
    {
        // Will add in next part
    }

    protected function createSatisfactionSurveyForm(): void
    {
        // Will add in next part
    }
}