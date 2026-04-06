<?php

namespace Database\Seeders;

use App\Models\AssessmentFormSchema;
use Illuminate\Database\Seeder;

class AudiologyFormsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding Audiology Forms...');

        // 1. HEARING SCREENING FORM
        AssessmentFormSchema::create([
            'name' => 'Hearing Screening & Initial Assessment',
            'slug' => 'audiology-hearing-screening',
            'version' => 1,
            'category' => 'audiology',
            'description' => 'Initial hearing screening including play-based and behavioural screening protocols for all ages',
            'estimated_minutes' => 30,
            'allow_draft' => true,
            'allow_partial_submission' => false,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getHearingScreeningSchema(),
        ]);
        $this->command->info('  ✓ Hearing Screening & Initial Assessment');

        // 2. AUDIOLOGICAL DIAGNOSTIC ASSESSMENT
        AssessmentFormSchema::create([
            'name' => 'Audiological Diagnostic Assessment',
            'slug' => 'audiology-diagnostic-assessment',
            'version' => 1,
            'category' => 'audiology',
            'description' => 'Comprehensive audiological diagnostic assessment including pure tone audiometry, tympanometry, and management planning',
            'estimated_minutes' => 60,
            'allow_draft' => true,
            'allow_partial_submission' => true,
            'is_active' => true,
            'is_published' => true,
            'created_by' => 1,
            'schema' => $this->getDiagnosticAssessmentSchema(),
        ]);
        $this->command->info('  ✓ Audiological Diagnostic Assessment');
    }

    private function getHearingScreeningSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Referral & Background',
                    'icon' => 'heroicon-o-document-text',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'referral_reason', 'type' => 'textarea', 'label' => 'Reason for Hearing Screening Referral', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'referral_source', 'type' => 'select', 'label' => 'Referred by', 'options' => ['Medical Officer', 'Paediatrician', 'ENT Specialist', 'Teacher', 'Parent / Guardian', 'Other KISE Service', 'Self-referral'], 'columnSpan' => 1],
                        ['id' => 'referral_date', 'type' => 'date', 'label' => 'Referral Date', 'columnSpan' => 1],
                        ['id' => 'hearing_concern_onset', 'type' => 'select', 'label' => 'Onset of Hearing Concern', 'options' => ['At birth / congenital', 'First year of life', 'Before school age', 'School age', 'Recent onset (adult)', 'Unknown'], 'columnSpan' => 1],
                        ['id' => 'ear_concern', 'type' => 'radio', 'label' => 'Ear of Concern', 'options' => ['Right ear', 'Left ear', 'Both ears', 'Unknown'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Risk Factors (Hearing Loss)',
                    'icon' => 'heroicon-o-shield-exclamation',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'rf_note', 'type' => 'placeholder', 'label' => 'JCIH Risk Indicators — check all that apply'],
                        ['id' => 'rf_family_history', 'type' => 'checkbox', 'label' => 'Family history of permanent childhood hearing loss'],
                        ['id' => 'rf_nicu', 'type' => 'checkbox', 'label' => 'NICU admission >5 days or any ECMO, assisted ventilation, ototoxic medications'],
                        ['id' => 'rf_in_utero_infection', 'type' => 'checkbox', 'label' => 'In utero infection (CMV, herpes, rubella, syphilis, toxoplasmosis)'],
                        ['id' => 'rf_craniofacial', 'type' => 'checkbox', 'label' => 'Craniofacial anomalies involving ear, ear canal, auricle, or temporal bone'],
                        ['id' => 'rf_syndrome', 'type' => 'checkbox', 'label' => 'Syndrome associated with hearing loss (Down syndrome, CHARGE, Usher, Treacher Collins, etc.)'],
                        ['id' => 'rf_postnatal_infection', 'type' => 'checkbox', 'label' => 'Postnatal infections associated with HL (meningitis, measles, mumps)'],
                        ['id' => 'rf_head_trauma', 'type' => 'checkbox', 'label' => 'Head trauma especially temporal bone or skull base fracture'],
                        ['id' => 'rf_chemotherapy', 'type' => 'checkbox', 'label' => 'Chemotherapy / ototoxic medications'],
                        ['id' => 'rf_recurrent_om', 'type' => 'checkbox', 'label' => 'Recurrent or persistent otitis media with effusion (OME)'],
                        ['id' => 'rf_noise_exposure', 'type' => 'checkbox', 'label' => 'Significant noise exposure'],
                        ['id' => 'rf_caregiver_concern', 'type' => 'checkbox', 'label' => 'Caregiver / parent concern about hearing, speech, language, or developmental delay'],
                    ],
                ],
                [
                    'title' => 'Pre-Screening Otoscopy',
                    'icon' => 'heroicon-o-eye',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'otoscopy_right', 'type' => 'select', 'label' => 'Right Ear — Otoscopy Findings', 'options' => ['Normal', 'Cerumen impaction', 'Otitis media (acute)', 'Otitis media with effusion', 'Perforated tympanic membrane', 'Discharge / otorrhoea', 'Foreign body', 'Unable to visualise'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'otoscopy_left', 'type' => 'select', 'label' => 'Left Ear — Otoscopy Findings', 'options' => ['Normal', 'Cerumen impaction', 'Otitis media (acute)', 'Otitis media with effusion', 'Perforated tympanic membrane', 'Discharge / otorrhoea', 'Foreign body', 'Unable to visualise'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'otoscopy_notes', 'type' => 'textarea', 'label' => 'Otoscopy Notes', 'columnSpan' => 2],
                        ['id' => 'proceed_to_test', 'type' => 'radio', 'label' => 'Proceed to Hearing Test?', 'options' => ['Yes — both ears clear', 'Yes — with caution', 'No — refer to ENT first'], 'validation' => ['required'], 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Hearing Screening Method & Results',
                    'icon' => 'heroicon-o-speaker-wave',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'screening_method', 'type' => 'select', 'label' => 'Screening Method Used', 'options' => ['Pure Tone Audiometry (PTA) — Sweep test', 'Otoacoustic Emissions (OAE)', 'Automated ABR (AABR)', 'Play Audiometry', 'Visual Reinforcement Audiometry (VRA)', 'Behavioural Observation Audiometry (BOA)', 'Conditioned Play Audiometry (CPA)'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'screening_frequencies', 'type' => 'text', 'label' => 'Screening Frequencies / Levels Used (e.g., 25dB HL at 1k, 2k, 4k)', 'columnSpan' => 1],
                        ['id' => 'right_result', 'type' => 'radio', 'label' => 'Right Ear — Screening Result', 'options' => ['PASS', 'REFER / FAIL', 'Unable to test'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'left_result', 'type' => 'radio', 'label' => 'Left Ear — Screening Result', 'options' => ['PASS', 'REFER / FAIL', 'Unable to test'], 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'oae_right', 'type' => 'select', 'label' => 'OAE Right (if performed)', 'options' => ['Pass', 'Refer', 'Not performed'], 'columnSpan' => 1],
                        ['id' => 'oae_left', 'type' => 'select', 'label' => 'OAE Left (if performed)', 'options' => ['Pass', 'Refer', 'Not performed'], 'columnSpan' => 1],
                        ['id' => 'screening_notes', 'type' => 'textarea', 'label' => 'Screening Notes / Observations', 'columnSpan' => 2],
                        ['id' => 'patient_cooperation', 'type' => 'radio', 'label' => 'Patient Cooperation', 'options' => ['Excellent', 'Good', 'Fair', 'Poor — results may be unreliable'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Behavioural Hearing Indicators (Play-Based)',
                    'icon' => 'heroicon-o-puzzle-piece',
                    'columns' => 1,
                    'fields' => [
                        ['id' => 'bhi_note', 'type' => 'placeholder', 'label' => 'For children — observe during screening. Check all observed.'],
                        ['id' => 'bhi_responds_name', 'type' => 'checkbox', 'label' => 'Responds to own name being called'],
                        ['id' => 'bhi_localises', 'type' => 'checkbox', 'label' => 'Turns to localise sound source'],
                        ['id' => 'bhi_responds_speech', 'type' => 'checkbox', 'label' => 'Responds appropriately to conversational speech'],
                        ['id' => 'bhi_follows_instructions', 'type' => 'checkbox', 'label' => 'Follows simple verbal instructions without visual cues'],
                        ['id' => 'bhi_speech_development', 'type' => 'checkbox', 'label' => 'Speech / language development appears age appropriate'],
                        ['id' => 'bhi_tv_volume', 'type' => 'checkbox', 'label' => 'Does NOT require TV/radio at unusually high volume'],
                    ],
                ],
                [
                    'title' => 'Outcome & Next Steps',
                    'icon' => 'heroicon-o-check-badge',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'overall_outcome', 'type' => 'radio', 'label' => 'Overall Screening Outcome', 'options' => ['PASS — No further action needed', 'REFER — Proceed to diagnostic audiology assessment', 'REFER — ENT referral first', 'Rescreen — unable to complete reliably'], 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'follow_up_plan', 'type' => 'textarea', 'label' => 'Follow-up Plan / Recommendations', 'columnSpan' => 2],
                        ['id' => 'audiologist_name', 'type' => 'text', 'label' => 'Screened by', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'screening_date', 'type' => 'date', 'label' => 'Date', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }

    private function getDiagnosticAssessmentSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'History & Background',
                    'icon' => 'heroicon-o-document-text',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'presenting_complaint', 'type' => 'textarea', 'label' => 'Presenting Complaint', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'hearing_loss_duration', 'type' => 'text', 'label' => 'Duration of Hearing Difficulty', 'columnSpan' => 1],
                        ['id' => 'hearing_loss_progression', 'type' => 'radio', 'label' => 'Progression', 'options' => ['Stable', 'Progressive', 'Fluctuating', 'Sudden onset', 'Unknown'], 'columnSpan' => 1],
                        ['id' => 'tinnitus', 'type' => 'radio', 'label' => 'Tinnitus', 'options' => ['Yes — unilateral', 'Yes — bilateral', 'No'], 'columnSpan' => 1],
                        ['id' => 'vertigo', 'type' => 'radio', 'label' => 'Vertigo / Dizziness', 'options' => ['Yes', 'No'], 'columnSpan' => 1],
                        ['id' => 'otalgia', 'type' => 'radio', 'label' => 'Ear Pain (Otalgia)', 'options' => ['Yes — right', 'Yes — left', 'Yes — bilateral', 'No'], 'columnSpan' => 1],
                        ['id' => 'otorrhoea', 'type' => 'radio', 'label' => 'Ear Discharge (Otorrhoea)', 'options' => ['Yes', 'No'], 'columnSpan' => 1],
                        ['id' => 'previous_ent_history', 'type' => 'textarea', 'label' => 'Previous ENT History (surgeries, tubes, infections)', 'columnSpan' => 2],
                        ['id' => 'relevant_medical_history', 'type' => 'textarea', 'label' => 'Relevant Medical History', 'columnSpan' => 2],
                        ['id' => 'current_hearing_aids', 'type' => 'radio', 'label' => 'Currently Using Hearing Aids', 'options' => ['Yes — bilateral', 'Yes — unilateral', 'No', 'Trial previously'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Otoscopy & Middle Ear Examination',
                    'icon' => 'heroicon-o-eye',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'otoscopy_right_diag', 'type' => 'select', 'label' => 'Right Ear', 'options' => ['Normal tympanic membrane', 'Retracted TM', 'Perforated TM', 'Otitis media with effusion', 'Cholesteatoma suspected', 'Cerumen — partial', 'Cerumen — total occlusion', 'Post-surgical changes', 'Other'], 'columnSpan' => 1],
                        ['id' => 'otoscopy_left_diag', 'type' => 'select', 'label' => 'Left Ear', 'options' => ['Normal tympanic membrane', 'Retracted TM', 'Perforated TM', 'Otitis media with effusion', 'Cholesteatoma suspected', 'Cerumen — partial', 'Cerumen — total occlusion', 'Post-surgical changes', 'Other'], 'columnSpan' => 1],
                        ['id' => 'otoscopy_details', 'type' => 'textarea', 'label' => 'Otoscopy Details', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Pure Tone Audiometry (PTA)',
                    'icon' => 'heroicon-o-chart-bar',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'pta_note', 'type' => 'placeholder', 'label' => 'Record thresholds in dB HL. Document all measured frequencies.'],
                        ['id' => 'pta_right_ac', 'type' => 'textarea', 'label' => 'Right Ear — Air Conduction (250-8000 Hz)', 'columnSpan' => 1],
                        ['id' => 'pta_left_ac', 'type' => 'textarea', 'label' => 'Left Ear — Air Conduction (250-8000 Hz)', 'columnSpan' => 1],
                        ['id' => 'pta_right_bc', 'type' => 'textarea', 'label' => 'Right Ear — Bone Conduction', 'columnSpan' => 1],
                        ['id' => 'pta_left_bc', 'type' => 'textarea', 'label' => 'Left Ear — Bone Conduction', 'columnSpan' => 1],
                        ['id' => 'pta_right_pta', 'type' => 'text', 'label' => 'Right PTA (average 500-4000 Hz) dB HL', 'columnSpan' => 1],
                        ['id' => 'pta_left_pta', 'type' => 'text', 'label' => 'Left PTA (average 500-4000 Hz) dB HL', 'columnSpan' => 1],
                        ['id' => 'pta_right_classification', 'type' => 'select', 'label' => 'Right Ear — Degree', 'options' => ['Normal hearing (<26 dB)', 'Slight (26-40 dB)', 'Mild (41-55 dB)', 'Moderate (56-70 dB)', 'Moderately severe (71-90 dB)', 'Severe (91-100 dB)', 'Profound (>100 dB)', 'Unable to assess'], 'columnSpan' => 1],
                        ['id' => 'pta_left_classification', 'type' => 'select', 'label' => 'Left Ear — Degree', 'options' => ['Normal hearing (<26 dB)', 'Slight (26-40 dB)', 'Mild (41-55 dB)', 'Moderate (56-70 dB)', 'Moderately severe (71-90 dB)', 'Severe (91-100 dB)', 'Profound (>100 dB)', 'Unable to assess'], 'columnSpan' => 1],
                        ['id' => 'pta_type_right', 'type' => 'select', 'label' => 'Right — Type of Loss', 'options' => ['Normal', 'Conductive', 'Sensorineural', 'Mixed', 'Indeterminate'], 'columnSpan' => 1],
                        ['id' => 'pta_type_left', 'type' => 'select', 'label' => 'Left — Type of Loss', 'options' => ['Normal', 'Conductive', 'Sensorineural', 'Mixed', 'Indeterminate'], 'columnSpan' => 1],
                        ['id' => 'pta_reliability', 'type' => 'radio', 'label' => 'Test Reliability', 'options' => ['Reliable', 'Questionable', 'Unreliable'], 'columnSpan' => 1],
                    ],
                ],
                [
                    'title' => 'Tympanometry',
                    'icon' => 'heroicon-o-signal',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'tymp_right', 'type' => 'select', 'label' => 'Right Ear — Tympanogram Type', 'options' => ['Type A (normal)', 'Type As (shallow)', 'Type Ad (deep)', 'Type B (flat — effusion/perforation)', 'Type C (negative pressure)', 'Not performed', 'Contraindicated'], 'columnSpan' => 1],
                        ['id' => 'tymp_left', 'type' => 'select', 'label' => 'Left Ear — Tympanogram Type', 'options' => ['Type A (normal)', 'Type As (shallow)', 'Type Ad (deep)', 'Type B (flat — effusion/perforation)', 'Type C (negative pressure)', 'Not performed', 'Contraindicated'], 'columnSpan' => 1],
                        ['id' => 'tymp_ear_canal_volume', 'type' => 'text', 'label' => 'Ear Canal Volume (right / left) cm³', 'columnSpan' => 1],
                        ['id' => 'acoustic_reflexes', 'type' => 'textarea', 'label' => 'Acoustic Reflex Findings', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Speech Audiometry',
                    'icon' => 'heroicon-o-microphone',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'srt_right', 'type' => 'text', 'label' => 'Right SRT / SAT (dB HL)', 'columnSpan' => 1],
                        ['id' => 'srt_left', 'type' => 'text', 'label' => 'Left SRT / SAT (dB HL)', 'columnSpan' => 1],
                        ['id' => 'sds_right', 'type' => 'text', 'label' => 'Right Word Recognition Score (%)', 'columnSpan' => 1],
                        ['id' => 'sds_left', 'type' => 'text', 'label' => 'Left Word Recognition Score (%)', 'columnSpan' => 1],
                        ['id' => 'speech_notes', 'type' => 'textarea', 'label' => 'Speech Audiometry Notes', 'columnSpan' => 2],
                    ],
                ],
                [
                    'title' => 'Diagnosis & Management Plan',
                    'icon' => 'heroicon-o-light-bulb',
                    'columns' => 2,
                    'fields' => [
                        ['id' => 'audiological_diagnosis', 'type' => 'textarea', 'label' => 'Audiological Diagnosis', 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'suspected_aetiology', 'type' => 'textarea', 'label' => 'Suspected Aetiology', 'columnSpan' => 2],
                        ['id' => 'management_plan', 'type' => 'radio', 'label' => 'Primary Management Recommendation', 'options' => ['Hearing aid fitting', 'Cochlear implant evaluation', 'Medical / surgical referral (ENT)', 'Monitoring — no intervention now', 'Auditory rehabilitation', 'Combined (HA + rehabilitation)'], 'validation' => ['required'], 'columnSpan' => 2],
                        ['id' => 'ha_candidacy', 'type' => 'radio', 'label' => 'Hearing Aid Candidacy', 'options' => ['Candidate — bilateral', 'Candidate — unilateral', 'Not a candidate at this time', 'Already fitted'], 'columnSpan' => 1],
                        ['id' => 'ent_referral', 'type' => 'radio', 'label' => 'ENT Referral Required', 'options' => ['Yes — urgent', 'Yes — routine', 'No'], 'columnSpan' => 1],
                        ['id' => 'communication_strategies', 'type' => 'textarea', 'label' => 'Communication Strategies Counselled', 'columnSpan' => 2],
                        ['id' => 'school_recommendations', 'type' => 'textarea', 'label' => 'Classroom / School Recommendations (if applicable)', 'columnSpan' => 2],
                        ['id' => 'further_tests', 'type' => 'textarea', 'label' => 'Further Tests Required (ABR, ASSR, VEMP, imaging)', 'columnSpan' => 2],
                        ['id' => 'counselling_provided', 'type' => 'textarea', 'label' => 'Counselling Provided (to client / family)', 'columnSpan' => 2],
                        ['id' => 'review_plan', 'type' => 'text', 'label' => 'Review / Follow-up Plan', 'columnSpan' => 1],
                        ['id' => 'audiologist_name', 'type' => 'text', 'label' => 'Audiologist', 'validation' => ['required'], 'columnSpan' => 1],
                        ['id' => 'assessment_date', 'type' => 'date', 'label' => 'Date of Assessment', 'validation' => ['required'], 'columnSpan' => 1],
                    ],
                ],
            ],
        ];
    }
}
