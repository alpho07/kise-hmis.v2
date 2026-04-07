<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a dedicated `age_group` column to the services table and normalises
 * the `category` column to carry only business-level service categories.
 *
 * Previously `category` was overloaded: services seeded via DatabaseSeeder
 * stored a service-type label ('Assessment', 'Therapy', etc.) while services
 * seeded via ServiceCatalogSeeder stored an age-group label ('child', 'adult',
 * 'both'). This migration separates the two concerns cleanly:
 *
 *   age_group  → WHO receives the service   (child | adult | all)
 *   category   → WHAT kind of service it is (Assessment | Therapy | Counseling |
 *                                            Consultation | Assistive Technology)
 */
return new class extends Migration
{
    /** Canonical business categories — only these values are valid after this migration. */
    private const BUSINESS_CATEGORIES = [
        'Assessment',
        'Therapy',
        'Counseling',
        'Consultation',
        'Assistive Technology',
    ];

    public function up(): void
    {
        // 1. Add the age_group column
        Schema::table('services', function (Blueprint $table) {
            $table->enum('age_group', ['child', 'adult', 'all'])
                  ->default('all')
                  ->after('category')
                  ->comment('Target client age group: child (<18), adult (≥18), or all');
        });

        // 2. Seed age_group from the existing (inconsistent) category values
        //    and normalise category to a proper business label.
        //    Records are identified by their stable `id` values set by seeders.
        $services = [
            // id => [age_group, normalised_category]
            // ── Generic assessments (seeded by DatabaseSeeder, IDs 1–9) ──────────
            1  => ['all',   'Assessment'],         // Physiotherapy Initial Assessment
            2  => ['all',   'Therapy'],             // Physiotherapy Session
            3  => ['all',   'Assessment'],          // Occupational Therapy Assessment
            4  => ['all',   'Therapy'],             // Occupational Therapy Session
            5  => ['all',   'Assessment'],          // Speech & Language Assessment
            6  => ['all',   'Therapy'],             // Speech Therapy Session
            7  => ['all',   'Assessment'],          // Psychological Assessment
            8  => ['all',   'Counseling'],          // Counseling Session
            9  => ['all',   'Assessment'],          // Educational Assessment
            // ── Child-specific services (IDs 10–16) ──────────────────────────────
            10 => ['child', 'Therapy'],             // Children OT
            11 => ['child', 'Therapy'],             // Children PT
            12 => ['child', 'Therapy'],             // Children Hydrotherapy
            13 => ['child', 'Therapy'],             // Children Fine Motor
            14 => ['child', 'Therapy'],             // Sensory Integration
            15 => ['child', 'Therapy'],             // Play Therapy
            16 => ['child', 'Therapy'],             // Children Speech Therapy
            // ── Adult-specific services (IDs 17–23) ──────────────────────────────
            17 => ['adult', 'Consultation'],        // Adult Assessment Consultation
            18 => ['adult', 'Therapy'],             // Adult OT
            19 => ['adult', 'Therapy'],             // Adult PT
            20 => ['adult', 'Therapy'],             // Adult Hydrotherapy
            21 => ['adult', 'Therapy'],             // Adult Speech Therapy
            22 => ['adult', 'Assessment'],          // Adult Speech Assessment
            23 => ['adult', 'Assessment'],          // Auditory for Adults
            // ── Age-neutral services (IDs 24–25) ─────────────────────────────────
            24 => ['all',   'Assistive Technology'],// Ear Molds (per ear)
            25 => ['all',   'Consultation'],        // Nutrition Review
        ];

        foreach ($services as $id => [$ageGroup, $category]) {
            DB::table('services')
                ->where('id', $id)
                ->update([
                    'age_group' => $ageGroup,
                    'category'  => $category,
                ]);
        }

        // 3. Any services added outside the seeders (e.g. manually via admin UI)
        //    that still carry old age-label values in category should be normalised.
        DB::table('services')
            ->whereIn('category', ['child', 'adult', 'both'])
            ->update(['category' => 'Therapy']); // safe fallback — admin can correct

        // 4. Fix service_type for DatabaseSeeder records that were seeded without it.
        //    Sessions and counseling should be 'therapy', not 'assessment'.
        $serviceTypeCorrections = [
            2 => 'therapy',   // Physiotherapy Session
            4 => 'therapy',   // Occupational Therapy Session
            6 => 'therapy',   // Speech Therapy Session
            8 => 'therapy',   // Counseling Session
        ];
        foreach ($serviceTypeCorrections as $id => $type) {
            DB::table('services')->where('id', $id)->update(['service_type' => $type]);
        }

        // 4. Performance index on age_group (used in every intake service lookup)
        Schema::table('services', function (Blueprint $table) {
            $table->index('age_group', 'idx_services_age_group');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('idx_services_age_group');
            $table->dropColumn('age_group');
        });

        // Restore the old (inconsistent) child/adult/both labels in category
        // for the known seeded records.
        $restore = [
            10 => 'child', 11 => 'child', 12 => 'child', 13 => 'child',
            14 => 'child', 15 => 'child', 16 => 'child',
            17 => 'adult', 18 => 'adult', 19 => 'adult', 20 => 'adult',
            21 => 'adult', 22 => 'adult', 23 => 'adult',
            24 => 'both',  25 => 'both',
        ];

        foreach ($restore as $id => $cat) {
            DB::table('services')->where('id', $id)->update(['category' => $cat]);
        }
    }
};
