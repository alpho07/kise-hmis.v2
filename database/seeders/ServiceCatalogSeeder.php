<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $depts = Department::pluck('id', 'name');

        /**
         * Each entry: code, name, base_price, service_type, age_group, category,
         *             requires_sessions, default_session_count, dept
         *
         * age_group  → child | adult | all
         * category   → Assessment | Therapy | Counseling | Consultation | Assistive Technology
         */
        $services = [
            // ── Children services ───────────────────────────────────────────────
            ['code' => 'COT-001', 'name' => 'Children OT',              'base_price' => 500,  'service_type' => 'therapy',              'age_group' => 'child', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Occupational Therapy'],
            ['code' => 'CPT-001', 'name' => 'Children PT',              'base_price' => 500,  'service_type' => 'therapy',              'age_group' => 'child', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Physiotherapy'],
            ['code' => 'CHY-001', 'name' => 'Children Hydrotherapy',    'base_price' => 500,  'service_type' => 'therapy',              'age_group' => 'child', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Physiotherapy'],
            ['code' => 'CFM-001', 'name' => 'Children Fine Motor',      'base_price' => 500,  'service_type' => 'therapy',              'age_group' => 'child', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Occupational Therapy'],
            ['code' => 'CSI-001', 'name' => 'Sensory Integration',      'base_price' => 500,  'service_type' => 'therapy',              'age_group' => 'child', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Occupational Therapy'],
            ['code' => 'CPL-001', 'name' => 'Play Therapy',             'base_price' => 500,  'service_type' => 'therapy',              'age_group' => 'child', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Psychological Services'],
            ['code' => 'CST-001', 'name' => 'Children Speech Therapy',  'base_price' => 500,  'service_type' => 'therapy',              'age_group' => 'child', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Speech & Language Therapy'],
            // ── Adult services ──────────────────────────────────────────────────
            ['code' => 'AAC-001', 'name' => 'Adult Assessment Consultation', 'base_price' => 1000, 'service_type' => 'consultation',    'age_group' => 'adult', 'category' => 'Consultation',         'requires_sessions' => false, 'default_session_count' => null, 'dept' => 'Occupational Therapy'],
            ['code' => 'AOT-001', 'name' => 'Adult OT',                 'base_price' => 1000, 'service_type' => 'therapy',              'age_group' => 'adult', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Occupational Therapy'],
            ['code' => 'APT-001', 'name' => 'Adult PT',                 'base_price' => 1000, 'service_type' => 'therapy',              'age_group' => 'adult', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Physiotherapy'],
            ['code' => 'AHY-001', 'name' => 'Adult Hydrotherapy',       'base_price' => 1500, 'service_type' => 'therapy',              'age_group' => 'adult', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Physiotherapy'],
            ['code' => 'AST-001', 'name' => 'Adult Speech Therapy',     'base_price' => 1000, 'service_type' => 'therapy',              'age_group' => 'adult', 'category' => 'Therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'dept' => 'Speech & Language Therapy'],
            ['code' => 'ASA-001', 'name' => 'Adult Speech Assessment',  'base_price' => 2000, 'service_type' => 'assessment',           'age_group' => 'adult', 'category' => 'Assessment',           'requires_sessions' => false, 'default_session_count' => null, 'dept' => 'Speech & Language Therapy'],
            ['code' => 'AUD-001', 'name' => 'Auditory for Adults',      'base_price' => 1000, 'service_type' => 'assessment',           'age_group' => 'adult', 'category' => 'Assessment',           'requires_sessions' => false, 'default_session_count' => null, 'dept' => 'Audiology'],
            // ── Age-neutral services ────────────────────────────────────────────
            ['code' => 'EAR-001', 'name' => 'Ear Molds (per ear)',      'base_price' => 2000, 'service_type' => 'assistive_technology', 'age_group' => 'all',   'category' => 'Assistive Technology', 'requires_sessions' => false, 'default_session_count' => null, 'dept' => 'Audiology'],
            ['code' => 'NUT-001', 'name' => 'Nutrition Review',         'base_price' => 500,  'service_type' => 'consultation',         'age_group' => 'all',   'category' => 'Consultation',         'requires_sessions' => false, 'default_session_count' => null, 'dept' => 'Guidance & Counseling'],
        ];

        foreach ($services as $svc) {
            $deptId = $svc['dept'] ? ($depts[$svc['dept']] ?? null) : null;

            Service::updateOrCreate(
                ['code' => $svc['code']],
                [
                    'name'                  => $svc['name'],
                    'base_price'            => $svc['base_price'],
                    'service_type'          => $svc['service_type'],
                    'age_group'             => $svc['age_group'],
                    'category'              => $svc['category'],
                    'requires_sessions'     => $svc['requires_sessions'],
                    'default_session_count' => $svc['default_session_count'],
                    'department_id'         => $deptId,
                    'is_active'             => true,
                    'duration_minutes'      => 60,
                ]
            );
        }
    }
}
