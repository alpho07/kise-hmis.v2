<?php

namespace App\Http\Controllers;

use App\Models\ClientDisability;
use App\Models\ClientEducation;
use App\Models\ClientMedicalHistory;
use App\Models\ClientSocioDemographic;
use App\Models\FunctionalScreening;
use App\Models\IntakeAssessment;
use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class IntakeAssessmentReportController extends Controller
{
    public function __invoke(IntakeAssessment $intake)
    {
        abort_unless(
            auth()->user()?->hasRole(['intake_officer', 'admin', 'super_admin']),
            403
        );

        $intake->load(['visit.branch', 'client.county', 'client.subCounty', 'client.ward', 'functionalScreening', 'assessedBy']);
        $client = $intake->client;
        $visit  = $intake->visit;
        $sr     = $intake->services_required ?? [];

        // ── Related records ──────────────────────────────────────────────────
        $disability = ClientDisability::where('client_id', $client->id)->first();
        $socio      = ClientSocioDemographic::where('client_id', $client->id)->first();
        $med        = ClientMedicalHistory::where('client_id', $client->id)->first();
        $edu        = ClientEducation::where('client_id', $client->id)->first();

        // ── Service names ────────────────────────────────────────────────────
        $allServiceIds = array_unique(array_merge(
            (array) ($sr['service_ids'] ?? []),
            array_filter([$sr['primary_service_id'] ?? null])
        ));
        $services = Service::whereIn('id', $allServiceIds)->get()->keyBy('id');

        // ── Functional screening ─────────────────────────────────────────────
        $screeningScores = $intake->functional_screening_scores ?? [];

        $data = [
            'intake'          => $intake,
            'client'          => $client,
            'visit'           => $visit,
            'sr'              => $sr,
            'disability'      => $disability,
            'socio'           => $socio,
            'med'             => $med,
            'edu'             => $edu,
            'services'        => $services,
            'screeningScores' => $screeningScores,
            'printedAt'       => now(),
            'printedBy'       => auth()->user(),
            'labels'          => self::labels(),
        ];

        $pdf = Pdf::loadView('intake.assessment-report', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top',    '12mm')
            ->setOption('margin-bottom', '14mm')
            ->setOption('margin-left',   '12mm')
            ->setOption('margin-right',  '12mm')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $filename = sprintf(
            'Intake_Assessment_%s_%s.pdf',
            str_replace(['/', '\\'], '-', $client->uci ?? 'unknown'),
            now()->format('Ymd')
        );

        return $pdf->stream($filename);
    }

    /** Centralised enum → human label maps shared with the Blade view. */
    public static function labels(): array
    {
        return [
            'gender' => [
                'male' => 'Male', 'female' => 'Female', 'other' => 'Other / Not Specified',
            ],
            'verification_mode' => [
                'national_id'        => 'National ID',
                'birth_certificate'  => 'Birth Certificate',
                'passport'           => 'Passport',
                'school_letter'      => 'School Letter',
                'parent_id'          => 'Parent / Guardian ID',
                'none'               => 'None',
            ],
            'disability_categories' => [
                'visual'              => 'Visual Impairment',
                'hearing'             => 'Hearing Impairment',
                'physical'            => 'Physical / Mobility',
                'intellectual'        => 'Intellectual Disability',
                'learning'            => 'Learning Disability',
                'speech_language'     => 'Speech & Language',
                'autism'              => 'Autism Spectrum',
                'multiple'            => 'Multiple Disabilities',
                'psychosocial'        => 'Psychosocial / Mental Health',
                'deafblind'           => 'Deafblind',
                'albinism'            => 'Albinism',
                'other'               => 'Other',
            ],
            'onset' => [
                'congenital'    => 'Congenital (from birth)',
                'acquired'      => 'Acquired',
                'progressive'   => 'Progressive',
                'unknown'       => 'Unknown',
            ],
            'level_of_functioning' => [
                'independent'       => 'Independent',
                'mild_support'      => 'Mild support needed',
                'moderate_support'  => 'Moderate support needed',
                'full_support'      => 'Full support needed',
            ],
            'ncpwd_registered' => [
                'yes' => 'Yes', 'no' => 'No', 'unknown' => 'Unknown',
            ],
            'ncpwd_verification_status' => [
                'pending'       => 'Pending',
                'verified'      => 'Verified',
                'not_verified'  => 'Not Verified',
            ],
            'marital_status' => [
                'single'    => 'Single',
                'married'   => 'Married',
                'divorced'  => 'Divorced',
                'widowed'   => 'Widowed',
                'separated' => 'Separated',
                'other'     => 'Other',
            ],
            'living_arrangement' => [
                'alone'              => 'Alone',
                'with_family'        => 'With Family',
                'with_caregiver'     => 'With Caregiver',
                'institution'        => 'Institution / Facility',
                'other'              => 'Other',
            ],
            'primary_caregiver' => [
                'both_parents'   => 'Both Parents',
                'mother'         => 'Mother',
                'father'         => 'Father',
                'grandparent'    => 'Grandparent',
                'sibling'        => 'Sibling',
                'relative'       => 'Other Relative',
                'guardian'       => 'Legal Guardian',
                'institution'    => 'Institutional Care',
                'self'           => 'Self',
                'other'          => 'Other',
            ],
            'source_of_support' => [
                'family'        => 'Family',
                'government'    => 'Government (cash transfer, etc.)',
                'ngo'           => 'NGO / CBO',
                'employer'      => 'Employer',
                'self'          => 'Self-employed / Own savings',
                'none'          => 'None',
                'other'         => 'Other',
            ],
            'primary_language' => [
                'english'   => 'English',
                'swahili'   => 'Swahili / Kiswahili',
                'kikuyu'    => 'Kikuyu',
                'luo'       => 'Luo',
                'luhya'     => 'Luhya',
                'kamba'     => 'Kamba',
                'kisii'     => 'Kisii',
                'kalenjin'  => 'Kalenjin',
                'meru'      => 'Meru',
                'sign_language' => 'Kenyan Sign Language (KSL)',
                'other'     => 'Other',
            ],
            'education_level' => [
                'none'       => 'None / Not Applicable',
                'ecd'        => 'Early Childhood Development (ECD)',
                'primary'    => 'Primary',
                'secondary'  => 'Secondary',
                'tertiary'   => 'Tertiary / College / University',
                'vocational' => 'Vocational / TVET',
            ],
            'school_type' => [
                'regular'    => 'Regular',
                'special'    => 'Special School',
                'integrated' => 'Integrated / Inclusive Unit',
                'homeschool' => 'Home Schooling',
            ],
            'employment_status' => [
                'unemployed'    => 'Unemployed',
                'employed'      => 'Employed (formal)',
                'self_employed' => 'Self-employed / Informal',
                'student'       => 'Student',
                'retired'       => 'Retired',
                'other'         => 'Other',
            ],
            'referral_source' => [
                'self'             => 'Self / Family',
                'school'           => 'School / ECD Centre',
                'hospital'         => 'Hospital / Health Facility',
                'community_worker' => 'Community Health Worker',
                'social_media'     => 'Social Media',
                'court'            => 'Court / Legal System',
                'kise_internal'    => 'KISE Internal Referral',
                'other'            => 'Other',
            ],
            'payment_method' => [
                'cash'      => 'Cash',
                'sha'       => 'SHA (Social Health Authority)',
                'ncpwd'     => 'NCPWD',
                'insurance' => 'Private Insurance',
                'credit'    => 'Credit / Deferred',
                'waiver'    => 'Waiver / Pro-bono',
                'hybrid'    => 'Hybrid (multiple)',
            ],
            'visit_type' => [
                'new'       => 'New Visit',
                'follow_up' => 'Follow-up',
                'urgent'    => 'Urgent',
                'emergency' => 'Emergency',
            ],
            'deferral_reason' => [
                'client_not_ready'  => 'Client Not Ready',
                'documents_missing' => 'Documents Missing',
                'capacity'          => 'Capacity / No slot today',
                'financial'         => 'Financial constraint',
                'other'             => 'Other',
            ],
        ];
    }
}
