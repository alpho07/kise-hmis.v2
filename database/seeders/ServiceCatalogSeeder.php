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

        // Note: 'Play Therapy' is mapped to 'Psychological Services' (actual dept name in DB).
        // 'Nutrition Review' has no exact department match — mapped to 'Guidance & Counseling' as closest available.
        $services = [
            ['code' => 'COT-001', 'name' => 'Children OT',                  'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'child', 'dept' => 'Occupational Therapy'],
            ['code' => 'CPT-001', 'name' => 'Children PT',                  'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'child', 'dept' => 'Physiotherapy'],
            ['code' => 'CHY-001', 'name' => 'Children Hydrotherapy',        'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'child', 'dept' => 'Physiotherapy'],
            ['code' => 'CFM-001', 'name' => 'Children Fine Motor',          'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'child', 'dept' => 'Occupational Therapy'],
            ['code' => 'CSI-001', 'name' => 'Sensory Integration',          'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'child', 'dept' => 'Occupational Therapy'],
            ['code' => 'CPL-001', 'name' => 'Play Therapy',                 'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'child', 'dept' => 'Psychological Services'],
            ['code' => 'CST-001', 'name' => 'Children Speech Therapy',      'base_price' => 500,  'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'child', 'dept' => 'Speech & Language Therapy'],
            ['code' => 'AAC-001', 'name' => 'Adult Assessment Consultation', 'base_price' => 1000, 'service_type' => 'consultation',         'requires_sessions' => false, 'default_session_count' => null, 'category' => 'adult', 'dept' => 'Occupational Therapy'],
            ['code' => 'AOT-001', 'name' => 'Adult OT',                     'base_price' => 1000, 'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'adult', 'dept' => 'Occupational Therapy'],
            ['code' => 'APT-001', 'name' => 'Adult PT',                     'base_price' => 1000, 'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'adult', 'dept' => 'Physiotherapy'],
            ['code' => 'AHY-001', 'name' => 'Adult Hydrotherapy',           'base_price' => 1500, 'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'adult', 'dept' => 'Physiotherapy'],
            ['code' => 'AST-001', 'name' => 'Adult Speech Therapy',         'base_price' => 1000, 'service_type' => 'therapy',              'requires_sessions' => true,  'default_session_count' => 12,   'category' => 'adult', 'dept' => 'Speech & Language Therapy'],
            ['code' => 'ASA-001', 'name' => 'Adult Speech Assessment',      'base_price' => 2000, 'service_type' => 'assessment',           'requires_sessions' => false, 'default_session_count' => null, 'category' => 'adult', 'dept' => 'Speech & Language Therapy'],
            ['code' => 'AUD-001', 'name' => 'Auditory for Adults',          'base_price' => 1000, 'service_type' => 'assessment',           'requires_sessions' => false, 'default_session_count' => null, 'category' => 'adult', 'dept' => 'Audiology'],
            ['code' => 'EAR-001', 'name' => 'Ear Molds (per ear)',          'base_price' => 2000, 'service_type' => 'assistive_technology', 'requires_sessions' => false, 'default_session_count' => null, 'category' => 'both',  'dept' => 'Audiology'],
            ['code' => 'NUT-001', 'name' => 'Nutrition Review',             'base_price' => 500,  'service_type' => 'consultation',         'requires_sessions' => false, 'default_session_count' => null, 'category' => 'both',  'dept' => 'Guidance & Counseling'],
        ];

        foreach ($services as $svc) {
            $deptId = $svc['dept'] ? ($depts[$svc['dept']] ?? null) : null;
            Service::updateOrCreate(
                ['code' => $svc['code']],
                [
                    'name'                  => $svc['name'],
                    'base_price'            => $svc['base_price'],
                    'service_type'          => $svc['service_type'],
                    'requires_sessions'     => $svc['requires_sessions'],
                    'default_session_count' => $svc['default_session_count'],
                    'category'              => $svc['category'],
                    'department_id'         => $deptId,
                    'is_active'             => true,
                    'duration_minutes'      => 60,
                ]
            );
        }
    }
}
