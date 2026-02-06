<?php

namespace Database\Seeders;

use App\Models\AssessmentFormSchema;
use Illuminate\Database\Seeder;

class VisionCentreFormSchemasSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding Vision Centre Forms...');
        
        // 1. BASIC EYE ASSESSMENT
        AssessmentFormSchema::create([
            'name' => 'Basic Eye Assessment',
            'slug' => 'vision-basic-eye',
            'version' => 1,
            'category' => 'vision',
            'description' => 'Initial screening for visual acuity and presenting complaints',
            'estimated_minutes' => 15,
            'allow_draft' => true,
            'allow_partial_submission' => false,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getBasicEyeSchema(),
            'auto_referrals' => [
                [
                    'condition' => ['field' => 'va_right_distance', 'operator' => 'in', 'value' => ['CF', 'HM', 'PL', 'NPL']],
                    'action' => ['service_point' => 'ophthalmology', 'priority' => 'high', 'reason' => 'Severe vision impairment']
                ]
            ]
        ]);
        
        $this->command->info('  ✓ Basic Eye Assessment');
        
        // 2. FEVA
        AssessmentFormSchema::create([
            'name' => 'Functional Educational Vision Assessment (FEVA)',
            'slug' => 'vision-feva',
            'version' => 1,
            'category' => 'vision',
            'description' => 'Comprehensive functional vision assessment for educational settings',
            'estimated_minutes' => 45,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getFEVASchema(),
        ]);
        
        $this->command->info('  ✓ FEVA');
        
        // 3. CLINICAL
        AssessmentFormSchema::create([
            'name' => 'Clinical Vision Screening',
            'slug' => 'vision-clinical',
            'version' => 1,
            'category' => 'vision',
            'description' => 'Clinical assessment with slit lamp, refraction, and diagnostics',
            'estimated_minutes' => 45,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getClinicalSchema(),
        ]);
        
        $this->command->info('  ✓ Clinical Screening');
        
        // 4. OPTICAL
        AssessmentFormSchema::create([
            'name' => 'Optical Inventory & Dispensing',
            'slug' => 'vision-optical',
            'version' => 1,
            'category' => 'vision',
            'description' => 'Frame selection, lens prescription, and dispensing',
            'estimated_minutes' => 30,
            'allow_draft' => true,
            'allow_partial_submission' => false,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getOpticalSchema(),
        ]);
        
        $this->command->info('  ✓ Optical Inventory');
        $this->command->info('✅ Complete!');
    }
    
    protected function getBasicEyeSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Client Information',
                    'icon' => 'heroicon-o-user',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'client_name', 'type' => 'placeholder', 'label' => 'Client Name', 'columnSpan' => 1],
                        ['id' => 'client_age', 'type' => 'placeholder', 'label' => 'Age', 'columnSpan' => 1],
                        ['id' => 'client_uci', 'type' => 'placeholder', 'label' => 'UCI', 'columnSpan' => 1],
                        ['id' => 'contact', 'type' => 'text', 'label' => 'Contact', 'inputType' => 'tel', 'columnSpan' => 1, 'validation' => ['required' => false]],
                    ]
                ],
                [
                    'title' => 'Presenting Complaints',
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'presenting_complaints', 'type' => 'textarea', 'label' => 'Main Complaints', 'placeholder' => 'e.g., blurred vision, pain, redness', 'rows' => 4, 'validation' => ['required' => true]],
                    ]
                ],
                [
                    'title' => 'Visual Acuity',
                    'icon' => 'heroicon-o-eye',
                    'columns' => 2,
                    'fields' => [
                        [
                            'id' => 'va_right_distance',
                            'type' => 'select',
                            'label' => 'Right Eye - Distance VA',
                            'options' => [
                                ['value' => '6/6', 'label' => '6/6'],
                                ['value' => '6/9', 'label' => '6/9'],
                                ['value' => '6/12', 'label' => '6/12'],
                                ['value' => '6/18', 'label' => '6/18'],
                                ['value' => '6/24', 'label' => '6/24'],
                                ['value' => '6/60', 'label' => '6/60'],
                                ['value' => 'CF', 'label' => 'CF'],
                                ['value' => 'HM', 'label' => 'HM'],
                                ['value' => 'PL', 'label' => 'PL'],
                                ['value' => 'NPL', 'label' => 'NPL'],
                            ],
                            'searchable' => true,
                            'validation' => ['required' => true],
                            'columnSpan' => 1
                        ],
                        [
                            'id' => 'va_left_distance',
                            'type' => 'select',
                            'label' => 'Left Eye - Distance VA',
                            'options' => [
                                ['value' => '6/6', 'label' => '6/6'],
                                ['value' => '6/9', 'label' => '6/9'],
                                ['value' => '6/12', 'label' => '6/12'],
                                ['value' => '6/18', 'label' => '6/18'],
                                ['value' => '6/24', 'label' => '6/24'],
                                ['value' => '6/60', 'label' => '6/60'],
                                ['value' => 'CF', 'label' => 'CF'],
                                ['value' => 'HM', 'label' => 'HM'],
                                ['value' => 'PL', 'label' => 'PL'],
                                ['value' => 'NPL', 'label' => 'NPL'],
                            ],
                            'searchable' => true,
                            'validation' => ['required' => true],
                            'columnSpan' => 1
                        ],
                    ]
                ],
                [
                    'title' => 'Observations',
                    'icon' => 'heroicon-o-document-text',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'other_observations', 'type' => 'textarea', 'label' => 'Clinical Observations', 'rows' => 5, 'validation' => ['required' => false]],
                    ]
                ],
            ]
        ];
    }
    
    protected function getFEVASchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Part A: Client Information',
                    'icon' => 'heroicon-o-user-circle',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'student_name', 'type' => 'placeholder', 'label' => 'Student Name', 'columnSpan' => 1],
                        ['id' => 'client_age', 'type' => 'placeholder', 'label' => 'Age', 'columnSpan' => 1],
                        ['id' => 'school_class', 'type' => 'text', 'label' => 'School / Class', 'columnSpan' => 1, 'validation' => ['required' => false]],
                        ['id' => 'primary_visual_condition', 'type' => 'text', 'label' => 'Primary Visual Condition', 'columnSpan' => 1, 'validation' => ['required' => true]],
                    ]
                ],
                [
                    'title' => 'Part B: Medical Background',
                    'icon' => 'heroicon-o-clipboard-document-list',
                    'columns' => 2,
                    'collapsible' => true,
                    'fields' => [
                        ['id' => 'medical_history', 'type' => 'textarea', 'label' => 'Medical History', 'rows' => 3, 'columnSpan' => 2, 'validation' => ['required' => false]],
                        ['id' => 'contrast_sensitivity', 'type' => 'radio', 'label' => 'Contrast Sensitivity', 'options' => [
                            ['value' => 'normal', 'label' => 'Normal'],
                            ['value' => 'mild', 'label' => 'Mild Difficulty'],
                            ['value' => 'marked', 'label' => 'Marked Difficulty'],
                        ], 'columnSpan' => 1, 'validation' => ['required' => true]],
                    ]
                ],
                // Additional FEVA sections would go here (Parts C-G)
            ]
        ];
    }
    
    protected function getClinicalSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Patient Information',
                    'icon' => 'heroicon-o-identification',
                    'columns' => 3,
                    'fields' => [
                        ['id' => 'patient_name', 'type' => 'placeholder', 'label' => 'Name', 'columnSpan' => 1],
                        ['id' => 'client_age', 'type' => 'placeholder', 'label' => 'Age', 'columnSpan' => 1],
                        ['id' => 'client_uci', 'type' => 'placeholder', 'label' => 'UCI', 'columnSpan' => 1],
                    ]
                ],
                [
                    'title' => 'Chief Complaint',
                    'icon' => 'heroicon-o-chat-bubble-left',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'chief_complaint', 'type' => 'textarea', 'label' => 'Chief Complaint', 'rows' => 3, 'validation' => ['required' => true]],
                        ['id' => 'history', 'type' => 'textarea', 'label' => 'History', 'rows' => 4, 'validation' => ['required' => true]],
                    ]
                ],
                [
                    'title' => 'Refraction',
                    'icon' => 'heroicon-o-adjustments-horizontal',
                    'columns' => 2,
                    'collapsible' => true,
                    'fields' => [
                        ['id' => 'sph_re', 'type' => 'text', 'label' => 'SPH - RE', 'columnSpan' => 1, 'validation' => ['required' => false]],
                        ['id' => 'sph_le', 'type' => 'text', 'label' => 'SPH - LE', 'columnSpan' => 1, 'validation' => ['required' => false]],
                    ]
                ],
                [
                    'title' => 'Diagnosis',
                    'icon' => 'heroicon-o-clipboard-document-check',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'diagnosis', 'type' => 'textarea', 'label' => 'Diagnosis', 'rows' => 3, 'validation' => ['required' => true]],
                        ['id' => 'plan', 'type' => 'textarea', 'label' => 'Treatment Plan', 'rows' => 3, 'validation' => ['required' => false]],
                    ]
                ],
            ]
        ];
    }
    
    protected function getOpticalSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Client Information',
                    'icon' => 'heroicon-o-user',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'client_name', 'type' => 'placeholder', 'label' => 'Name', 'columnSpan' => 1],
                        ['id' => 'phone_no', 'type' => 'text', 'label' => 'Phone', 'inputType' => 'tel', 'columnSpan' => 1, 'validation' => ['required' => false]],
                    ]
                ],
                [
                    'title' => 'Frames Selection',
                    'icon' => 'heroicon-o-squares-plus',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'frames_selected', 'type' => 'radio', 'label' => 'Frames Selected?', 'options' => [
                            ['value' => 'yes', 'label' => 'Yes'],
                            ['value' => 'no', 'label' => 'No'],
                        ], 'columnSpan' => 2, 'validation' => ['required' => true]],
                        ['id' => 'frame_type', 'type' => 'select', 'label' => 'Frame Type', 'options' => [
                            ['value' => 'full', 'label' => 'Full Frame'],
                            ['value' => 'rimless', 'label' => 'Rimless'],
                        ], 'columnSpan' => 1, 'validation' => ['required' => false]],
                    ]
                ],
                [
                    'title' => 'Lenses',
                    'icon' => 'heroicon-o-circle-stack',
                    'columns' => 2,
                    'collapsible' => true,
                    'fields' => [
                        ['id' => 'lens_type', 'type' => 'select', 'label' => 'Lens Type', 'options' => [
                            ['value' => 'single', 'label' => 'Single Vision'],
                            ['value' => 'bifocal', 'label' => 'Bifocal'],
                            ['value' => 'progressive', 'label' => 'Progressive'],
                        ], 'columnSpan' => 2, 'validation' => ['required' => false]],
                    ]
                ],
                [
                    'title' => 'Dispensing',
                    'icon' => 'heroicon-o-calendar-days',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'pick_up_date', 'type' => 'date', 'label' => 'Pick-up Date', 'columnSpan' => 1, 'validation' => ['required' => false]],
                    ]
                ],
            ]
        ];
    }
}