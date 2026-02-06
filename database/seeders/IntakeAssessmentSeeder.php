<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IntakeAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        // Remove old schema
        DB::table('assessment_form_schemas')
            ->where('slug', 'intake-assessment')
            ->delete();

        // Insert new schema
        DB::table('assessment_form_schemas')->insert([
            'id' => 1,
            'name' => 'Comprehensive Intake Assessment',
            'slug' => 'intake-assessment',
            'version' => '1.0.0',
            'category' => 'clinical_assessment',
            'description' => 'Complete KISE intake capturing all 327+ fields across 12 sections',

            // HEREDOC start — EXACT JSON you provided (no modifications)
            'schema' => <<<JSON
{
    "meta": {
        "version": "1.0.0",
        "title": "Comprehensive Intake Assessment",
        "description": "KISE complete intake form",
        "estimatedMinutes": 30,
        "totalSections": 12
    },
    "sections": [
        {
            "id": "header",
            "title": "Client & Visit Information",
            "description": "Auto-loaded from Reception and Triage",
            "icon": "heroicon-o-information-circle",
            "order": 1,
            "collapsible": false,
            "columns": 3,
            "fields": [
                {
                    "id": "client_uci",
                    "type": "placeholder",
                    "label": "UCI",
                    "content": "Auto-loaded from client record"
                },
                {
                    "id": "client_name",
                    "type": "placeholder",
                    "label": "Client Name",
                    "content": "Auto-loaded from client record"
                },
                {
                    "id": "client_age",
                    "type": "placeholder",
                    "label": "Age",
                    "content": "Auto-loaded from DOB"
                },
                {
                    "id": "client_gender",
                    "type": "placeholder",
                    "label": "Sex",
                    "content": "Auto-loaded from client record"
                },
                {
                    "id": "visit_number",
                    "type": "placeholder",
                    "label": "Visit Number",
                    "content": "Auto-loaded from visit"
                },
                {
                    "id": "triage_status",
                    "type": "placeholder",
                    "label": "Triage Status",
                    "content": "Auto-loaded from triage"
                }
            ]
        },
        {
            "id": "contact_info",
            "title": "Contact Information",
            "description": "Client contact details and communication preferences",
            "icon": "heroicon-o-phone",
            "order": 2,
            "collapsible": true,
            "columns": 2,
            "fields": [
                {
                    "id": "national_id",
                    "type": "text",
                    "label": "National ID / Passport Number",
                    "placeholder": "e.g., 12345678",
                    "prefixIcon": "heroicon-o-identification",
                    "maxLength": 20
                },
                {
                    "id": "birth_certificate",
                    "type": "text",
                    "label": "Birth Certificate Number",
                    "placeholder": "Optional",
                    "prefixIcon": "heroicon-o-document-text",
                    "maxLength": 20
                },
                {
                    "id": "phone_primary",
                    "type": "text",
                    "inputType": "tel",
                    "label": "Primary Phone",
                    "placeholder": "0712345678 or +254712345678",
                    "prefixIcon": "heroicon-o-phone",
                    "helperText": "Kenya format: +254... or 07...",
                    "validation": {
                        "rules": ["regex:/^(\\\\+254|0)[17]\\\\d{8}$/"]
                    }
                },
                {
                    "id": "phone_alternate",
                    "type": "text",
                    "inputType": "tel",
                    "label": "Alternate Phone",
                    "placeholder": "Optional alternate number",
                    "prefixIcon": "heroicon-o-phone"
                },
                {
                    "id": "preferred_communication",
                    "type": "select",
                    "label": "Preferred Communication Channel",
                    "options": [
                        {"value": "sms", "label": "SMS"},
                        {"value": "phone", "label": "Phone Call"},
                        {"value": "email", "label": "Email"},
                        {"value": "whatsapp", "label": "WhatsApp"}
                    ],
                    "default": "sms",
                    "validation": {"required": true},
                    "prefixIcon": "heroicon-o-chat-bubble-left-right",
                    "live": true
                },
                {
                    "id": "email",
                    "type": "text",
                    "inputType": "email",
                    "label": "Email Address",
                    "placeholder": "client@example.com",
                    "prefixIcon": "heroicon-o-envelope",
                    "conditionalDisplay": {
                        "field": "preferred_communication",
                        "operator": "equals",
                        "value": "email"
                    },
                    "validation": {"rules": ["email"]}
                },
                {
                    "id": "consent_to_sms",
                    "type": "toggle",
                    "label": "Consent to Receive SMS",
                    "helperText": "Client consents to receive appointment reminders and updates via SMS",
                    "default": true,
                    "columnSpan": "full"
                },
                {
                    "id": "physical_address",
                    "type": "textarea",
                    "label": "Physical Address",
                    "rows": 2,
                    "placeholder": "Enter complete physical address",
                    "columnSpan": "full"
                },
                {
                    "id": "county_id",
                    "type": "select",
                    "label": "County",
                    "dataSource": {
                        "model": "County",
                        "valueField": "id",
                        "labelField": "name"
                    },
                    "searchable": true,
                    "prefixIcon": "heroicon-o-map-pin",
                    "live": true,
                    "onChangeActions": [
                        {"action": "reset", "target": "sub_county_id"},
                        {"action": "reset", "target": "ward_id"}
                    ]
                },
                {
                    "id": "sub_county_id",
                    "type": "select",
                    "label": "Sub-County",
                    "dataSource": {
                        "model": "SubCounty",
                        "valueField": "id",
                        "labelField": "name",
                        "filterBy": {
                            "field": "county_id",
                            "sourceField": "county_id"
                        }
                    },
                    "searchable": true,
                    "live": true,
                    "disabled": "if county_id == null",
                    "onChangeActions": [
                        {"action": "reset", "target": "ward_id"}
                    ]
                },
                {
                    "id": "ward_id",
                    "type": "select",
                    "label": "Ward",
                    "dataSource": {
                        "model": "Ward",
                        "valueField": "id",
                        "labelField": "name",
                        "filterBy": {
                            "field": "sub_county_id",
                            "sourceField": "sub_county_id"
                        }
                    },
                    "searchable": true,
                    "disabled": "if sub_county_id == null"
                },
                {
                    "id": "village_estate",
                    "type": "text",
                    "label": "Village / Estate",
                    "placeholder": "e.g., Kimbo Village"
                },
                {
                    "id": "nearest_landmark",
                    "type": "text",
                    "label": "Nearest Landmark",
                    "placeholder": "e.g., Near Tuskys Supermarket",
                    "helperText": "For home visits and follow-ups",
                    "prefixIcon": "heroicon-o-map"
                },
                {
                    "id": "directions",
                    "type": "textarea",
                    "label": "Directions to Home",
                    "rows": 2,
                    "placeholder": "Detailed directions from nearest landmark",
                    "helperText": "Important for outreach visits",
                    "columnSpan": "full"
                }
            ]
        },
        {
            "id": "disability_registration",
            "title": "Disability & Registration Information",
            "description": "Disability details and NCPWD registration",
            "icon": "heroicon-o-identification",
            "order": 3,
            "collapsible": true,
            "columns": 2,
            "fields": [
                {
                    "id": "has_ncpwd",
                    "type": "toggle",
                    "label": "Has NCPWD Card?",
                    "default": false,
                    "live": true,
                    "columnSpan": "full"
                },
                {
                    "id": "ncpwd_number",
                    "type": "text",
                    "label": "NCPWD Registration Number",
                    "placeholder": "NCPWD/YYYY/XXXXX",
                    "helperText": "Format: NCPWD/YYYY/XXXXX",
                    "prefixIcon": "heroicon-o-identification",
                    "conditionalDisplay": {
                        "field": "has_ncpwd",
                        "operator": "equals",
                        "value": true
                    },
                    "validation": {
                        "rules": ["regex:/^NCPWD\\/\\d{4}\\/\\d{5}$/"]
                    }
                },
                {
                    "id": "ncpwd_card_status",
                    "type": "select",
                    "label": "NCPWD Card Status",
                    "options": [
                        {"value": "card_seen", "label": "Card Seen & Verified"},
                        {"value": "copy_uploaded", "label": "Copy Uploaded"},
                        {"value": "pending_verification", "label": "Pending Verification"}
                    ],
                    "conditionalDisplay": {
                        "field": "has_ncpwd",
                        "operator": "equals",
                        "value": true
                    }
                },
                {
                    "id": "disability_categories",
                    "type": "checkbox_list",
                    "label": "Disability Category (Select all that apply)",
                    "helperText": "Select primary and any additional disabilities",
                    "options": [
                        {"value": "physical", "label": "Physical Disability"},
                        {"value": "visual", "label": "Visual Impairment"},
                        {"value": "hearing", "label": "Hearing Impairment"},
                        {"value": "intellectual", "label": "Intellectual Disability"},
                        {"value": "speech", "label": "Speech & Language Disorder"},
                        {"value": "psychosocial", "label": "Psychosocial Disability"},
                        {"value": "multiple", "label": "Multiple Disabilities"},
                        {"value": "albinism", "label": "Albinism"},
                        {"value": "deafblind", "label": "Deafblindness"},
                        {"value": "other", "label": "Other (specify below)"}
                    ],
                    "columns": 3,
                    "live": true,
                    "validation": {"required": true},
                    "columnSpan": "full"
                },
                {
                    "id": "disability_other",
                    "type": "text",
                    "label": "Other Disability (Please Specify)",
                    "placeholder": "Describe the disability",
                    "conditionalDisplay": {
                        "field": "disability_categories",
                        "operator": "contains",
                        "value": "other"
                    },
                    "columnSpan": "full"
                },
                {
                    "id": "primary_disability",
                    "type": "select",
                    "label": "Primary Disability",
                    "helperText": "Main disability affecting client - determines service routing",
                    "options": [
                        {"value": "physical", "label": "Physical Disability"},
                        {"value": "visual", "label": "Visual Impairment"},
                        {"value": "hearing", "label": "Hearing Impairment"},
                        {"value": "intellectual", "label": "Intellectual Disability"},
                        {"value": "speech", "label": "Speech & Language Disorder"},
                        {"value": "psychosocial", "label": "Psychosocial Disability"},
                        {"value": "multiple", "label": "Multiple Disabilities"},
                        {"value": "albinism", "label": "Albinism"},
                        {"value": "deafblind", "label": "Deafblindness"}
                    ],
                    "validation": {"required": true},
                    "prefixIcon": "heroicon-o-star"
                },
                {
                    "id": "disability_severity",
                    "type": "select",
                    "label": "Disability Severity",
                    "helperText": "Initial assessment - will be confirmed by service provider",
                    "options": [
                        {"value": "mild", "label": "Mild"},
                        {"value": "moderate", "label": "Moderate"},
                        {"value": "severe", "label": "Severe"},
                        {"value": "profound", "label": "Profound"}
                    ]
                },
                {
                    "id": "disability_onset",
                    "type": "radio",
                    "label": "Onset of Disability",
                    "options": [
                        {"value": "congenital", "label": "Congenital (from birth)"},
                        {"value": "acquired", "label": "Acquired (after birth)"},
                        {"value": "unknown", "label": "Unknown"}
                    ],
                    "inline": true,
                    "columnSpan": "full"
                },
                {
                    "id": "disability_age_onset",
                    "type": "text",
                    "inputType": "number",
                    "label": "Age at Onset (if acquired)",
                    "placeholder": "Enter age in years",
                    "conditionalDisplay": {
                        "field": "disability_onset",
                        "operator": "equals",
                        "value": "acquired"
                    }
                },
                {
                    "id": "disability_cause",
                    "type": "textarea",
                    "label": "Known Cause of Disability",
                    "rows": 2,
                    "placeholder": "e.g., Birth complications, accident, illness",
                    "conditionalDisplay": {
                        "field": "disability_onset",
                        "operator": "equals",
                        "value": "acquired"
                    }
                },
                {
                    "id": "disability_evidence",
                    "type": "file",
                    "label": "Evidence Documents",
                    "helperText": "Upload ID, Birth Certificate, NCPWD Card, or medical reports",
                    "multiple": true,
                    "directory": "disability-evidence",
                    "acceptedFileTypes": ["image/*", "application/pdf"],
                    "columnSpan": "full"
                }
            ]
        },
        {
            "id": "socio_demographic",
            "title": "Socio-Demographic Information",
            "description": "Social and demographic details",
            "icon": "heroicon-o-users",
            "order": 4,
            "collapsible": true,
            "columns": 2,
            "fields": [
                {
                    "id": "marital_status",
                    "type": "select",
                    "label": "Marital Status",
                    "options": [
                        {"value": "single", "label": "Single"},
                        {"value": "married", "label": "Married"},
                        {"value": "divorced", "label": "Divorced"},
                        {"value": "separated", "label": "Separated"},
                        {"value": "widowed", "label": "Widowed"}
                    ],
                    "conditionalDisplay": {
                        "ageCondition": {"minAge": 18}
                    }
                },
                {
                    "id": "living_arrangement",
                    "type": "select",
                    "label": "Living Arrangement",
                    "options": [
                        {"value": "parents", "label": "With Parents"},
                        {"value": "spouse", "label": "With Spouse"},
                        {"value": "alone", "label": "Alone"},
                        {"value": "relatives", "label": "With Relatives"},
                        {"value": "institution", "label": "Institution"},
                        {"value": "other", "label": "Other"}
                    ],
                    "validation": {"required": true}
                },
                {
                    "id": "primary_caregiver",
                    "type": "text",
                    "label": "Primary Caregiver Name",
                    "placeholder": "Name of main caregiver",
                    "conditionalDisplay": {
                        "ageCondition": {"maxAge": 18}
                    }
                },
                {
                    "id": "caregiver_relationship",
                    "type": "select",
                    "label": "Caregiver Relationship",
                    "options": [
                        {"value": "mother", "label": "Mother"},
                        {"value": "father", "label": "Father"},
                        {"value": "guardian", "label": "Guardian"},
                        {"value": "sibling", "label": "Sibling"},
                        {"value": "other_relative", "label": "Other Relative"},
                        {"value": "other", "label": "Other"}
                    ],
                    "conditionalDisplay": {
                        "ageCondition": {"maxAge": 18}
                    }
                },
                {
                    "id": "caregiver_phone",
                    "type": "text",
                    "inputType": "tel",
                    "label": "Caregiver Phone",
                    "placeholder": "0712345678",
                    "prefixIcon": "heroicon-o-phone",
                    "conditionalDisplay": {
                        "ageCondition": {"maxAge": 18}
                    }
                },
                {
                    "id": "household_size",
                    "type": "text",
                    "inputType": "number",
                    "label": "Household Size",
                    "placeholder": "Total number of people",
                    "helperText": "Including client"
                },
                {
                    "id": "primary_language",
                    "type": "select",
                    "label": "Primary Language",
                    "options": [
                        {"value": "english", "label": "English"},
                        {"value": "kiswahili", "label": "Kiswahili"},
                        {"value": "kikuyu", "label": "Kikuyu"},
                        {"value": "luo", "label": "Luo"},
                        {"value": "luhya", "label": "Luhya"},
                        {"value": "kamba", "label": "Kamba"},
                        {"value": "kalenjin", "label": "Kalenjin"},
                        {"value": "ksl", "label": "Kenya Sign Language"},
                        {"value": "other", "label": "Other"}
                    ],
                    "validation": {"required": true},
                    "searchable": true
                },
                {
                    "id": "communication_method",
                    "type": "select",
                    "label": "Primary Communication Method",
                    "helperText": "How client communicates best",
                    "options": [
                        {"value": "verbal", "label": "Verbal Speech"},
                        {"value": "sign_language", "label": "Sign Language"},
                        {"value": "gestures", "label": "Gestures"},
                        {"value": "picture_cards", "label": "Picture Cards"},
                        {"value": "aac_device", "label": "AAC Device"},
                        {"value": "writing", "label": "Writing"},
                        {"value": "other", "label": "Other"}
                    ]
                },
                {
                    "id": "interpreter_needed",
                    "type": "toggle",
                    "label": "Interpreter Needed?",
                    "helperText": "Will client need interpreter for services?",
                    "default": false
                },
                {
                    "id": "economic_status",
                    "type": "select",
                    "label": "Economic Status (Self-Reported)",
                    "helperText": "Client/family perception",
                    "options": [
                        {"value": "above_average", "label": "Above Average"},
                        {"value": "average", "label": "Average"},
                        {"value": "below_average", "label": "Below Average"},
                        {"value": "low_income", "label": "Low Income"}
                    ]
                },
                {
                    "id": "main_income_source",
                    "type": "select",
                    "label": "Main Source of Income",
                    "options": [
                        {"value": "employment", "label": "Employment"},
                        {"value": "business", "label": "Business"},
                        {"value": "farming", "label": "Farming"},
                        {"value": "support_family", "label": "Family Support"},
                        {"value": "support_government", "label": "Government Support"},
                        {"value": "pension", "label": "Pension"},
                        {"value": "none", "label": "None"},
                        {"value": "other", "label": "Other"}
                    ]
                },
                {
                    "id": "health_insurance",
                    "type": "checkbox_list",
                    "label": "Health Insurance Coverage",
                    "options": [
                        {"value": "sha", "label": "SHA (Social Health Authority)"},
                        {"value": "ncpwd", "label": "NCPWD"},
                        {"value": "private", "label": "Private Insurance"},
                        {"value": "employer", "label": "Employer-Based"},
                        {"value": "none", "label": "None"}
                    ],
                    "columns": 2,
                    "columnSpan": "full"
                },
                {
                    "id": "religion",
                    "type": "select",
                    "label": "Religion",
                    "options": [
                        {"value": "christian", "label": "Christian"},
                        {"value": "muslim", "label": "Muslim"},
                        {"value": "hindu", "label": "Hindu"},
                        {"value": "other", "label": "Other"},
                        {"value": "none", "label": "None"}
                    ]
                },
                {
                    "id": "next_of_kin_name",
                    "type": "text",
                    "label": "Next of Kin Name",
                    "placeholder": "Full name"
                },
                {
                    "id": "next_of_kin_relationship",
                    "type": "text",
                    "label": "Next of Kin Relationship",
                    "placeholder": "e.g., Mother, Brother"
                },
                {
                    "id": "next_of_kin_phone",
                    "type": "text",
                    "inputType": "tel",
                    "label": "Next of Kin Phone",
                    "placeholder": "0712345678",
                    "prefixIcon": "heroicon-o-phone"
                }
            ]
        },
        {
            "id": "medical_history",
            "title": "Medical & Developmental History",
            "description": "Medical conditions, medications, and developmental milestones",
            "icon": "heroicon-o-heart",
            "order": 5,
            "collapsible": true,
            "columns": 2,
            "fields": [
                {
                    "id": "chronic_conditions",
                    "type": "checkbox_list",
                    "label": "Chronic Conditions (Select all that apply)",
                    "options": [
                        {"value": "epilepsy", "label": "Epilepsy / Seizures"},
                        {"value": "cerebral_palsy", "label": "Cerebral Palsy"},
                        {"value": "autism", "label": "Autism Spectrum Disorder"},
                        {"value": "down_syndrome", "label": "Down Syndrome"},
                        {"value": "adhd", "label": "ADHD"},
                        {"value": "diabetes", "label": "Diabetes"},
                        {"value": "asthma", "label": "Asthma"},
                        {"value": "heart_condition", "label": "Heart Condition"},
                        {"value": "mental_health", "label": "Mental Health Condition"},
                        {"value": "none", "label": "None"},
                        {"value": "other", "label": "Other"}
                    ],
                    "columns": 3,
                    "live": true,
                    "columnSpan": "full"
                },
                {
                    "id": "chronic_conditions_other",
                    "type": "text",
                    "label": "Other Chronic Conditions",
                    "placeholder": "Specify other conditions",
                    "conditionalDisplay": {
                        "field": "chronic_conditions",
                        "operator": "contains",
                        "value": "other"
                    },
                    "columnSpan": "full"
                },
                {
                    "id": "current_medications",
                    "type": "textarea",
                    "label": "Current Medications",
                    "rows": 3,
                    "placeholder": "List all medications with dosage and frequency",
                    "helperText": "Include traditional/herbal remedies",
                    "columnSpan": "full"
                },
                {
                    "id": "past_surgeries",
                    "type": "textarea",
                    "label": "Past Surgeries / Hospitalizations",
                    "rows": 2,
                    "placeholder": "Description and approximate dates",
                    "columnSpan": "full"
                },
                {
                    "id": "developmental_concerns",
                    "type": "checkbox_list",
                    "label": "Developmental Concerns",
                    "helperText": "Quick screening - detailed assessment follows",
                    "options": [
                        {"value": "motor_delay", "label": "Motor Delay (sitting, walking)"},
                        {"value": "speech_delay", "label": "Speech/Language Delay"},
                        {"value": "cognitive_delay", "label": "Cognitive Delay"},
                        {"value": "social_delay", "label": "Social Interaction Delay"},
                        {"value": "behavioral", "label": "Behavioral Concerns"},
                        {"value": "none", "label": "None"}
                    ],
                    "columns": 3,
                    "columnSpan": "full"
                },
                {
                    "id": "family_history_disability",
                    "type": "toggle",
                    "label": "Family History of Disability?",
                    "helperText": "Any disability in siblings, parents, or close relatives",
                    "columnSpan": "full"
                },
                {
                    "id": "family_history_details",
                    "type": "textarea",
                    "label": "Family History Details",
                    "rows": 2,
                    "placeholder": "Describe family history",
                    "conditionalDisplay": {
                        "field": "family_history_disability",
                        "operator": "equals",
                        "value": true
                    },
                    "columnSpan": "full"
                },
                {
                    "id": "immunization_status",
                    "type": "select",
                    "label": "Immunization Status",
                    "options": [
                        {"value": "up_to_date", "label": "Up to Date"},
                        {"value": "partially", "label": "Partially Immunized"},
                        {"value": "not_immunized", "label": "Not Immunized"},
                        {"value": "unknown", "label": "Unknown"}
                    ],
                    "helperText": "Based on EPI schedule"
                },
                {
                    "id": "recent_illness",
                    "type": "textarea",
                    "label": "Recent Illness / Injuries",
                    "rows": 2,
                    "placeholder": "Any recent health issues (last 3 months)",
                    "columnSpan": "full"
                }
            ]
        },
        {
            "id": "allergies",
            "title": "Allergies & Sensitivities",
            "description": "All known allergies - Kenya-specific options",
            "icon": "heroicon-o-exclamation-triangle",
            "order": 6,
            "collapsible": true,
            "columns": 2,
            "fields": [
                {
                    "id": "allergy_categories",
                    "type": "checkbox_list",
                    "label": "Allergy Categories (Select all that apply)",
                    "options": [
                        {"value": "drug", "label": "Drug / Medication"},
                        {"value": "food", "label": "Food"},
                        {"value": "environmental", "label": "Environmental"},
                        {"value": "insect", "label": "Insect Bites/Stings"},
                        {"value": "latex", "label": "Latex"},
                        {"value": "herbal", "label": "Herbal/Traditional Medicine"},
                        {"value": "chemical", "label": "Chemical"},
                        {"value": "none_known", "label": "No Known Allergies"},
                        {"value": "other", "label": "Other"}
                    ],
                    "columns": 3,
                    "live": true,
                    "columnSpan": "full"
                },
                {
                    "id": "drug_allergies",
                    "type": "checkbox_list",
                    "label": "Drug Allergies (Select all)",
                    "options": [
                        {"value": "penicillin", "label": "Penicillin"},
                        {"value": "amoxicillin", "label": "Amoxicillin"},
                        {"value": "cotrimoxazole", "label": "Cotrimoxazole (Septrin)"},
                        {"value": "artemether", "label": "Artemether-Lumefantrine (AL/Coartem)"},
                        {"value": "ibuprofen", "label": "Ibuprofen"},
                        {"value": "aspirin", "label": "Aspirin"},
                        {"value": "other", "label": "Other"}
                    ],
                    "columns": 3,
                    "conditionalDisplay": {
                        "field": "allergy_categories",
                        "operator": "contains",
                        "value": "drug"
                    },
                    "columnSpan": "full"
                },
                {
                    "id": "drug_allergies_other",
                    "type": "text",
                    "label": "Other Drug Allergies",
                    "placeholder": "Specify",
                    "conditionalDisplay": {
                        "field": "drug_allergies",
                        "operator": "contains",
                        "value": "other"
                    }
                },
                {
                    "id": "food_allergies",
                    "type": "checkbox_list",
                    "label": "Food Allergies (Select all)",
                    "options": [
                        {"value": "milk", "label": "Milk / Dairy"},
                        {"value": "eggs", "label": "Eggs"},
                        {"value": "groundnuts", "label": "Groundnuts (Peanuts)"},
                        {"value": "maize", "label": "Maize"},
                        {"value": "wheat", "label": "Wheat"},
                        {"value": "fish", "label": "Fish"},
                        {"value": "other", "label": "Other"}
                    ],
                    "columns": 3,
                    "conditionalDisplay": {
                        "field": "allergy_categories",
                        "operator": "contains",
                        "value": "food"
                    },
                    "columnSpan": "full"
                },
                {
                    "id": "food_allergies_other",
                    "type": "text",
                    "label": "Other Food Allergies",
                    "placeholder": "Specify",
                    "conditionalDisplay": {
                        "field": "food_allergies",
                        "operator": "contains",
                        "value": "other"
                    }
                },
                {
                    "id": "reaction_severity",
                    "type": "select",
                    "label": "Worst Reaction Severity",
                    "options": [
                        {"value": "mild", "label": "Mild (rash, itching)"},
                        {"value": "moderate", "label": "Moderate (swelling, difficulty breathing)"},
                        {"value": "severe", "label": "Severe (hospitalization required)"},
                        {"value": "life_threatening", "label": "Life-Threatening (anaphylaxis)"}
                    ],
                    "conditionalDisplay": {
                        "field": "allergy_categories",
                        "operator": "not_contains",
                        "value": "none_known"
                    }
                },
                {
                    "id": "allergy_management",
                    "type": "textarea",
                    "label": "Allergy Management",
                    "rows": 2,
                    "placeholder": "EpiPen, antihistamines, avoidance measures, etc.",
                    "helperText": "How allergies are currently managed",
                    "conditionalDisplay": {
                        "field": "allergy_categories",
                        "operator": "not_contains",
                        "value": "none_known"
                    },
                    "columnSpan": "full"
                },
                {
                    "id": "allergy_details",
                    "type": "textarea",
                    "label": "Additional Allergy Information",
                    "rows": 2,
                    "placeholder": "Any other details about allergies",
                    "conditionalDisplay": {
                        "field": "allergy_categories",
                        "operator": "not_contains",
                        "value": "none_known"
                    },
                    "columnSpan": "full"
                }
            ]
        },
        {
            "id": "assistive_technology",
            "title": "Assistive Technology Assessment",
            "description": "Current AT use and needs - 18 fields",
            "icon": "heroicon-o-wrench-screwdriver",
            "order": 7,
            "collapsible": true,
            "columns": 2,
            "fields": [
                {
                    "id": "currently_uses_at",
                    "type": "toggle",
                    "label": "Currently Uses Assistive Technology?",
                    "live": true,
                    "columnSpan": "full"
                },
                {
                    "id": "at_devices_current",
                    "type": "checkbox_list",
                    "label": "Current AT Devices (Select all)",
                    "options": [
                        {"value": "wheelchair", "label": "Wheelchair"},
                        {"value": "walker", "label": "Walker / Crutches"},
                        {"value": "hearing_aid", "label": "Hearing Aid"},
                        {"value": "glasses", "label": "Glasses / Low Vision Aids"},
                        {"value": "white_cane", "label": "White Cane"},
                        {"value": "braille_device", "label": "Braille Device"},
                        {"value": "communication_device", "label": "Communication Device (AAC)"},
                        {"value": "prosthetic", "label": "Prosthetic Limb"},
                        {"value": "orthotic", "label": "Orthotic Device"},
                        {"value": "other", "label": "Other"}
                    ],
                    "columns": 3,
                    "live": true,
                    "conditionalDisplay": {
                        "field": "currently_uses_at",
                        "operator": "equals",
                        "value": true
                    },
                    "columnSpan": "full"
                },
                {
                    "id": "at_devices_other",
                    "type": "text",
                    "label": "Other AT Devices",
                    "placeholder": "Specify",
                    "conditionalDisplay": {
                        "field": "at_devices_current",
                        "operator": "contains",
                        "value": "other"
                    },
                    "columnSpan": "full"
                },
                {
                    "id": "at_device_condition",
                    "type": "select",
                    "label": "Overall Device Condition",
                    "options": [
                        {"value": "good", "label": "Good (functioning well)"},
                        {"value": "fair", "label": "Fair (needs minor repairs)"},
                        {"value": "poor", "label": "Poor (needs major repairs/replacement)"}
                    ],
                    "conditionalDisplay": {
                        "field": "currently_uses_at",
                        "operator": "equals",
                        "value": true
                    }
                },
                {
                    "id": "at_training_received",
                    "type": "toggle",
                    "label": "Training on AT Use Received?",
                    "conditionalDisplay": {
                        "field": "currently_uses_at",
                        "operator": "equals",
                        "value": true
                    }
                },
                {
                    "id": "at_needs_identified",
                    "type": "checkbox_list",
                    "label": "AT Needs Identified (Select all)",
                    "options": [
                        {"value": "mobility", "label": "Mobility Devices"},
                        {"value": "hearing", "label": "Hearing Devices"},
                        {"value": "vision", "label": "Vision Devices"},
                        {"value": "communication", "label": "Communication Devices"},
                        {"value": "daily_living", "label": "Daily Living Aids"},
                        {"value": "education", "label": "Educational Tools"},
                        {"value": "none", "label": "No Additional Needs"}
                    ],
                    "columns": 3,
                    "columnSpan": "full"
                },
                {
                    "id": "at_previous_use",
                    "type": "repeater",
                    "label": "Previous AT Use History",
                    "helperText": "Add each device previously used",
                    "collapsible": true,
                    "defaultItems": 0,
                    "columnSpan": "full",
                    "fields": [
                        {
                            "id": "device_type",
                            "type": "text",
                            "label": "Device Type",
                            "placeholder": "e.g., Wheelchair"
                        },
                        {
                            "id": "years_used",
                            "type": "text",
                            "inputType": "number",
                            "label": "Years Used"
                        },
                        {
                            "id": "reason_stopped",
                            "type": "text",
                            "label": "Reason Stopped Using",
                            "placeholder": "e.g., Broken, outgrown, lost"
                        }
                    ]
                }
            ]
        },
        {
            "id": "education_occupation",
            "title": "Education & Occupation",
            "description": "Educational history and employment status",
            "icon": "heroicon-o-academic-cap",
            "order": 8,
            "collapsible": true,
            "columns": 2,
            "fields": [
                {
                    "id": "currently_in_school",
                    "type": "toggle",
                    "label": "Currently in School?",
                    "live": true
                },
                {
                    "id": "school_name",
                    "type": "text",
                    "label": "School Name",
                    "placeholder": "Current school",
                    "conditionalDisplay": {
                        "field": "currently_in_school",
                        "operator": "equals",
                        "value": true
                    }
                },
                {
                    "id": "school_type",
                    "type": "select",
                    "label": "School Type",
                    "options": [
                        {"value": "regular", "label": "Regular School"},
                        {"value": "special", "label": "Special School"},
                        {"value": "integrated", "label": "Integrated Unit"},
                        {"value": "home_based", "label": "Home-Based Education"}
                    ],
                    "conditionalDisplay": {
                        "field": "currently_in_school",
                        "operator": "equals",
                        "value": true
                    }
                },
                {
                    "id": "current_grade",
                    "type": "text",
                    "label": "Current Grade / Class",
                    "placeholder": "e.g., Grade 5",
                    "conditionalDisplay": {
                        "field": "currently_in_school",
                        "operator": "equals",
                        "value": true
                    }
                },
                {
                    "id": "highest_education",
                    "type": "select",
                    "label": "Highest Education Level",
                    "options": [
                        {"value": "none", "label": "None"},
                        {"value": "primary_incomplete", "label": "Primary (incomplete)"},
                        {"value": "primary_complete", "label": "Primary (complete)"},
                        {"value": "secondary_incomplete", "label": "Secondary (incomplete)"},
                        {"value": "secondary_complete", "label": "Secondary (complete)"},
                        {"value": "tertiary", "label": "Tertiary / University"},
                        {"value": "vocational", "label": "Vocational Training"}
                    ]
                },
                {
                    "id": "literacy_status",
                    "type": "select",
                    "label": "Literacy Status",
                    "options": [
                        {"value": "fluent", "label": "Fluent"},
                        {"value": "basic", "label": "Basic"},
                        {"value": "minimal", "label": "Minimal"},
                        {"value": "none", "label": "None"}
                    ]
                },
                {
                    "id": "employment_status",
                    "type": "select",
                    "label": "Employment Status",
                    "options": [
                        {"value": "employed", "label": "Employed"},
                        {"value": "self_employed", "label": "Self-Employed"},
                        {"value": "unemployed", "label": "Unemployed"},
                        {"value": "student", "label": "Student"},
                        {"value": "retired", "label": "Retired"},
                        {"value": "unable", "label": "Unable to Work"}
                    ],
                    "conditionalDisplay": {
                        "ageCondition": {"minAge": 18}
                    }
                },
                {
                    "id": "occupation",
                    "type": "text",
                    "label": "Occupation / Job Title",
                    "placeholder": "Current or previous occupation",
                    "conditionalDisplay": {
                        "ageCondition": {"minAge": 18}
                    }
                },
                {
                    "id": "vocational_interests",
                    "type": "textarea",
                    "label": "Vocational Interests / Skills",
                    "rows": 2,
                    "placeholder": "Areas of interest for training or employment",
                    "conditionalDisplay": {
                        "ageCondition": {"minAge": 15}
                    },
                    "columnSpan": "full"
                }
            ]
        },
        {
            "id": "functional_screening",
            "title": "Functional Screening",
            "description": "Quick functional assessment - detailed assessment separate",
            "icon": "heroicon-o-clipboard-document-check",
            "order": 9,
            "collapsible": true,
            "columns": 1,
            "fields": [
                {
                    "id": "mobility_status",
                    "type": "radio",
                    "label": "Mobility",
                    "options": [
                        {"value": "independent", "label": "Independent"},
                        {"value": "minimal_assistance", "label": "Minimal Assistance"},
                        {"value": "moderate_assistance", "label": "Moderate Assistance"},
                        {"value": "full_assistance", "label": "Full Assistance"},
                        {"value": "not_assessed", "label": "Not Assessed"}
                    ],
                    "inline": false,
                    "columnSpan": "full"
                },
                {
                    "id": "self_care_status",
                    "type": "radio",
                    "label": "Self-Care (eating, dressing, hygiene)",
                    "options": [
                        {"value": "independent", "label": "Independent"},
                        {"value": "minimal_assistance", "label": "Minimal Assistance"},
                        {"value": "moderate_assistance", "label": "Moderate Assistance"},
                        {"value": "full_assistance", "label": "Full Assistance"},
                        {"value": "not_assessed", "label": "Not Assessed"}
                    ],
                    "inline": false,
                    "columnSpan": "full"
                },
                {
                    "id": "communication_status",
                    "type": "radio",
                    "label": "Communication",
                    "options": [
                        {"value": "age_appropriate", "label": "Age-Appropriate"},
                        {"value": "delayed", "label": "Delayed"},
                        {"value": "severely_limited", "label": "Severely Limited"},
                        {"value": "non_verbal", "label": "Non-Verbal"},
                        {"value": "not_assessed", "label": "Not Assessed"}
                    ],
                    "inline": false,
                    "columnSpan": "full"
                },
                {
                    "id": "social_interaction",
                    "type": "radio",
                    "label": "Social Interaction",
                    "options": [
                        {"value": "age_appropriate", "label": "Age-Appropriate"},
                        {"value": "some_challenges", "label": "Some Challenges"},
                        {"value": "significant_challenges", "label": "Significant Challenges"},
                        {"value": "minimal_interaction", "label": "Minimal Interaction"},
                        {"value": "not_assessed", "label": "Not Assessed"}
                    ],
                    "inline": false,
                    "columnSpan": "full"
                },
                {
                    "id": "functional_screening_notes",
                    "type": "textarea",
                    "label": "Screening Notes",
                    "rows": 3,
                    "placeholder": "Any observations from quick screening",
                    "helperText": "Detailed functional assessment will be conducted separately",
                    "columnSpan": "full"
                }
            ]
        },
        {
            "id": "referral_info",
            "title": "Referral Information",
            "description": "How client came to KISE and presenting concerns",
            "icon": "heroicon-o-arrow-right-circle",
            "order": 10,
            "collapsible": true,
            "columns": 2,
            "fields": [
                {
                    "id": "referral_source",
                    "type": "checkbox_list",
                    "label": "Referral Source (Select all that apply)",
                    "options": [
                        {"value": "self", "label": "Self / Walk-in"},
                        {"value": "school", "label": "School"},
                        {"value": "hospital", "label": "Hospital / Clinic"},
                        {"value": "community_worker", "label": "Community Health Worker"},
                        {"value": "social_media", "label": "Social Media / Online"},
                        {"value": "court", "label": "Court Order"},
                        {"value": "ngo", "label": "NGO / CBO"},
                        {"value": "other", "label": "Other"}
                    ],
                    "columns": 3,
                    "live": true,
                    "columnSpan": "full"
                },
                {
                    "id": "referral_source_other",
                    "type": "text",
                    "label": "Other Referral Source",
                    "placeholder": "Specify",
                    "conditionalDisplay": {
                        "field": "referral_source",
                        "operator": "contains",
                        "value": "other"
                    },
                    "columnSpan": "full"
                },
                {
                    "id": "referrer_name",
                    "type": "text",
                    "label": "Referrer Name",
                    "placeholder": "Name of person/organization who referred"
                },
                {
                    "id": "referrer_contact",
                    "type": "text",
                    "inputType": "tel",
                    "label": "Referrer Contact",
                    "placeholder": "Phone or email",
                    "prefixIcon": "heroicon-o-phone"
                },
                {
                    "id": "reason_for_visit",
                    "type": "textarea",
                    "label": "Chief Complaint / Reason for Visit",
                    "rows": 3,
                    "maxLength": 250,
                    "placeholder": "Brief description of main concern (max 250 characters)",
                    "helperText": "Keep brief and concise - max 250 characters",
                    "validation": {"required": true},
                    "columnSpan": "full"
                },
                {
                    "id": "presenting_concerns_detailed",
                    "type": "textarea",
                    "label": "Detailed Presenting Concerns",
                    "rows": 4,
                    "placeholder": "Detailed description of concerns, symptoms, behaviors, timeline",
                    "helperText": "More detailed information about concerns",
                    "columnSpan": "full"
                },
                {
                    "id": "previous_services",
                    "type": "textarea",
                    "label": "Previous Services / Interventions",
                    "rows": 3,
                    "placeholder": "Any previous therapy, assessments, or interventions received elsewhere",
                    "columnSpan": "full"
                },
                {
                    "id": "referral_documents",
                    "type": "file",
                    "label": "Referral Documents",
                    "helperText": "Upload referral letters, previous reports, medical records",
                    "multiple": true,
                    "directory": "referral-documents",
                    "acceptedFileTypes": ["image/*", "application/pdf"],
                    "columnSpan": "full"
                }
            ]
        },
        {
            "id": "service_posting",
            "title": "Service Screening & Posting",
            "description": "Services needed and routing to departments",
            "icon": "heroicon-o-building-office-2",
            "order": 11,
            "collapsible": true,
            "columns": 2,
            "fields": [
                {
                    "id": "services_needed",
                    "type": "checkbox_list",
                    "label": "Services Needed (Select all)",
                    "helperText": "Based on presenting concerns and screening",
                    "options": [
                        {"value": "educational_assessment", "label": "Educational Assessment"},
                        {"value": "psychological_assessment", "label": "Psychological Assessment"},
                        {"value": "audiology", "label": "Audiology"},
                        {"value": "physiotherapy", "label": "Physiotherapy"},
                        {"value": "occupational_therapy", "label": "Occupational Therapy"},
                        {"value": "speech_language", "label": "Speech & Language Therapy"},
                        {"value": "vision", "label": "Vision Services"},
                        {"value": "counselling", "label": "Counselling"},
                        {"value": "assistive_technology", "label": "Assistive Technology"},
                        {"value": "nutrition", "label": "Nutrition"},
                        {"value": "social_work", "label": "Social Work"},
                        {"value": "other", "label": "Other"}
                    ],
                    "columns": 3,
                    "validation": {"required": true},
                    "columnSpan": "full"
                },
                {
                    "id": "primary_service_posting",
                    "type": "select",
                    "label": "Primary Service Posting",
                    "helperText": "Main service point where client will start",
                    "dataSource": {
                        "model": "Service",
                        "valueField": "name",
                        "labelField": "name"
                    },
                    "searchable": true,
                    "prefixIcon": "heroicon-o-star",
                    "validation": {"required": true}
                },
                {
                    "id": "service_priority",
                    "type": "select",
                    "label": "Service Priority",
                    "options": [
                        {"value": "routine", "label": "Routine"},
                        {"value": "high", "label": "High Priority"},
                        {"value": "urgent", "label": "Urgent"}
                    ],
                    "default": "routine",
                    "helperText": "Based on triage and assessment"
                },
                {
                    "id": "handover_note_intake",
                    "type": "textarea",
                    "label": "Handover Note to Service Point",
                    "rows": 2,
                    "maxLength": 200,
                    "placeholder": "Brief note for service provider (1-2 lines max)",
                    "helperText": "Keep brief - max 200 characters",
                    "columnSpan": "full"
                },
                {
                    "id": "booking_requested",
                    "type": "toggle",
                    "label": "Create Booking Request?",
                    "helperText": "Request Customer Care to schedule appointment",
                    "default": false,
                    "columnSpan": "full"
                },
                {
                    "id": "preferred_appointment_date",
                    "type": "date",
                    "label": "Preferred Appointment Date",
                    "minDate": "today",
                    "conditionalDisplay": {
                        "field": "booking_requested",
                        "operator": "equals",
                        "value": true
                    }
                },
                {
                    "id": "preferred_time",
                    "type": "select",
                    "label": "Preferred Time",
                    "options": [
                        {"value": "morning", "label": "Morning (8AM - 12PM)"},
                        {"value": "afternoon", "label": "Afternoon (12PM - 5PM)"},
                        {"value": "any", "label": "Any Time"}
                    ],
                    "conditionalDisplay": {
                        "field": "booking_requested",
                        "operator": "equals",
                        "value": true
                    }
                }
            ]
        },
        {
            "id": "payment_preview",
            "title": "Payment & Insurance",
            "description": "Payment method and estimated costs",
            "icon": "heroicon-o-credit-card",
            "order": 12,
            "collapsible": true,
            "columns": 2,
            "fields": [
                {
                    "id": "payment_method",
                    "type": "select",
                    "label": "Payment Method",
                    "options": [
                        {"value": "cash", "label": "Cash"},
                        {"value": "mpesa", "label": "M-PESA"},
                        {"value": "sha", "label": "SHA"},
                        {"value": "ncpwd", "label": "NCPWD"},
                        {"value": "waiver", "label": "Waiver / Pro Bono"}
                    ],
                    "validation": {"required": true},
                    "live": true,
                    "prefixIcon": "heroicon-o-banknotes"
                },
                {
                    "id": "insurance_member_number",
                    "type": "text",
                    "label": "Insurance Member Number",
                    "placeholder": "Enter member number",
                    "conditionalDisplay": {
                        "field": "payment_method",
                        "operator": "in",
                        "value": ["sha", "ncpwd"]
                    }
                },
                {
                    "id": "estimated_cost_preview",
                    "type": "placeholder",
                    "label": "Estimated Cost",
                    "content": "Will be calculated based on services selected",
                    "columnSpan": "full"
                },
                {
                    "id": "payment_notes",
                    "type": "textarea",
                    "label": "Payment Notes",
                    "rows": 2,
                    "placeholder": "Special payment arrangements, waivers, etc.",
                    "columnSpan": "full"
                }
            ]
        }
    ]
}
JSON,


        
            'estimated_minutes' => 30,
            'allow_draft' => 1,
            'allow_partial_submission' => 1,
            'is_active' => 1,
            'is_published' => 1,
            'created_by' => 1,
            'updated_by' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
