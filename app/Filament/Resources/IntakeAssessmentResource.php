<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntakeAssessmentResource\Pages;
use App\Models\County;
use App\Models\Department;
use App\Models\IntakeAssessment;
use App\Models\Service;
use App\Models\SubCounty;
use App\Models\Visit;
use App\Models\Ward;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class IntakeAssessmentResource extends Resource
{
    protected static ?string $model = IntakeAssessment::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Intake Assessment';
    protected static ?string $navigationGroup = 'Clinical Workflow';
    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool { return false; }

    // ─── Age Helpers ─────────────────────────────────────────────────────────────

    private static array $_ageCache = [];

    public static function resolveClientAgeMonths(Get $get, ?int $explicitVisitId = null): int
    {
        $visitId = $explicitVisitId ?? ($get('visit_id') ?: request()->query('visit'));
        if (!$visitId) return 9999;
        if (!isset(self::$_ageCache[$visitId])) {
            $dob = Visit::with('client')->find($visitId)?->client?->date_of_birth;
            self::$_ageCache[$visitId] = $dob ? (int) Carbon::parse($dob)->diffInMonths(now()) : 9999;
        }
        return self::$_ageCache[$visitId];
    }

    public static function resolveClientAge(Get $get, ?int $explicitVisitId = null): int
    {
        return (int) floor(self::resolveClientAgeMonths($get, $explicitVisitId) / 12);
    }

    public static function detectBandKey(int $ageMonths): string
    {
        return match (true) {
            $ageMonths < 7   => 'b0m',
            $ageMonths < 13  => 'b7m',
            $ageMonths < 36  => 'b1y',
            $ageMonths < 60  => 'b3y',
            $ageMonths < 84  => 'b5y',
            $ageMonths < 156 => 'b7y',
            $ageMonths < 216 => 'b13y',
            default          => 'b18y',
        };
    }

    public static function ageBandLabel(string $band): string
    {
        return match ($band) {
            'b0m'  => '0–6 Months',
            'b7m'  => '7–12 Months',
            'b1y'  => '1–2 Years',
            'b3y'  => '3–4 Years',
            'b5y'  => '5–6 Years',
            'b7y'  => '7–12 Years',
            'b13y' => '13–17 Years',
            'b18y' => 'Adults (18+)',
            default => '—',
        };
    }

    /**
     * Maps screening domain keys → canonical service_point slug + department name.
     * Used by afterCreate() to generate properly-routed AssessmentAutoReferrals.
     * selfcare and mob_fine both map to occupational_therapy — deduplication is
     * handled in afterCreate() by tracking already-created service_point slugs.
     */
    public static function screeningDomainServiceMap(): array
    {
        return [
            'hearing'   => ['service_point' => 'audiology',             'department' => 'Audiology / Hearing'],
            'vision'    => ['service_point' => 'vision',                 'department' => 'Vision Assessment'],
            'comm'      => ['service_point' => 'speech_language',        'department' => 'Speech & Language Therapy'],
            'mob_gross' => ['service_point' => 'physiotherapy',          'department' => 'Physiotherapy'],
            'mob_fine'  => ['service_point' => 'occupational_therapy',   'department' => 'Occupational Therapy'],
            'cognition' => ['service_point' => 'educational_assessment', 'department' => 'Educational Assessment / Psychology'],
            'social'    => ['service_point' => 'counselling',            'department' => 'Counselling / Psychosocial'],
            'selfcare'  => ['service_point' => 'occupational_therapy',   'department' => 'Occupational Therapy'],
            'work'      => ['service_point' => 'vocational',             'department' => 'Vocational Rehabilitation'],
        ];
    }

    /**
     * Maps Section I clinical category keys → department name(s) in the DB.
     * Used to filter service options when the intake officer selects categories.
     */
    private static function serviceCategoryToDeptNames(): array
    {
        return [
            'educational_assessment'  => ['Educational Assessment'],
            'psychological_assessment'=> ['Psychological Services'],
            'audiology'               => ['Audiology'],
            'physiotherapy'           => ['Physiotherapy'],
            'occupational_therapy'    => ['Occupational Therapy'],
            'speech_language'         => ['Speech & Language Therapy'],
            'vision'                  => ['Vision Services'],
            'counselling'             => ['Guidance & Counseling'],
            'assistive_technology'    => ['Assistive Technology'],
            'nutrition'               => [],   // no dedicated dept yet — shows no filter
        ];
    }

    /**
     * Build the service options array for Section I selects, filtered by the
     * clinical categories chosen in i_service_categories (when any are selected).
     * Returns id => "Name — Dept (KES X)" pairs.
     */
    public static function filteredServiceOptions(Get $get): array
    {
        $selectedCats = $get('i_service_categories') ?? [];

        $query = Service::with('department')->where('is_active', true);

        if (!empty($selectedCats) && !in_array('other', $selectedCats, true)) {
            $catDeptMap = self::serviceCategoryToDeptNames();
            $deptNames  = [];
            foreach ($selectedCats as $cat) {
                $deptNames = array_merge($deptNames, $catDeptMap[$cat] ?? []);
            }
            if (!empty($deptNames)) {
                $deptIds = Department::whereIn('name', array_unique($deptNames))->pluck('id');
                $query->whereIn('department_id', $deptIds);
            }
        }

        return $query->orderBy('name')
            ->get()
            ->mapWithKeys(fn($s) =>
                [$s->id => $s->name
                    . ($s->department ? ' — ' . $s->department->name : '')
                    . ' (KES ' . number_format($s->base_price ?? 0, 0) . ')']
            )
            ->toArray();
    }

    // ─── Allergen Options (filtered per category) ────────────────────────────────

    public static function allergenOptions(?string $category): array
    {
        return match ($category) {
            'drug' => [
                'penicillin'          => 'Penicillin',
                'amoxicillin'         => 'Amoxicillin',
                'cotrimoxazole'       => 'Cotrimoxazole',
                'sulfa_drugs'         => 'Sulfa drugs',
                'artemether_lumef'    => 'Artemether-Lumefantrine',
                'quinine'             => 'Quinine',
                'ibuprofen'           => 'Ibuprofen',
                'diclofenac'          => 'Diclofenac',
                'paracetamol'         => 'Paracetamol',
                'metronidazole'       => 'Metronidazole',
                'ranitidine'          => 'Ranitidine',
                'omeprazole'          => 'Omeprazole',
                'lidocaine'           => 'Lidocaine (local anaesthetic)',
                'other'               => 'Other (specify)',
            ],
            'food' => [
                'eggs'                => 'Eggs',
                'milk_dairy'          => 'Milk / Dairy',
                'fish'                => 'Fish',
                'groundnuts_peanuts'  => 'Groundnuts / Peanuts',
                'tree_nuts'           => 'Tree nuts',
                'maize'               => 'Maize',
                'wheat_gluten'        => 'Wheat / Gluten',
                'soy'                 => 'Soy',
                'sesame'              => 'Sesame',
                'mango'               => 'Mango',
                'banana'              => 'Banana',
                'tomatoes'            => 'Tomatoes',
                'shellfish'           => 'Shellfish',
                'other'               => 'Other (specify)',
            ],
            'environmental' => [
                'dust'                => 'Dust',
                'pollen'              => 'Pollen',
                'perfume'             => 'Perfume / Fragrance',
                'cigarette_smoke'     => 'Cigarette smoke',
                'mold'                => 'Mold / Mildew',
                'grass'               => 'Grass',
                'animal_dander'       => 'Animal dander',
                'other'               => 'Other (specify)',
            ],
            'insect' => [
                'bee_sting'           => 'Bee sting',
                'mosquito_bite'       => 'Mosquito bite',
                'ant_bite'            => 'Ant bite',
                'wasp_sting'          => 'Wasp sting',
                'other'               => 'Other (specify)',
            ],
            'latex' => [
                'gloves'              => 'Gloves',
                'balloons'            => 'Balloons',
                'rubber_items'        => 'Rubber items',
                'other'               => 'Other (specify)',
            ],
            'herbal' => [
                'local_herbs'         => 'Local herbs',
                'traditional_brew'    => 'Traditional brew',
                'other'               => 'Other (specify)',
            ],
            'chemical' => [
                'chlorine_pool'       => 'Chlorine (pool)',
                'detergents'          => 'Detergents',
                'disinfectants'       => 'Disinfectants',
                'bleach'              => 'Bleach',
                'other'               => 'Other (specify)',
            ],
            default => ['other' => 'Other (specify)'],
        };
    }

    // ─── AT Referral Routing Map ─────────────────────────────────────────────────
    // Returns ['service_point', 'department'] per AT category key.

    public static function atReferralMap(): array
    {
        return [
            'mobility'          => ['service_point' => 'physiotherapy',           'department' => 'Occupational Therapy / Physiotherapy'],
            'seating'           => ['service_point' => 'physiotherapy',           'department' => 'Occupational Therapy / Physiotherapy'],
            'orthotics'         => ['service_point' => 'orthotics_prosthetics',   'department' => 'Orthotics & Prosthetics'],
            'vision'            => ['service_point' => 'vision',                  'department' => 'Vision Assessment'],
            'hearing'           => ['service_point' => 'audiology',               'department' => 'Audiology / Hearing'],
            'communication_aac' => ['service_point' => 'speech_language',         'department' => 'Speech & Language Therapy'],
            'learning_access'   => ['service_point' => 'educational_assessment',  'department' => 'Functional Educational Assessment / OT'],
            'adl'               => ['service_point' => 'occupational_therapy',    'department' => 'Occupational Therapy'],
            'other'             => ['service_point' => 'occupational_therapy',    'department' => 'Occupational Therapy'],
        ];
    }

    // ─── AT Device Sub-types (filtered per category) ─────────────────────────────

    public static function atDeviceTypes(?string $category): array
    {
        return match ($category) {
            'mobility' => [
                'manual_wheelchair'  => 'Manual wheelchair',
                'powered_wheelchair' => 'Powered wheelchair',
                'walker_rollator'    => 'Walker / Rollator',
                'crutches'           => 'Crutches (axillary or forearm)',
                'cane'               => 'Cane / Walking stick',
                'standing_frame'     => 'Standing frame',
                'tricycle'           => 'Hand tricycle',
                'other'              => 'Other — specify',
            ],
            'seating' => [
                'custom_seat'        => 'Custom-moulded seat',
                'pressure_cushion'   => 'Pressure-relief cushion',
                'lateral_supports'   => 'Lateral supports / trunk pads',
                'headrest'           => 'Headrest',
                'lap_belt'           => 'Lap belt / harness',
                'tray'               => 'Wheelchair tray',
                'other'              => 'Other — specify',
            ],
            'orthotics' => [
                'afo'                => 'AFO (Ankle-Foot Orthosis)',
                'kafo'               => 'KAFO (Knee-Ankle-Foot Orthosis)',
                'spinal_brace'       => 'Spinal brace / TLSO',
                'upper_limb_splint'  => 'Upper limb splint',
                'prosthesis'         => 'Prosthesis (upper or lower limb)',
                'other'              => 'Other — specify',
            ],
            'vision' => [
                'prescription_glasses' => 'Prescription glasses',
                'low_vision_aids'    => 'Low-vision aids (magnifier, telescope)',
                'white_cane'         => 'White cane',
                'braille_slate'      => 'Braille slate & stylus',
                'braille_display'    => 'Braille display (electronic)',
                'other'              => 'Other — specify',
            ],
            'hearing' => [
                'bte_aid'            => 'Behind-the-ear (BTE) hearing aid',
                'ite_aid'            => 'In-the-ear (ITE) hearing aid',
                'cochlear_implant'   => 'Cochlear implant processor',
                'other'              => 'Other — specify',
            ],
            'communication_aac' => [
                'comm_board'         => 'Communication board / book',
                'sgd'                => 'Speech-generating device (SGD)',
                'tablet_aac_app'     => 'Tablet-based AAC app',
                'other'              => 'Other — specify',
            ],
            'learning_access' => [
                'pencil_grip'        => 'Pencil grip',
                'slant_board'        => 'Slant board',
                'keyguard'           => 'Keyguard',
                'alt_mouse_switch'   => 'Alternative mouse / switch',
                'other'              => 'Other — specify',
            ],
            'adl' => [
                'feeding_aids'       => 'Feeding aids (adapted spoon, dycem, sippy cup)',
                'dressing_aids'      => 'Dressing aids (button hook, sock aid)',
                'toilet_shower_chair'=> 'Toilet / shower chair',
                'other'              => 'Other — specify',
            ],
            default => ['other' => 'Other — specify'],
        };
    }

    // ─── Screening Question Bank ──────────────────────────────────────────────────

    public static function screeningQuestions(): array
    {
        // Each question: ['text' => '...', 'flag' => 'no'|'yes', 'referral' => '...']
        return [
            'b0m' => [
                'label' => '0–6 Months',
                'domains' => [
                    'hearing'   => ['label' => 'Hearing',             'referral' => 'Audiology / Hearing', 'questions' => [
                        'q1' => ['text' => 'Startles at loud sounds (door bang, hand clap)?',         'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Calms when you speak or sing?',                            'flag' => 'no'],
                    ]],
                    'vision'    => ['label' => 'Vision',              'referral' => 'Vision Assessment',  'questions' => [
                        'q1' => ['text' => 'Looks at your face or tracks a moving object?',           'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => "Both eyes move in the same direction together?",           'flag' => 'no', 'priority' => 'high'],
                    ]],
                    'comm'      => ['label' => 'Communication',       'referral' => 'Speech & Language',  'questions' => [
                        'q1' => ['text' => 'Makes soft sounds (aa, oo, gu)?',                         'flag' => 'no'],
                        'q2' => ['text' => 'Smiles when you talk or play?',                            'flag' => 'no'],
                    ]],
                    'mob_gross' => ['label' => 'Mobility – Gross',    'referral' => 'Physiotherapy',       'questions' => [
                        'q1' => ['text' => 'Lifts head briefly when lying on stomach?',               'flag' => 'no'],
                        'q2' => ['text' => 'Moves arms and legs when excited or awake?',              'flag' => 'no'],
                    ]],
                    'mob_fine'  => ['label' => 'Mobility – Fine',     'referral' => 'Occupational Therapy','questions' => [
                        'q1' => ['text' => 'Opens and closes hands?',                                 'flag' => 'no'],
                        'q2' => ['text' => 'Tries to grip your finger or a small object?',            'flag' => 'no'],
                    ]],
                    'cognition' => ['label' => 'Cognition / Learning','referral' => 'Educational Assessment','questions' => [
                        'q1' => ['text' => 'Looks toward a sudden sound or light?',                  'flag' => 'no'],
                        'q2' => ['text' => 'Shows interest in new faces or objects?',                 'flag' => 'no'],
                    ]],
                    'social'    => ['label' => 'Social / Behavioural','referral' => 'Counselling / Early Intervention','questions' => [
                        'q1' => ['text' => 'Smiles when a familiar person approaches?',              'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Calms when held, fed, or comforted?',                    'flag' => 'no'],
                    ]],
                ],
            ],
            'b7m' => [
                'label' => '7–12 Months',
                'domains' => [
                    'hearing'   => ['label' => 'Hearing',             'referral' => 'Audiology / Hearing', 'questions' => [
                        'q1' => ['text' => 'Turns head when name is called or hands clap?',          'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Notices household sounds (spoon, phone, dog)?',           'flag' => 'no'],
                        'q3' => ['text' => 'Stops crying / gets excited at familiar voice?',          'flag' => 'no'],
                    ]],
                    'vision'    => ['label' => 'Vision',              'referral' => 'Vision Assessment',  'questions' => [
                        'q1' => ['text' => 'Looks toward toy or person that moves nearby?',          'flag' => 'no'],
                        'q2' => ['text' => 'Reaches for objects they can see?',                       'flag' => 'no'],
                        'q3' => ['text' => 'Both eyes move together and look straight?',              'flag' => 'no', 'priority' => 'high'],
                    ]],
                    'comm'      => ['label' => 'Communication',       'referral' => 'Speech & Language',  'questions' => [
                        'q1' => ['text' => 'Makes repeated sounds (ba, ma, da)?',                    'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => "Responds to 'no' or 'bye-bye'?",                          'flag' => 'no'],
                        'q3' => ['text' => 'Uses different sounds or tones when "talking"?',          'flag' => 'no'],
                    ]],
                    'mob_gross' => ['label' => 'Mobility – Gross',    'referral' => 'Physiotherapy',       'questions' => [
                        'q1' => ['text' => 'Sits without support?',                                  'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Crawls or moves by rolling/shuffling?',                  'flag' => 'no'],
                        'q3' => ['text' => 'Stands while holding furniture or your hands?',          'flag' => 'no'],
                    ]],
                    'mob_fine'  => ['label' => 'Mobility – Fine',     'referral' => 'Occupational Therapy','questions' => [
                        'q1' => ['text' => 'Picks up small items (beans, buttons) with pincer grip?','flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Transfers a toy from one hand to the other?',            'flag' => 'no'],
                        'q3' => ['text' => 'Bangs two objects together?',                             'flag' => 'no'],
                    ]],
                    'cognition' => ['label' => 'Cognition / Learning','referral' => 'Educational / Psychology','questions' => [
                        'q1' => ['text' => 'Looks for a toy that falls or hides under cloth?',       'flag' => 'no'],
                        'q2' => ['text' => 'Shows curiosity about new objects or faces?',             'flag' => 'no'],
                        'q3' => ['text' => 'Recognizes familiar people (mother, sibling)?',           'flag' => 'no'],
                    ]],
                    'social'    => ['label' => 'Social / Behavioural','referral' => 'Counselling / Early Intervention','questions' => [
                        'q1' => ['text' => 'Enjoys hiding/surprise games (peek-a-boo)?',             'flag' => 'no'],
                        'q2' => ['text' => 'Shows fear of strangers or clings to familiar people?',  'flag' => 'no'],
                        'q3' => ['text' => 'Shows interest when others laugh or talk nearby?',        'flag' => 'no'],
                    ]],
                ],
            ],
            'b1y' => [
                'label' => '1–2 Years',
                'domains' => [
                    'hearing'   => ['label' => 'Hearing',             'referral' => 'Audiology / Hearing', 'questions' => [
                        'q1' => ['text' => 'Responds when name called from another room?',           'flag' => 'no'],
                        'q2' => ['text' => 'Enjoys listening to songs or stories?',                   'flag' => 'no'],
                        'q3' => ['text' => 'Points to items when named (cup, ball, shoe)?',           'flag' => 'no'],
                    ]],
                    'vision'    => ['label' => 'Vision',              'referral' => 'Vision Assessment',  'questions' => [
                        'q1' => ['text' => 'Notices small items on the ground (coin, crumb)?',       'flag' => 'no'],
                        'q2' => ['text' => 'Bumps into furniture or trips even in familiar places?', 'flag' => 'yes'],
                        'q3' => ['text' => 'Eyes appear straight and move together most of the time?','flag'=> 'no'],
                    ]],
                    'comm'      => ['label' => 'Communication',       'referral' => 'Speech & Language',  'questions' => [
                        'q1' => ['text' => 'Uses 10–20 single words (mama, bye, milk)?',             'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Tries to combine two words (mama come)?',                'flag' => 'no'],
                        'q3' => ['text' => 'Follows simple instructions (sit down, come here)?',     'flag' => 'no'],
                    ]],
                    'mob_gross' => ['label' => 'Mobility – Gross',    'referral' => 'Physiotherapy',       'questions' => [
                        'q1' => ['text' => 'Walks alone without holding furniture?',                 'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Bends down to pick a toy and stands again?',             'flag' => 'no'],
                        'q3' => ['text' => 'Climbs onto a low stool or couch?',                      'flag' => 'no'],
                    ]],
                    'mob_fine'  => ['label' => 'Mobility – Fine',     'referral' => 'Occupational Therapy','questions' => [
                        'q1' => ['text' => 'Feeds self with hands or a spoon?',                      'flag' => 'no'],
                        'q2' => ['text' => 'Stacks two or more blocks or cups?',                     'flag' => 'no'],
                        'q3' => ['text' => 'Turns book pages or picks small objects with fingers?',  'flag' => 'no'],
                    ]],
                    'cognition' => ['label' => 'Cognition / Learning','referral' => 'Educational / Psychology','questions' => [
                        'q1' => ['text' => 'Copies simple actions (sweeping, stirring)?',            'flag' => 'no'],
                        'q2' => ['text' => 'Recognizes familiar routines (meal, bath, nap)?',        'flag' => 'no'],
                        'q3' => ['text' => 'Tries to use objects correctly (comb, cup, spoon)?',     'flag' => 'no'],
                    ]],
                    'social'    => ['label' => 'Social / Behavioural','referral' => 'Counselling / Early Intervention','questions' => [
                        'q1' => ['text' => 'Plays beside other children or shows interest?',         'flag' => 'no'],
                        'q2' => ['text' => 'Copies actions (waving, clapping, dancing)?',            'flag' => 'no'],
                        'q3' => ['text' => 'Shows affection to caregivers (hugs, smiles)?',          'flag' => 'no', 'priority' => 'high'],
                    ]],
                ],
            ],
            'b3y' => [
                'label' => '3–4 Years',
                'domains' => [
                    'hearing'   => ['label' => 'Hearing',             'referral' => 'Audiology / Hearing', 'questions' => [
                        'q1' => ['text' => 'Responds when called from another room or in a group?',  'flag' => 'no'],
                        'q2' => ['text' => 'Follows short stories or instructions?',                  'flag' => 'no'],
                        'q3' => ['text' => "Answers simple questions (name, 'where is mummy')?",     'flag' => 'no'],
                    ]],
                    'vision'    => ['label' => 'Vision',              'referral' => 'Vision Assessment',  'questions' => [
                        'q1' => ['text' => 'Recognizes people or objects from a distance?',          'flag' => 'no'],
                        'q2' => ['text' => 'Avoids bright light or squints often when looking far?', 'flag' => 'yes'],
                        'q3' => ['text' => 'Trips over obstacles or misses steps when running?',     'flag' => 'yes'],
                    ]],
                    'comm'      => ['label' => 'Communication',       'referral' => 'Speech & Language',  'questions' => [
                        'q1' => ['text' => "Speaks in 3–4 word sentences ('I want water')?",         'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Understood by familiar adults most of the time?',        'flag' => 'no'],
                        'q3' => ['text' => "Asks questions like 'what' or 'where'?",                 'flag' => 'no'],
                    ]],
                    'mob_gross' => ['label' => 'Mobility – Gross',    'referral' => 'Physiotherapy',       'questions' => [
                        'q1' => ['text' => 'Runs, climbs small steps, and kicks a ball?',            'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Jumps with both feet or stands on one foot briefly?',    'flag' => 'no'],
                        'q3' => ['text' => 'Moves confidently around obstacles when playing?',       'flag' => 'no'],
                    ]],
                    'mob_fine'  => ['label' => 'Mobility – Fine',     'referral' => 'Occupational Therapy','questions' => [
                        'q1' => ['text' => 'Draws simple shapes (line, circle) or imitates them?',  'flag' => 'no'],
                        'q2' => ['text' => 'Holds a crayon/pencil between fingers (not fist)?',     'flag' => 'no'],
                        'q3' => ['text' => 'Feeds self with minimal spilling?',                      'flag' => 'no'],
                    ]],
                    'cognition' => ['label' => 'Cognition / Learning','referral' => 'Educational / Psychology','questions' => [
                        'q1' => ['text' => 'Names familiar objects or animals when asked?',          'flag' => 'no'],
                        'q2' => ['text' => 'Matches similar objects by color or shape?',             'flag' => 'no'],
                        'q3' => ['text' => 'Remembers simple routines (prayer, song, daily)?',       'flag' => 'no'],
                    ]],
                    'social'    => ['label' => 'Social / Behavioural','referral' => 'Counselling / Psychosocial','questions' => [
                        'q1' => ['text' => 'Plays with other children, sharing or taking turns?',    'flag' => 'no'],
                        'q2' => ['text' => 'Copies adult actions (sweeping, pretend cooking)?',      'flag' => 'no'],
                        'q3' => ['text' => 'Shows emotions (happy, sad, angry) appropriately?',      'flag' => 'no'],
                    ]],
                ],
            ],
            'b5y' => [
                'label' => '5–6 Years',
                'domains' => [
                    'hearing'   => ['label' => 'Hearing',             'referral' => 'Audiology / Hearing', 'questions' => [
                        'q1' => ['text' => 'Hears well in a group without asking for repetition?',   'flag' => 'no'],
                        'q2' => ['text' => 'Responds when called softly from behind or another room?','flag'=> 'no'],
                        'q3' => ['text' => 'Enjoys stories, songs, conversations and responds?',     'flag' => 'no'],
                    ]],
                    'vision'    => ['label' => 'Vision',              'referral' => 'Vision Assessment',  'questions' => [
                        'q1' => ['text' => 'Recognizes friends/teachers from across the playground?','flag' => 'no'],
                        'q2' => ['text' => 'Holds books or items too close to the face?',            'flag' => 'yes', 'priority' => 'high'],
                        'q3' => ['text' => 'Frequent eye rubbing, blinking, or squinting?',          'flag' => 'yes', 'priority' => 'high'],
                    ]],
                    'comm'      => ['label' => 'Communication',       'referral' => 'Speech & Language',  'questions' => [
                        'q1' => ['text' => 'Uses full sentences and takes part in conversations?',    'flag' => 'no'],
                        'q2' => ['text' => 'Understood by unfamiliar adults most of the time?',      'flag' => 'no', 'priority' => 'high'],
                        'q3' => ['text' => 'Tells simple stories or describes recent events?',        'flag' => 'no'],
                    ]],
                    'mob_gross' => ['label' => 'Mobility – Gross',    'referral' => 'Physiotherapy',       'questions' => [
                        'q1' => ['text' => 'Runs, hops, and skips without frequent falling?',        'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Climbs stairs alternating feet without holding rail?',   'flag' => 'no'],
                        'q3' => ['text' => 'Throws and catches a medium-sized ball?',                'flag' => 'no'],
                    ]],
                    'mob_fine'  => ['label' => 'Mobility – Fine',     'referral' => 'Occupational Therapy','questions' => [
                        'q1' => ['text' => 'Draws a person or object with basic parts?',             'flag' => 'no'],
                        'q2' => ['text' => 'Uses scissors or holds a pencil correctly?',             'flag' => 'no'],
                        'q3' => ['text' => 'Buttons clothes, zips a bag, or ties shoelaces?',        'flag' => 'no'],
                    ]],
                    'cognition' => ['label' => 'Cognition / Learning','referral' => 'Educational / Psychology','questions' => [
                        'q1' => ['text' => 'Names basic colors, numbers (1–10), and shapes?',        'flag' => 'no'],
                        'q2' => ['text' => 'Matches or sorts items by color, size, or function?',    'flag' => 'no'],
                        'q3' => ['text' => 'Recalls simple stories or routines from school/home?',   'flag' => 'no'],
                    ]],
                    'social'    => ['label' => 'Social / Behavioural','referral' => 'Counselling / Psychosocial','questions' => [
                        'q1' => ['text' => 'Takes turns and shares toys with others?',               'flag' => 'no'],
                        'q2' => ['text' => 'Manages self-care (dressing, toileting) independently?', 'flag' => 'no'],
                        'q3' => ['text' => 'Shows interest in playing with friends or group games?', 'flag' => 'no'],
                    ]],
                ],
            ],
            'b7y' => [
                'label' => '7–12 Years',
                'domains' => [
                    'hearing'   => ['label' => 'Hearing',             'referral' => 'Audiology / Hearing', 'questions' => [
                        'q1' => ['text' => 'Hears clearly in class without asking for repetition?',  'flag' => 'no'],
                        'q2' => ['text' => 'Turns TV/phone volume higher than others?',              'flag' => 'yes'],
                        'q3' => ['text' => 'Misses instructions or appears inattentive though alert?','flag'=> 'yes'],
                    ]],
                    'vision'    => ['label' => 'Vision',              'referral' => 'Vision Assessment',  'questions' => [
                        'q1' => ['text' => 'Struggles to see the blackboard from a normal distance?','flag' => 'yes', 'priority' => 'high'],
                        'q2' => ['text' => 'Brings books very close when reading or writing?',       'flag' => 'yes'],
                        'q3' => ['text' => 'Frequent headaches or eye pain after reading?',          'flag' => 'yes'],
                    ]],
                    'comm'      => ['label' => 'Communication',       'referral' => 'Speech & Language',  'questions' => [
                        'q1' => ['text' => 'Speaks clearly with age-appropriate grammar?',           'flag' => 'no'],
                        'q2' => ['text' => "Understands teacher's explanations and responds?",       'flag' => 'no'],
                        'q3' => ['text' => 'Participates in peer conversations confidently?',        'flag' => 'no'],
                    ]],
                    'mob_gross' => ['label' => 'Mobility – Gross',    'referral' => 'Physiotherapy',       'questions' => [
                        'q1' => ['text' => 'Runs, jumps, and participates in games without difficulty?','flag'=> 'no'],
                        'q2' => ['text' => 'Has limp, joint pain, or stiffness limiting play?',     'flag' => 'yes', 'priority' => 'high'],
                        'q3' => ['text' => 'Tires easily or struggles to climb stairs?',             'flag' => 'yes'],
                    ]],
                    'mob_fine'  => ['label' => 'Mobility – Fine',     'referral' => 'Occupational Therapy','questions' => [
                        'q1' => ['text' => 'Writes legibly for their grade level?',                  'flag' => 'no'],
                        'q2' => ['text' => 'No clear hand preference by 8 years?',                  'flag' => 'yes'],
                        'q3' => ['text' => 'Handles daily tools (pencil, cup, spoon) without dropping?','flag'=> 'no'],
                    ]],
                    'cognition' => ['label' => 'Cognition / Learning','referral' => 'Educational / Psychology','questions' => [
                        'q1' => ['text' => 'Reads and understands short paragraphs for their class?','flag' => 'no'],
                        'q2' => ['text' => 'Remembers instructions and completes homework on time?', 'flag' => 'no'],
                        'q3' => ['text' => 'Has trouble with number concepts, spelling, or writing?','flag' => 'yes'],
                    ]],
                    'social'    => ['label' => 'Social / Behavioural','referral' => 'Counselling / Mental Health','questions' => [
                        'q1' => ['text' => 'Makes and keeps friends easily?',                        'flag' => 'no'],
                        'q2' => ['text' => 'Shows sudden mood changes, aggression, or withdrawal?',  'flag' => 'yes', 'priority' => 'high'],
                        'q3' => ['text' => 'Follows school and home rules appropriately for age?',   'flag' => 'no'],
                    ]],
                    'selfcare'  => ['label' => 'Self-care / Daily Living','referral' => 'Occupational Therapy','questions' => [
                        'q1' => ['text' => 'Bathes, dresses, and feeds independently?',             'flag' => 'no'],
                        'q2' => ['text' => 'Manages toileting and hygiene independently?',           'flag' => 'no'],
                        'q3' => ['text' => 'Organizes personal items (books, bag) without help?',   'flag' => 'no'],
                    ]],
                ],
            ],
            'b13y' => [
                'label' => '13–17 Years',
                'domains' => [
                    'hearing'   => ['label' => 'Hearing',             'referral' => 'Audiology / Hearing', 'questions' => [
                        'q1' => ['text' => 'Hears clearly during conversations or group activities?','flag' => 'no'],
                        'q2' => ['text' => 'Often misunderstands or does not respond when spoken to?','flag'=> 'yes'],
                        'q3' => ['text' => 'Had ear pain, discharge, or ringing (buzzing) recently?','flag' => 'yes'],
                    ]],
                    'vision'    => ['label' => 'Vision',              'referral' => 'Vision Assessment',  'questions' => [
                        'q1' => ['text' => 'Has trouble seeing, reading, or identifying faces from normal distance?','flag'=>'yes','priority'=>'high'],
                        'q2' => ['text' => 'Frequent headaches, eye strain, or squinting when reading?','flag'=> 'yes'],
                        'q3' => ['text' => 'Bumps into objects, trips often, or sits very close to screen?','flag'=>'yes'],
                    ]],
                    'comm'      => ['label' => 'Communication',       'referral' => 'Speech & Language',  'questions' => [
                        'q1' => ['text' => 'Expresses self clearly and confidently?',                'flag' => 'no'],
                        'q2' => ['text' => 'Understands normal conversation and responds appropriately?','flag'=> 'no'],
                        'q3' => ['text' => 'Avoids speaking or shows anxiety when asked to talk?',   'flag' => 'yes'],
                    ]],
                    'mob_gross' => ['label' => 'Mobility – Gross',    'referral' => 'Physiotherapy',       'questions' => [
                        'q1' => ['text' => 'Walks, runs, uses stairs without support or visible strain?','flag'=> 'no'],
                        'q2' => ['text' => 'Reports pain, stiffness, or fatigue when moving?',       'flag' => 'yes', 'priority' => 'high'],
                        'q3' => ['text' => 'Uses or needs mobility aid (crutches, wheelchair)?',     'flag' => 'yes'],
                    ]],
                    'mob_fine'  => ['label' => 'Mobility – Fine',     'referral' => 'Occupational Therapy','questions' => [
                        'q1' => ['text' => 'Writes, draws, or handles small tools easily?',          'flag' => 'no'],
                        'q2' => ['text' => 'Performs self-care (washing, dressing) without assistance?','flag'=> 'no'],
                        'q3' => ['text' => 'Drops things frequently or struggles with hand control?','flag' => 'yes'],
                    ]],
                    'cognition' => ['label' => 'Cognition / Learning','referral' => 'Educational / Psychology','questions' => [
                        'q1' => ['text' => 'Remembers new information, instructions, or routines easily?','flag'=> 'no'],
                        'q2' => ['text' => 'Finds it difficult to stay focused during tasks?',       'flag' => 'yes'],
                        'q3' => ['text' => 'Sudden drop in performance or loss of interest in learning?','flag'=>'yes', 'priority'=>'high'],
                    ]],
                    'social'    => ['label' => 'Social / Behavioural','referral' => 'Counselling / Mental Health','questions' => [
                        'q1' => ['text' => 'Makes and maintains friendships or positive relationships?','flag'=> 'no'],
                        'q2' => ['text' => 'Recent changes in behaviour (isolation, anger, risk-taking)?','flag'=>'yes', 'priority'=>'high'],
                        'q3' => ['text' => 'Anyone expressed concern about their behaviour?',        'flag' => 'yes'],
                    ]],
                    'selfcare'  => ['label' => 'Self-care & Vocational Readiness','referral' => 'OT / Vocational Guidance','questions' => [
                        'q1' => ['text' => 'Manages personal care and home responsibilities independently?','flag'=>'no'],
                        'q2' => ['text' => 'Manages own time, money, or belongings responsibly?',    'flag' => 'no'],
                        'q3' => ['text' => 'Interested in learning practical skills (tailoring, ICT)?','flag'=> 'no'],
                    ]],
                ],
            ],
            'b18y' => [
                'label' => 'Adults (18+)',
                'domains' => [
                    'hearing'   => ['label' => 'Hearing',             'referral' => 'Audiology / Hearing', 'questions' => [
                        'q1' => ['text' => 'Hears clearly when people talk in a normal voice?',      'flag' => 'no'],
                        'q2' => ['text' => 'Often asks people to repeat what they say?',             'flag' => 'yes'],
                        'q3' => ['text' => 'Has ear pain, discharge, or ringing sounds?',            'flag' => 'yes'],
                    ]],
                    'vision'    => ['label' => 'Vision',              'referral' => 'Vision Assessment',  'questions' => [
                        'q1' => ['text' => 'Has trouble seeing faces or reading text (even with glasses)?','flag'=>'yes','priority'=>'high'],
                        'q2' => ['text' => 'Eyes hurt, itchy, or headaches when using them?',        'flag' => 'yes'],
                        'q3' => ['text' => 'Moves close to objects or the TV to see clearly?',       'flag' => 'yes'],
                    ]],
                    'comm'      => ['label' => 'Communication',       'referral' => 'Speech & Language',  'questions' => [
                        'q1' => ['text' => 'Finds it easy to express self and make others understand?','flag'=> 'no'],
                        'q2' => ['text' => 'Noticed change in how they speak (slurred, unclear)?',   'flag' => 'yes', 'priority' => 'high'],
                        'q3' => ['text' => 'Avoids talking with others because it is hard to communicate?','flag'=>'yes'],
                    ]],
                    'mob_gross' => ['label' => 'Mobility – Gross',    'referral' => 'Physiotherapy',       'questions' => [
                        'q1' => ['text' => 'Can walk and move around safely without support?',       'flag' => 'no'],
                        'q2' => ['text' => 'Often falls, feels weak, or loses balance when walking?','flag' => 'yes', 'priority' => 'high'],
                        'q3' => ['text' => 'Uses wheelchair, crutches, or walking stick?',           'flag' => 'yes'],
                    ]],
                    'mob_fine'  => ['label' => 'Mobility – Fine',     'referral' => 'Occupational Therapy','questions' => [
                        'q1' => ['text' => 'Can hold small items (coins, pens, keys) easily?',       'flag' => 'no'],
                        'q2' => ['text' => 'Hands shake, feel weak, or numb?',                       'flag' => 'yes'],
                        'q3' => ['text' => 'Can dress, eat, and wash self without help?',             'flag' => 'no'],
                    ]],
                    'cognition' => ['label' => 'Cognition / Memory',  'referral' => 'Psychology / Counselling','questions' => [
                        'q1' => ['text' => 'Sometimes forgets appointments or tasks?',               'flag' => 'yes'],
                        'q2' => ['text' => 'Has trouble focusing or making decisions?',               'flag' => 'yes'],
                        'q3' => ['text' => 'Noticed changes in how they think or remember recently?','flag' => 'yes', 'priority' => 'high'],
                    ]],
                    'social'    => ['label' => 'Social / Emotional',  'referral' => 'Counselling / Mental Health','questions' => [
                        'q1' => ['text' => 'Enjoys spending time with family and others?',           'flag' => 'no'],
                        'q2' => ['text' => 'Often feels sad, anxious, or easily angered?',           'flag' => 'yes'],
                        'q3' => ['text' => 'Has been hurt, neglected, or felt unsafe at home/work?', 'flag' => 'yes', 'priority' => 'high'],
                    ]],
                    'selfcare'  => ['label' => 'Self-care & Daily Living','referral' => 'OT / Support Services','questions' => [
                        'q1' => ['text' => 'Can take care of self (bathing, dressing, meals)?',      'flag' => 'no', 'priority' => 'high'],
                        'q2' => ['text' => 'Needs help managing money, transport, or medication?',   'flag' => 'yes'],
                        'q3' => ['text' => 'Stopped doing activities they used to do easily?',       'flag' => 'yes'],
                    ]],
                    'work'      => ['label' => 'Work / Study / Productivity','referral' => 'Vocational Rehabilitation','questions' => [
                        'q1' => ['text' => 'Currently working, studying, or doing income activities?',                    'flag' => 'no'],
                        'q2' => ['text' => 'Finds work or chores difficult due to a health or physical problem?',         'flag' => 'yes'],
                        'q3' => ['text' => 'Would like support to learn new skills or start working again?',              'flag' => 'yes'],
                    ]],
                ],
            ],
        ];
    }

    // ─── Build one age-band screening section ────────────────────────────────────

    private static function buildBandSection(string $bandKey, array $band): Forms\Components\Section
    {
        $bandKeyLocal = $bandKey; // capture for closures

        $domainSections = [];
        foreach ($band['domains'] as $domainKey => $domain) {
            $fields = [];
            foreach ($domain['questions'] as $qKey => $q) {
                $isRedFlag = ($q['priority'] ?? 'routine') === 'high';
                $label = $isRedFlag
                    ? '<span style="color:#dc2626;font-weight:600;">⚠ ' . htmlspecialchars($q['text']) . '</span>'
                    : htmlspecialchars($q['text']);

                $radio = Forms\Components\Radio::make("g_{$bandKeyLocal}_{$domainKey}_{$qKey}")
                    ->label(new HtmlString($label))
                    ->options(['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Unsure'])
                    ->inline()
                    ->inlineLabel(false)
                    ->columnSpanFull();

                if ($isRedFlag) {
                    $flagAnswer = $q['flag'];
                    $radio = $radio->helperText('Red flag — immediate referral if answer is "' . strtoupper($flagAnswer) . '"');
                }

                $fields[] = $radio;
            }
            $fields[] = Forms\Components\TextInput::make("g_{$bandKeyLocal}_{$domainKey}_notes")
                ->label('Notes / Observations')
                ->placeholder('Optional…')
                ->columnSpanFull();

            $domainSections[] = Forms\Components\Section::make($domain['label'])
                ->description('→ ' . $domain['referral'])
                ->compact()
                ->collapsible()
                ->columns(1)
                ->schema($fields);
        }

        return Forms\Components\Section::make("Functional Screening — {$band['label']}")
            ->icon('heroicon-o-chart-bar')
            ->collapsible()
            ->visible(function (Get $get) use ($bandKeyLocal) {
                $m = self::resolveClientAgeMonths($get);
                return self::detectBandKey($m) === $bandKeyLocal;
            })
            ->schema([
                Forms\Components\Placeholder::make("g_{$bandKeyLocal}_hint")
                    ->hiddenLabel()
                    ->content(new HtmlString(
                        '<div style="padding:8px 14px;background:#dbeafe;border-radius:6px;border-left:4px solid #3b82f6;color:#1e40af;font-size:13px;">'
                        . 'Answer Yes / No / Unsure for each item. '
                        . '<span style="color:#dc2626;font-weight:600;">⚠ Red questions</span> are immediate-referral flags — '
                        . 'a flagged answer generates a <b>high-priority</b> referral on save.'
                        . '</div>'
                    ))
                    ->columnSpanFull(),
                Forms\Components\Grid::make(2)->schema($domainSections),
                Forms\Components\Textarea::make('func_overall_summary')
                    ->label('Functional Screening — Overall Summary & Observations')
                    ->placeholder('Key observations, strengths, priority areas…')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    // ─── Section Schema Methods ──────────────────────────────────────────────────

    public static function sectionBSchema(?int $visitId = null): array
    {
        return [
            Forms\Components\Radio::make('verification_mode')
                ->label('Client Type at This Visit')
                ->options([
                    'new_client'       => 'New Client',
                    'old_new_client'   => 'Old-New (satellite/outreach)',
                    'returning_client' => 'Returning Client',
                ])
                ->default(function () use ($visitId) {
                    $ct = Visit::with('client')->find($visitId)?->client?->client_type ?? 'new';
                    return match ($ct) { 'returning' => 'returning_client', 'old_new' => 'old_new_client', default => 'new_client' };
                })
                ->required()
                ->columnSpanFull(),

            Forms\Components\TextInput::make('b_national_id')
                ->label('National ID / Passport')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->national_id)
                ->prefixIcon('heroicon-o-identification'),

            Forms\Components\TextInput::make('b_birth_certificate')
                ->label('Birth Certificate No.')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->birth_certificate_number)
                ->placeholder('e.g. BC/2015/XXXX'),

            Forms\Components\TextInput::make('b_phone_primary')
                ->label('Primary Phone')
                ->tel()
                ->prefixIcon('heroicon-o-phone')
                ->placeholder('+254 7XX XXX XXX')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->phone_primary)
                ->helperText('Updates client record on save'),

            Forms\Components\TextInput::make('b_phone_secondary')
                ->label('Alternate Phone')
                ->tel()
                ->prefixIcon('heroicon-o-phone')
                ->placeholder('+254 7XX XXX XXX')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->phone_secondary),

            Forms\Components\Select::make('b_preferred_communication')
                ->label('Preferred Communication')
                ->options(['sms' => 'SMS', 'phone_call' => 'Phone Call', 'email' => 'Email', 'whatsapp' => 'WhatsApp'])
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->preferred_communication ?? 'sms')
                ->native(false)
                ->live(),

            Forms\Components\TextInput::make('b_email')
                ->label('Email Address')
                ->email()
                ->prefixIcon('heroicon-o-envelope')
                ->placeholder('client@example.com')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->email)
                ->visible(fn(Get $get) => $get('b_preferred_communication') === 'email'),

            Forms\Components\Toggle::make('b_consent_to_sms')
                ->label('Consents to SMS Reminders')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->consent_to_sms ?? true)
                ->helperText('Required for appointment reminders'),

            Forms\Components\TextInput::make('b_sha_number')
                ->label('SHA Number')
                ->prefixIcon('heroicon-o-shield-check')
                ->placeholder('SHA-XXXXXXXX')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->sha_number),

            Forms\Components\TextInput::make('b_ncpwd_number')
                ->label('NCPWD Number')
                ->prefixIcon('heroicon-o-identification')
                ->placeholder('NCPWD-XXXXXX')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->ncpwd_number),

            // Address
            Forms\Components\Select::make('b_county_id')
                ->label('County')
                ->options(fn() => County::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn(Set $set) => $set('b_sub_county_id', null))
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->county_id)
                ->native(false),

            Forms\Components\Select::make('b_sub_county_id')
                ->label('Sub-County')
                ->options(fn(Get $get) => $get('b_county_id')
                    ? SubCounty::where('county_id', $get('b_county_id'))->orderBy('name')->pluck('name', 'id')
                    : [])
                ->searchable()
                ->live()
                ->afterStateUpdated(fn(Set $set) => $set('b_ward_id', null))
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->sub_county_id)
                ->native(false),

            Forms\Components\Select::make('b_ward_id')
                ->label('Ward')
                ->options(fn(Get $get) => $get('b_sub_county_id')
                    ? Ward::where('sub_county_id', $get('b_sub_county_id'))->orderBy('name')->pluck('name', 'id')
                    : [])
                ->searchable()
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->ward_id)
                ->native(false),

            Forms\Components\TextInput::make('b_primary_address')
                ->label('Primary Address')
                ->placeholder('Estate, street, house number…')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->primary_address)
                ->columnSpan(2),

            Forms\Components\TextInput::make('b_landmark')
                ->label('Landmark / Directions')
                ->placeholder('Near church, behind market…')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->landmark),

            Forms\Components\Textarea::make('verification_notes')
                ->label('Verification Notes')
                ->placeholder('Discrepancies or notes about identification…')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    public static function sectionCSchema(?int $visitId = null): array
    {
        return [
            Forms\Components\Toggle::make('dis_is_disability_known')
                ->label('Disability status is known / confirmed')
                ->live()
                ->columnSpanFull(),

            Forms\Components\CheckboxList::make('dis_disability_categories')
                ->label('Disability Categories (tick all that apply)')
                ->options([
                    'hearing'      => 'Hearing Impairment',
                    'visual'       => 'Visual Impairment',
                    'physical'     => 'Physical / Mobility',
                    'intellectual' => 'Intellectual Disability',
                    'autism'       => 'Autism Spectrum',
                    'multiple'     => 'Multiple Disabilities',
                    'other'        => 'Other (specify in notes)',
                ])
                ->columns(3)
                ->visible(fn(Get $get) => $get('dis_is_disability_known'))
                ->columnSpanFull(),

            Forms\Components\Select::make('dis_onset')
                ->label('Onset')
                ->options(['congenital' => 'Congenital (from birth)', 'acquired' => 'Acquired', 'unknown' => 'Unknown'])
                ->native(false)
                ->visible(fn(Get $get) => $get('dis_is_disability_known')),

            Forms\Components\Select::make('dis_level_of_functioning')
                ->label('Level of Functioning')
                ->options(['mild' => 'Mild', 'moderate' => 'Moderate', 'severe' => 'Severe', 'profound' => 'Profound'])
                ->native(false)
                ->visible(fn(Get $get) => $get('dis_is_disability_known')),

            Forms\Components\Radio::make('dis_ncpwd_registered')
                ->label('NCPWD Registered?')
                ->options(['yes' => 'Yes', 'no' => 'No', 'unknown' => 'Unknown'])
                ->inline()
                ->inlineLabel(false)
                ->live()
                ->visible(fn(Get $get) => $get('dis_is_disability_known'))
                ->columnSpanFull(),

            Forms\Components\TextInput::make('dis_ncpwd_number')
                ->label('NCPWD Number')
                ->placeholder('NCPWD-XXXXXX')
                ->prefixIcon('heroicon-o-identification')
                ->helperText('Format: NCPWD-XXXXXX — system will validate checksum when available')
                ->default(fn() => Visit::with('client')->find($visitId)?->client?->ncpwd_number)
                ->visible(fn(Get $get) => $get('dis_is_disability_known') && $get('dis_ncpwd_registered') === 'yes')
                ->required(fn(Get $get) => $get('dis_ncpwd_registered') === 'yes'),

            Forms\Components\Select::make('dis_ncpwd_verification_status')
                ->label('NCPWD Card Status')
                ->options([
                    'seen'     => 'Seen (physical card inspected)',
                    'uploaded' => 'Uploaded (copy attached below)',
                    'verified' => 'Verified (system confirmed)',
                ])
                ->native(false)
                ->helperText('Select the current verification status of the NCPWD card')
                ->visible(fn(Get $get) => $get('dis_is_disability_known') && $get('dis_ncpwd_registered') === 'yes')
                ->required(fn(Get $get) => $get('dis_ncpwd_registered') === 'yes'),

            Forms\Components\FileUpload::make('dis_evidence_files')
                ->label('Evidence Attachments (ID / Birth Certificate / NCPWD Card)')
                ->helperText('Upload copies of relevant identification and disability documents')
                ->multiple()
                ->disk('public')
                ->directory('disability-evidence')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                ->maxSize(5120)
                ->maxFiles(5)
                ->downloadable()
                ->previewable(false)
                ->visible(fn(Get $get) => $get('dis_is_disability_known') && $get('dis_ncpwd_registered') === 'yes')
                ->columnSpanFull(),

            Forms\Components\Textarea::make('dis_disability_notes')
                ->label('Disability Notes')
                ->rows(2)
                ->visible(fn(Get $get) => $get('dis_is_disability_known'))
                ->columnSpanFull(),
        ];
    }

    public static function sectionDSchema(?int $visitId = null): array
    {
        return [
            Forms\Components\Select::make('socio_marital_status')
                ->label('Marital Status')
                ->options(['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed', 'other' => 'Other (specify)'])
                ->native(false)
                ->live()
                ->visible(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) >= 18; }),

            Forms\Components\TextInput::make('socio_marital_other')
                ->label('Marital Status — Specify')
                ->placeholder('e.g. Cohabiting, Separated…')
                ->visible(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) >= 18 && $get('socio_marital_status') === 'other'; })
                ->required(fn(Get $get) => $get('socio_marital_status') === 'other'),

            Forms\Components\Select::make('socio_living_arrangement')
                ->label('Living Arrangement')
                ->options(['with_family' => 'With Family', 'institution' => 'Institution / Care Home', 'alone' => 'Alone', 'other' => 'Other (specify)'])
                ->native(false)
                ->live(),

            Forms\Components\TextInput::make('socio_living_other')
                ->label('Living Arrangement — Specify')
                ->placeholder('Describe arrangement…')
                ->visible(fn(Get $get) => $get('socio_living_arrangement') === 'other')
                ->required(fn(Get $get) => $get('socio_living_arrangement') === 'other'),

            Forms\Components\TextInput::make('socio_household_size')
                ->label('Household Size')
                ->numeric()
                ->minValue(1)
                ->maxValue(30)
                ->suffix('people'),

            Forms\Components\Select::make('socio_primary_caregiver')
                ->label('Primary Caregiver')
                ->options(['mother' => 'Mother', 'father' => 'Father', 'guardian' => 'Guardian', 'relative' => 'Relative', 'sponsor' => 'Sponsor / Institution', 'other' => 'Other (specify)'])
                ->native(false)
                ->live()
                ->visible(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) < 18; }),

            Forms\Components\TextInput::make('socio_caregiver_other')
                ->label('Primary Caregiver — Specify')
                ->placeholder('e.g. Step-parent, Social worker…')
                ->visible(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) < 18 && $get('socio_primary_caregiver') === 'other'; })
                ->required(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) < 18 && $get('socio_primary_caregiver') === 'other'; }),

            Forms\Components\CheckboxList::make('socio_source_of_support')
                ->label('Source of Support / Income')
                ->options([
                    'parent_guardian' => 'Parent / Guardian',
                    'government'      => 'Government',
                    'ngo'             => 'NGO / Charity',
                    'self'            => 'Self',
                    'childrens_home'  => "Children's Home",
                    'faith_based'     => 'Faith-based Organisation',
                    'community'       => 'Community Group',
                    'other'           => 'Other (specify)',
                ])
                ->columns(3)
                ->live()
                ->columnSpanFull(),

            Forms\Components\TextInput::make('socio_other_support')
                ->label('Other Support Source — Specify')
                ->placeholder('Specify…')
                ->columnSpanFull()
                ->visible(fn(Get $get) => \in_array('other', (array)($get('socio_source_of_support') ?? []), true)),

            Forms\Components\Select::make('socio_primary_language')
                ->label('Primary Language')
                ->options([
                    'ksl'       => 'Kenyan Sign Language (KSL)',
                    'english'   => 'English',
                    'kiswahili' => 'Kiswahili',
                    'kikuyu'    => 'Kikuyu',
                    'luo'       => 'Luo / Dholuo',
                    'luhya'     => 'Luhya',
                    'kamba'     => 'Kamba',
                    'kalenjin'  => 'Kalenjin',
                    'other'     => 'Other (specify)',
                ])
                ->searchable()
                ->native(false)
                ->live(),

            Forms\Components\TextInput::make('socio_language_other')
                ->label('Primary Language — Specify')
                ->placeholder('e.g. Somali, Turkana, Mijikenda…')
                ->visible(fn(Get $get) => $get('socio_primary_language') === 'other')
                ->required(fn(Get $get) => $get('socio_primary_language') === 'other'),

            Forms\Components\TextInput::make('socio_other_languages')
                ->label('Other Languages Spoken')
                ->placeholder('e.g. Kikuyu, Dholuo (comma-separated)')
                ->helperText('Optional — list additional languages'),

            Forms\Components\Radio::make('socio_school_enrolled')
                ->label('Currently Enrolled in School / Programme?')
                ->options(['yes' => 'Yes', 'no' => 'No'])
                ->inline()
                ->inlineLabel(false)
                ->helperText('If Yes, complete school details in Section F')
                ->visible(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) < 18; })
                ->columnSpanFull(),

            Forms\Components\Select::make('socio_accessibility_at_home')
                ->label('Accessibility at Home (quick check)')
                ->options(['adequate' => 'Adequate', 'limited' => 'Limited', 'not_adequate' => 'Not Adequate', 'unknown' => 'Unknown'])
                ->native(false)
                ->helperText('Background flag — no clinical reasoning here'),

            Forms\Components\Textarea::make('socio_notes')
                ->label('Notes')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * Section E returns an array of Section components (E1–E5) to be spread into the form schema.
     */
    public static function sectionESchema(?int $visitId = null): array
    {
        return [
            Forms\Components\Section::make('E1 — Medical History')
                ->icon('heroicon-o-beaker')
                ->collapsible()
                ->collapsed(true)
                ->columns(2)
                ->schema([
                    Forms\Components\CheckboxList::make('med_medical_conditions')
                        ->label('Known Medical Conditions (tick all that apply)')
                        ->options([
                            'epilepsy'        => 'Epilepsy / Seizures',
                            'cerebral_palsy'  => 'Cerebral Palsy',
                            'sickle_cell'     => 'Sickle Cell Disease',
                            'asthma'          => 'Asthma',
                            'diabetes'        => 'Diabetes',
                            'hypertension'    => 'Hypertension',
                            'heart_disease'   => 'Heart Disease',
                            'stroke'          => 'Stroke',
                            'tuberculosis'    => 'Tuberculosis (current/past)',
                            'hiv'             => 'HIV (client-declared)',
                            'none'            => 'None known',
                            'other'           => 'Other (specify)',
                        ])
                        ->columns(3)
                        ->live()
                        ->helperText('Client-reported only.')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('med_conditions_other')
                        ->label('Other Conditions — Specify')
                        ->placeholder('Describe other known condition(s)…')
                        ->visible(fn(Get $get) => \in_array('other', (array)($get('med_medical_conditions') ?? []), true))
                        ->required(fn(Get $get) => \in_array('other', (array)($get('med_medical_conditions') ?? []), true))
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('med_current_medications')
                        ->label('Current Medications')
                        ->placeholder('Names and dosage if known…')
                        ->rows(2),

                    Forms\Components\Textarea::make('med_surgical_history')
                        ->label('Surgical / Hospitalization History')
                        ->placeholder('Previous surgeries or hospital admissions…')
                        ->rows(2),

                    Forms\Components\Textarea::make('med_family_medical_history')
                        ->label('Family Medical History')
                        ->placeholder('Relevant conditions in immediate family…')
                        ->rows(2),

                    Forms\Components\Textarea::make('family_history')
                        ->label('Family History Summary (Assessment Record)')
                        ->placeholder('Broader family context for this assessment…')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\CheckboxList::make('med_previous_assessments')
                        ->label('Previous Assessments / Services Received')
                        ->options([
                            'educational'   => 'Educational Assessment',
                            'psychological' => 'Psychological Assessment',
                            'audiology'     => 'Audiology',
                            'physiotherapy' => 'Physiotherapy',
                            'ot'            => 'Occupational Therapy',
                            'speech'        => 'Speech & Language Therapy',
                            'vision'        => 'Vision Assessment',
                            'nutrition'     => 'Nutrition',
                            'none'          => 'None',
                            'other'         => 'Other (specify)',
                        ])
                        ->columns(3)
                        ->live()
                        ->helperText('Attach report if available.')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('med_previous_assessments_other')
                        ->label('Other Previous Assessment — Specify')
                        ->placeholder('Describe other assessment or service received…')
                        ->visible(fn(Get $get) => \in_array('other', (array)($get('med_previous_assessments') ?? []), true))
                        ->required(fn(Get $get) => \in_array('other', (array)($get('med_previous_assessments') ?? []), true))
                        ->columnSpanFull(),

                    Forms\Components\Radio::make('med_has_at_history')
                        ->label('Has the client previously used any assistive device(s)?')
                        ->options(['yes' => 'Yes', 'no' => 'No', 'unknown' => 'Unknown'])
                        ->inline()
                        ->inlineLabel(false)
                        ->helperText('If Yes, complete full AT history in Section E2')
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('allergy_items')
                        ->label('Allergies')
                        ->addActionLabel('+ Add Allergy')
                        ->schema([
                            Forms\Components\Select::make('allergy_type')
                                ->label('Category')
                                ->options([
                                    'drug'          => 'Drug / Medication',
                                    'food'          => 'Food',
                                    'environmental' => 'Environmental',
                                    'insect'        => 'Insect / Sting',
                                    'latex'         => 'Latex',
                                    'herbal'        => 'Herbal / Traditional Remedy',
                                    'chemical'      => 'Chemical (incl. pool chlorine)',
                                    'none_known'    => 'None Known',
                                    'other'         => 'Other (specify)',
                                ])
                                ->required()
                                ->native(false)
                                ->live(),

                            Forms\Components\Select::make('allergen_names')
                                ->label('Specific Allergen(s)')
                                ->options(fn(Get $get) => self::allergenOptions($get('allergy_type')))
                                ->multiple()
                                ->searchable()
                                ->live()
                                ->helperText('Options filtered by category. Select "Other (specify)" to add a custom entry.')
                                ->required(fn(Get $get) => !\in_array($get('allergy_type'), ['none_known', null], true)),

                            Forms\Components\TextInput::make('allergen_other')
                                ->label('Other Allergen — Specify')
                                ->placeholder('Describe the specific allergen…')
                                ->visible(fn(Get $get) => \in_array('other', (array)($get('allergen_names') ?? []), true))
                                ->required(fn(Get $get) => \in_array('other', (array)($get('allergen_names') ?? []), true)),

                            Forms\Components\CheckboxList::make('reactions')
                                ->label('Typical Reactions')
                                ->options([
                                    'rash'           => 'Rash / Itch',
                                    'swelling'       => 'Swelling',
                                    'wheeze'         => 'Wheeze / Shortness of breath',
                                    'anaphylaxis'    => 'Anaphylaxis symptoms',
                                    'nausea'         => 'Nausea / Vomiting',
                                    'diarrhoea'      => 'Diarrhoea',
                                    'abdominal_pain' => 'Abdominal Pain',
                                    'dizziness'      => 'Dizziness / Fainting',
                                    'headache'       => 'Headache',
                                    'other'          => 'Other',
                                ])
                                ->columns(2),

                            Forms\Components\Select::make('severity')
                                ->label('Severity')
                                ->options([
                                    'mild'             => 'Mild',
                                    'moderate'         => 'Moderate',
                                    'severe'           => 'Severe',
                                    'life_threatening' => 'Life-threatening (Anaphylaxis)',
                                ])
                                ->required(fn(Get $get) => !in_array($get('allergy_type'), ['none_known', null]))
                                ->native(false),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->collapsible()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('E2 — Assistive Technology Use')
                ->icon('heroicon-o-wrench-screwdriver')
                ->collapsible()
                ->collapsed(true)
                ->schema([
                    Forms\Components\Radio::make('e2_has_at')
                        ->label('Does the client currently use any assistive device(s)?')
                        ->options(['yes' => 'Yes', 'no' => 'No'])
                        ->inline()
                        ->inlineLabel(false)
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('e2_current_devices')
                        ->label('i) Current Assistive Devices')
                        ->addActionLabel('+ Add Device')
                        ->visible(fn(Get $get) => $get('e2_has_at') === 'yes')
                        ->schema([
                            Forms\Components\Select::make('category')
                                ->label('AT Category')
                                ->options([
                                    'mobility'          => 'Mobility',
                                    'seating'           => 'Seating & Positioning',
                                    'orthotics'         => 'Orthotics / Prosthetics',
                                    'vision'            => 'Vision',
                                    'hearing'           => 'Hearing',
                                    'communication_aac' => 'Communication / AAC',
                                    'learning_access'   => 'Learning / Access',
                                    'adl'               => 'ADL Aids',
                                    'other'             => 'Other — specify',
                                ])
                                ->required()
                                ->native(false)
                                ->live(),

                            Forms\Components\Select::make('device_type')
                                ->label('Device Type')
                                ->options(fn(Get $get) => self::atDeviceTypes($get('category')))
                                ->required(fn(Get $get) => !empty($get('category')))
                                ->searchable()
                                ->native(false)
                                ->live(),

                            Forms\Components\TextInput::make('device_type_other')
                                ->label('Device Type — Specify')
                                ->placeholder('Describe the device…')
                                ->visible(fn(Get $get) => $get('device_type') === 'other')
                                ->required(fn(Get $get) => $get('device_type') === 'other'),

                            Forms\Components\Select::make('source')
                                ->label('Source of Device')
                                ->options([
                                    'private'        => 'Purchased (private)',
                                    'ncpwd'          => 'NCPWD Grant',
                                    'donor_ngo'      => 'Donor / NGO (e.g. Rotary, Hope Mobility)',
                                    'public_facility'=> 'Public Facility',
                                    'kise'           => 'KISE Workshop',
                                    'school'         => 'School',
                                    'other'          => 'Other — specify',
                                ])
                                ->native(false)
                                ->live(),

                            Forms\Components\TextInput::make('source_other')
                                ->label('Source — Specify')
                                ->placeholder('Describe source…')
                                ->visible(fn(Get $get) => $get('source') === 'other')
                                ->required(fn(Get $get) => $get('source') === 'other'),

                            Forms\Components\DatePicker::make('date_issued')
                                ->label('Date Issued (approx.)')
                                ->displayFormat('M Y')
                                ->helperText('Month / Year is acceptable'),

                            Forms\Components\Select::make('fit_comfort')
                                ->label('Fit & Comfort')
                                ->options([
                                    'good'       => 'Good',
                                    'acceptable' => 'Acceptable',
                                    'poor'       => 'Poor (pain / pressure / instability)',
                                ])
                                ->required()
                                ->native(false)
                                ->helperText('Poor → auto-flags AT Safety Risk'),

                            Forms\Components\Select::make('condition')
                                ->label('Condition')
                                ->options([
                                    'good'          => 'Good',
                                    'worn'          => 'Worn',
                                    'broken'        => 'Broken / Parts missing',
                                    'battery_fault' => 'Battery fault (powered)',
                                ])
                                ->required()
                                ->native(false)
                                ->helperText('Broken → routes to Repair & Maintenance'),

                            Forms\Components\CheckboxList::make('training_received')
                                ->label('Training Received')
                                ->options([
                                    'handover_only'  => 'Device handover only',
                                    'basic_use'      => 'Basic use',
                                    'safety_transfers'=> 'Safety & transfers',
                                    'care_maintenance'=> 'Care & maintenance',
                                    'none'           => 'None',
                                ])
                                ->columns(2)
                                ->helperText('"None" → system suggests User Training referral'),

                            Forms\Components\Select::make('use_frequency')
                                ->label('Use Frequency')
                                ->options([
                                    'daily'     => 'Daily',
                                    'most_days' => 'Most days',
                                    'weekly'    => 'Weekly',
                                    'rarely'    => 'Rarely',
                                    'not_using' => 'Not using',
                                ])
                                ->native(false)
                                ->live()
                                ->helperText('"Rarely / Not using" → complete Barriers below'),

                            Forms\Components\CheckboxList::make('main_environment')
                                ->label('Main Environment of Use')
                                ->options([
                                    'home'        => 'Home',
                                    'school'      => 'School',
                                    'work'        => 'Work',
                                    'community'   => 'Community / Transport',
                                    'therapy'     => 'Therapy',
                                ])
                                ->columns(2)
                                ->helperText('For service planning'),

                            Forms\Components\CheckboxList::make('barriers')
                                ->label('Barriers to Use')
                                ->options([
                                    'pain'           => 'Pain / Discomfort',
                                    'heavy'          => 'Too heavy',
                                    'terrain'        => 'Terrain / Access',
                                    'no_spares'      => 'No spares / Repair',
                                    'stigma'         => 'Embarrassment / Stigma',
                                    'school_work_refusal' => 'School / Work refusal',
                                    'battery_cost'   => 'Battery / Consumables cost',
                                    'other'          => 'Other — specify',
                                ])
                                ->columns(2)
                                ->visible(fn(Get $get) => \in_array($get('use_frequency'), ['rarely', 'not_using', null], true)),

                            Forms\Components\CheckboxList::make('safety_concerns')
                                ->label('Safety Concerns')
                                ->options([
                                    'pressure_sores'      => 'Pressure areas / Sores',
                                    'fall_risk'           => 'Fall risk',
                                    'skin_breakdown'      => 'Skin breakdown',
                                    'hearing_discomfort'  => 'Hearing / Auditory discomfort',
                                    'vision_strain'       => 'Vision strain',
                                    'other'               => 'Other — specify',
                                ])
                                ->columns(2)
                                ->helperText('Any selected → AT Safety Risk flag'),

                            Forms\Components\Radio::make('repairs_needed')
                                ->label('Repairs / Review Needed?')
                                ->options(['yes' => 'Yes', 'no' => 'No'])
                                ->inline()
                                ->inlineLabel(false)
                                ->live(),

                            Forms\Components\TextInput::make('repairs_notes')
                                ->label('Describe Repairs Needed')
                                ->placeholder('Describe the repair or review required…')
                                ->visible(fn(Get $get) => $get('repairs_needed') === 'yes')
                                ->required(fn(Get $get) => $get('repairs_needed') === 'yes'),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->collapsible()
                        ->columnSpanFull(),

                    Forms\Components\Radio::make('e2_previous_at')
                        ->label('ii) Has the client previously used any other assistive device(s)?')
                        ->options(['yes' => 'Yes', 'no' => 'No'])
                        ->inline()
                        ->inlineLabel(false)
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('e2_previous_devices')
                        ->label('Previous AT History')
                        ->addActionLabel('+ Add Previous Device')
                        ->visible(fn(Get $get) => $get('e2_previous_at') === 'yes')
                        ->schema([
                            Forms\Components\TextInput::make('device_name')
                                ->label('Device / Category')
                                ->required(),
                            Forms\Components\TextInput::make('year')
                                ->label('Approx. Year')
                                ->placeholder('e.g. 2021'),
                            Forms\Components\Select::make('outcome')
                                ->label('Outcome')
                                ->options([
                                    'helpful'     => 'Helpful',
                                    'not_helpful' => 'Not helpful',
                                    'outgrown'    => 'Outgrown',
                                    'broken'      => 'Broken / Lost',
                                ])
                                ->native(false),
                            Forms\Components\TextInput::make('reason')
                                ->label('Reason / Notes'),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->columnSpanFull(),

                    Forms\Components\Section::make('iii) AT Needs & Gaps')
                        ->description('Complete this section if the client has no current AT or identified gaps remain')
                        ->icon('heroicon-o-light-bulb')
                        ->compact()
                        ->collapsible()
                        ->visible(fn(Get $get) => $get('e2_has_at') !== 'yes')
                        ->schema([
                            Forms\Components\Radio::make('e2_needs_at')
                                ->label('Does the client appear to need any assistive device(s)?')
                                ->options(['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Unsure — refer for assessment'])
                                ->inline()
                                ->inlineLabel(false)
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\CheckboxList::make('e2_needs_categories')
                                ->label('Identified AT Need Categories (tick all that apply)')
                                ->options([
                                    'mobility'          => 'Mobility',
                                    'seating'           => 'Seating & Positioning',
                                    'orthotics'         => 'Orthotics / Prosthetics',
                                    'vision'            => 'Vision',
                                    'hearing'           => 'Hearing',
                                    'communication_aac' => 'Communication / AAC',
                                    'learning_access'   => 'Learning / Access',
                                    'adl'               => 'ADL Aids',
                                    'other'             => 'Other',
                                ])
                                ->columns(3)
                                ->visible(fn(Get $get) => $get('e2_needs_at') === 'yes')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('e2_needs_priority')
                                ->label('Priority for AT Provision')
                                ->options([
                                    'urgent'  => 'Urgent — safety / daily function at risk',
                                    'high'    => 'High — significant functional limitation',
                                    'routine' => 'Routine — recommended but not urgent',
                                ])
                                ->native(false)
                                ->visible(fn(Get $get) => $get('e2_needs_at') === 'yes'),

                            Forms\Components\Textarea::make('e2_needs_notes')
                                ->label('AT Needs Notes')
                                ->placeholder('Describe identified needs, barriers to access, or referral plan…')
                                ->rows(2)
                                ->visible(fn(Get $get) => \in_array($get('e2_needs_at'), ['yes', 'unsure'], true))
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Forms\Components\Select::make('e2_satisfaction')
                        ->label('Client / Caregiver Satisfaction with AT Support (overall)')
                        ->options([
                            1 => '1 — Very dissatisfied',
                            2 => '2 — Dissatisfied',
                            3 => '3 — Neutral',
                            4 => '4 — Satisfied',
                            5 => '5 — Very satisfied',
                        ])
                        ->native(false)
                        ->helperText('Optional quick outcome measure'),
                ]),

            Forms\Components\Section::make('E3 — Perinatal & Early Development History')
                ->icon('heroicon-o-sparkles')
                ->collapsible()
                ->collapsed(true)
                ->visible(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) < 19; })
                ->columns(2)
                ->schema([
                    Forms\Components\CheckboxList::make('peri_pregnancy_complications')
                        ->label('Pregnancy Complications (caregiver-reported)')
                        ->options([
                            'none'              => 'None',
                            'hypertension'      => 'Hypertension',
                            'diabetes'          => 'Diabetes',
                            'infection'         => 'Infection',
                            'bleeding'          => 'Bleeding',
                            'substance_exposure'=> 'Substance exposure',
                            'other'             => 'Other (specify)',
                        ])
                        ->default([])
                        ->columns(3)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $state = is_array($state) ? $state : [];
                            if (in_array('none', $state, true) && count($state) > 1) {
                                $set('peri_pregnancy_complications', ['none']);
                            }
                        })
                        ->disableOptionWhen(fn(string $value, Get $get): bool =>
                            $value !== 'none' && in_array('none', (array)($get('peri_pregnancy_complications') ?? []), true)
                        )
                        ->helperText('Caregiver-reported only.')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('peri_pregnancy_complications_other')
                        ->label('Pregnancy Complications — Specify')
                        ->placeholder('Describe other complication…')
                        ->visible(fn(Get $get) => \in_array('other', (array)($get('peri_pregnancy_complications') ?? []), true))
                        ->required(fn(Get $get) => \in_array('other', (array)($get('peri_pregnancy_complications') ?? []), true))
                        ->columnSpanFull(),

                    Forms\Components\Select::make('peri_place_of_birth')
                        ->label('Place of Birth')
                        ->options(['health_facility' => 'Health Facility', 'home' => 'Home', 'other' => 'Other (specify)'])
                        ->native(false)
                        ->live(),

                    Forms\Components\TextInput::make('peri_place_of_birth_other')
                        ->label('Place of Birth — Specify')
                        ->placeholder('e.g. Traditional birth attendant, en route…')
                        ->visible(fn(Get $get) => $get('peri_place_of_birth') === 'other')
                        ->required(fn(Get $get) => $get('peri_place_of_birth') === 'other'),

                    Forms\Components\Select::make('peri_mode_of_delivery')
                        ->label('Mode of Delivery')
                        ->options([
                            'svd'      => 'Spontaneous Vaginal',
                            'assisted' => 'Assisted (forceps / vacuum)',
                            'cs'       => 'Caesarean Section',
                        ])
                        ->native(false),

                    Forms\Components\TextInput::make('peri_gestation_weeks')
                        ->label('Gestation at Birth (weeks)')
                        ->numeric()
                        ->minValue(22)
                        ->maxValue(44)
                        ->placeholder('e.g. 38')
                        ->helperText('Range 22–44 weeks. Optional if unknown.'),

                    Forms\Components\TextInput::make('peri_birth_weight_kg')
                        ->label('Birth Weight (kg)')
                        ->numeric()
                        ->minValue(1.0)
                        ->maxValue(6.0)
                        ->step(0.1)
                        ->placeholder('e.g. 3.2')
                        ->helperText('Range 1.0–6.0 kg. Optional if unknown.'),

                    Forms\Components\CheckboxList::make('peri_neonatal_care')
                        ->label('Neonatal Care Required')
                        ->options([
                            'none'        => 'None',
                            'oxygen'      => 'Oxygen',
                            'incubator'   => 'Incubator',
                            'nicu'        => 'NICU Admission',
                            'phototherapy'=> 'Phototherapy',
                            'other'       => 'Other (specify)',
                        ])
                        ->default([])
                        ->columns(3)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $state = is_array($state) ? $state : [];
                            if (in_array('none', $state, true) && count($state) > 1) {
                                $set('peri_neonatal_care', ['none']);
                            }
                        })
                        ->disableOptionWhen(fn(string $value, Get $get): bool =>
                            $value !== 'none' && in_array('none', (array)($get('peri_neonatal_care') ?? []), true)
                        )
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('peri_neonatal_care_other')
                        ->label('Neonatal Care — Specify')
                        ->placeholder('Describe other neonatal care received…')
                        ->visible(fn(Get $get) => \in_array('other', (array)($get('peri_neonatal_care') ?? []), true))
                        ->required(fn(Get $get) => \in_array('other', (array)($get('peri_neonatal_care') ?? []), true))
                        ->columnSpanFull(),

                    Forms\Components\CheckboxList::make('peri_early_medical_issues')
                        ->label('Early Medical Issues (first months)')
                        ->options([
                            'jaundice'          => 'Jaundice',
                            'seizures'          => 'Seizures',
                            'infection'         => 'Infection / Sepsis',
                            'feeding_difficulty'=> 'Feeding difficulty',
                            'none'              => 'None',
                            'other'             => 'Other (specify)',
                        ])
                        ->default([])
                        ->columns(3)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $state = is_array($state) ? $state : [];
                            if (in_array('none', $state, true) && count($state) > 1) {
                                $set('peri_early_medical_issues', ['none']);
                            }
                        })
                        ->disableOptionWhen(fn(string $value, Get $get): bool =>
                            $value !== 'none' && in_array('none', (array)($get('peri_early_medical_issues') ?? []), true)
                        )
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('peri_early_medical_issues_other')
                        ->label('Early Medical Issues — Specify')
                        ->placeholder('Describe other early medical issue…')
                        ->visible(fn(Get $get) => \in_array('other', (array)($get('peri_early_medical_issues') ?? []), true))
                        ->required(fn(Get $get) => \in_array('other', (array)($get('peri_early_medical_issues') ?? []), true))
                        ->columnSpanFull(),

                    Forms\Components\CheckboxList::make('peri_developmental_concerns')
                        ->label('Developmental Concerns (caregiver-reported)')
                        ->options([
                            'motor'     => 'Motor / Movement',
                            'language'  => 'Language / Communication',
                            'social'    => 'Social skills',
                            'cognitive' => 'Cognitive / Learning',
                            'feeding'   => 'Feeding',
                            'sleep'     => 'Sleep',
                            'behaviour' => 'Behaviour',
                            'none'      => 'None',
                            'other'     => 'Other (specify)',
                        ])
                        ->default([])
                        ->columns(3)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $state = is_array($state) ? $state : [];
                            if (in_array('none', $state, true) && count($state) > 1) {
                                $set('peri_developmental_concerns', ['none']);
                            }
                        })
                        ->disableOptionWhen(fn(string $value, Get $get): bool =>
                            $value !== 'none' && in_array('none', (array)($get('peri_developmental_concerns') ?? []), true)
                        )
                        ->helperText('Quick flags only.')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('peri_developmental_concerns_other')
                        ->label('Developmental Concerns — Specify')
                        ->placeholder('Describe other developmental concern…')
                        ->visible(fn(Get $get) => \in_array('other', (array)($get('peri_developmental_concerns') ?? []), true))
                        ->required(fn(Get $get) => \in_array('other', (array)($get('peri_developmental_concerns') ?? []), true))
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('developmental_history')
                        ->label('Additional Developmental Notes')
                        ->placeholder('Milestones, regression, other observations…')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('E4 — Immunization Snapshot')
                ->icon('heroicon-o-shield-check')
                ->collapsible()
                ->collapsed(true)
                ->visible(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) < 19; })
                ->columns(2)
                ->schema([
                    Forms\Components\Radio::make('imm_epi_card_seen')
                        ->label('EPI Card Seen Today?')
                        ->options(['yes' => 'Yes', 'no' => 'No', 'unknown' => 'Unknown'])
                        ->inline()
                        ->inlineLabel(false)
                        ->live()
                        ->helperText('If Yes — upload a photo of the card below'),

                    Forms\Components\FileUpload::make('imm_epi_card_photo')
                        ->label('EPI Card Photo')
                        ->helperText('Upload a photo or scan of the EPI card')
                        ->disk('public')
                        ->directory('epi-cards')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                        ->maxSize(5120)
                        ->downloadable()
                        ->previewable(false)
                        ->visible(fn(Get $get) => $get('imm_epi_card_seen') === 'yes'),

                    Forms\Components\CheckboxList::make('imm_epi_status')
                        ->label('EPI Vaccines Received (card or caregiver)')
                        ->options([
                            'none'            => 'None received / Unknown',
                            'bcg'             => 'BCG',
                            'opv'             => 'OPV',
                            'pentavalent'     => 'Pentavalent',
                            'pcv'             => 'PCV',
                            'rotavirus'       => 'Rotavirus',
                            'measles_rubella' => 'Measles-Rubella (MR)',
                            'yellow_fever'    => 'Yellow Fever (if applicable)',
                            'hpv'             => 'HPV (≥9 yrs)',
                            'td'              => 'Tetanus-Diphtheria (adolescents)',
                        ])
                        ->default([])
                        ->afterStateHydrated(function (Forms\Components\CheckboxList $component, mixed $state): void {
                            if (!is_array($state)) {
                                $component->state([]);
                            }
                        })
                        ->columns(3)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $state = is_array($state) ? $state : [];
                            if (in_array('none', $state, true) && count($state) > 1) {
                                $set('imm_epi_status', ['none']);
                            }
                        })
                        ->disableOptionWhen(fn(string $value, Get $get): bool =>
                            $value !== 'none' && in_array('none', (array)($get('imm_epi_status') ?? []), true)
                        )
                        ->helperText('Selecting "None received / Unknown" clears all other selections. Kenya EPI context — intake does not interpret, records only.')
                        ->columnSpanFull(),

                    Forms\Components\Radio::make('imm_missed_doses')
                        ->label('Missed or Unknown Doses?')
                        ->options(['yes' => 'Yes', 'no' => 'No'])
                        ->inline()
                        ->inlineLabel(false)
                        ->live()
                        ->helperText('Background flag for downstream visibility'),

                    Forms\Components\TextInput::make('imm_missed_doses_which')
                        ->label('Specify Missed / Unknown Doses')
                        ->placeholder('e.g. OPV dose 2, Pentavalent 3…')
                        ->visible(fn(Get $get) => $get('imm_missed_doses') === 'yes')
                        ->required(fn(Get $get) => $get('imm_missed_doses') === 'yes'),

                    Forms\Components\Radio::make('imm_recent_illness_post_vaccine')
                        ->label('Recent Illness Following a Vaccine?')
                        ->options(['yes' => 'Yes', 'no' => 'No'])
                        ->inline()
                        ->inlineLabel(false)
                        ->live()
                        ->helperText('Optional — flag for clinician awareness'),

                    Forms\Components\TextInput::make('imm_recent_illness_notes')
                        ->label('Illness After Vaccine — Details')
                        ->placeholder('Which vaccine, when, what symptoms…')
                        ->visible(fn(Get $get) => $get('imm_recent_illness_post_vaccine') === 'yes')
                        ->required(fn(Get $get) => $get('imm_recent_illness_post_vaccine') === 'yes'),

                    Forms\Components\Textarea::make('med_immunization_status')
                        ->label('General Immunization Notes')
                        ->placeholder('Any additional immunization context, concerns, or observations…')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('E5 — Feeding & Nutrition Snapshot')
                ->icon('heroicon-o-cake')
                ->collapsible()
                ->collapsed(true)
                ->visible(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) < 19; })
                ->description('Auto-shown for age < 5. Also complete if Nutrition service is selected in Section I.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('feeding_method')
                        ->label('Feeding History')
                        ->options([
                            'exclusive_bf'  => 'Exclusive breastfeeding (≤ 6 months)',
                            'mixed'         => 'Mixed feeding (breast + formula)',
                            'formula'       => 'Formula feeding',
                            'complementary' => 'Complementary feeding (age-appropriate)',
                            'family_foods'  => 'Family foods',
                            'tube_feeding'  => 'Tube / Nasogastric feeding',
                            'unknown'       => 'Unknown',
                        ])
                        ->native(false)
                        ->helperText('Caregiver-reported.'),

                    Forms\Components\Select::make('feeding_diet_appetite')
                        ->label('Current Diet & Appetite')
                        ->options(['good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'])
                        ->native(false)
                        ->live(),

                    Forms\Components\TextInput::make('feeding_diet_foods_brief')
                        ->label('Brief Foods Description')
                        ->placeholder('e.g. Porridge, ugali, mashed vegetables, milk…')
                        ->helperText('List main foods currently eaten')
                        ->columnSpanFull(),

                    Forms\Components\CheckboxList::make('feeding_swallowing_concerns')
                        ->label('Feeding / Swallowing Concerns')
                        ->options([
                            'sucking_difficulty'    => 'Sucking difficulty',
                            'swallowing_difficulty' => 'Swallowing difficulty',
                            'choking'               => 'Coughing / Choking',
                            'texture_aversion'      => 'Texture aversion',
                            'none'                  => 'None',
                            'other'                 => 'Other (specify)',
                        ])
                        ->columns(3)
                        ->live()
                        ->helperText('Any clinical concern → auto-flags Speech & Language + Nutrition referral')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('feeding_swallowing_concerns_other')
                        ->label('Feeding / Swallowing Concern — Specify')
                        ->placeholder('Describe other concern…')
                        ->visible(fn(Get $get) => \in_array('other', (array)($get('feeding_swallowing_concerns') ?? []), true))
                        ->required(fn(Get $get) => \in_array('other', (array)($get('feeding_swallowing_concerns') ?? []), true))
                        ->columnSpanFull(),

                    Forms\Components\Radio::make('feeding_growth_concern')
                        ->label('Growth Concern (caregiver-reported)?')
                        ->options(['yes' => 'Yes', 'no' => 'No'])
                        ->inline()
                        ->inlineLabel(false)
                        ->helperText('BMI-for-age handled in Triage — Intake records caregiver view only'),

                    Forms\Components\Textarea::make('social_history')
                        ->label('Nutrition & Feeding Notes')
                        ->placeholder('Growth concerns, dietary restrictions, supplement use…')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function sectionFSchema(?int $visitId = null): array
    {
        return [
            Forms\Components\Select::make('edu_education_level')
                ->label('Current Education Level')
                ->options([
                    'none'      => 'None / Not Applicable',
                    'ecd'       => 'Early Childhood Development (ECD)',
                    'primary'   => 'Primary',
                    'secondary' => 'Secondary',
                    'tertiary'  => 'Tertiary / College / University',
                    'vocational'=> 'Vocational / TVET',
                ])
                ->native(false)
                ->live(),

            Forms\Components\Radio::make('edu_currently_enrolled')
                ->label('Currently Enrolled in School / Programme?')
                ->options(['yes' => 'Yes', 'no' => 'No'])
                ->inline()
                ->inlineLabel(false)
                ->live(),

            Forms\Components\Select::make('edu_school_type')
                ->label('School Type')
                ->options([
                    'regular'    => 'Regular',
                    'special'    => 'Special School',
                    'integrated' => 'Integrated / Inclusive Unit',
                    'homeschool' => 'Home Schooling',
                ])
                ->native(false)
                ->visible(fn(Get $get) => $get('edu_currently_enrolled') === 'yes'),

            Forms\Components\TextInput::make('edu_school_name')
                ->label('School / Institution Name')
                ->visible(fn(Get $get) => $get('edu_currently_enrolled') === 'yes'),

            Forms\Components\Select::make('edu_grade_level')
                ->label('Grade / Level')
                ->options(function (Get $get): array {
                    return match ($get('edu_education_level')) {
                        'ecd'       => ['pp1' => 'PP1', 'pp2' => 'PP2'],
                        'primary'   => [
                            'grade_1' => 'Grade 1', 'grade_2' => 'Grade 2', 'grade_3' => 'Grade 3',
                            'grade_4' => 'Grade 4', 'grade_5' => 'Grade 5', 'grade_6' => 'Grade 6',
                            'grade_7' => 'Grade 7', 'grade_8' => 'Grade 8', 'grade_9' => 'Grade 9',
                        ],
                        'secondary' => [
                            'form_1' => 'Form 1', 'form_2' => 'Form 2',
                            'form_3' => 'Form 3', 'form_4' => 'Form 4',
                            'grade_10' => 'Grade 10 (CBC)', 'grade_11' => 'Grade 11 (CBC)', 'grade_12' => 'Grade 12 (CBC)',
                        ],
                        'tertiary'  => [
                            'year_1' => 'Year 1', 'year_2' => 'Year 2',
                            'year_3' => 'Year 3', 'year_4' => 'Year 4+',
                            'diploma_y1' => 'Diploma Year 1', 'diploma_y2' => 'Diploma Year 2', 'diploma_y3' => 'Diploma Year 3',
                        ],
                        'vocational'=> [
                            'cert_y1' => 'Certificate Year 1', 'cert_y2' => 'Certificate Year 2',
                            'dip_y1'  => 'Diploma Year 1',    'dip_y2'  => 'Diploma Year 2',
                        ],
                        default     => ['other' => 'Other'],
                    };
                })
                ->native(false)
                ->visible(fn(Get $get) => $get('edu_currently_enrolled') === 'yes'),

            Forms\Components\Radio::make('edu_attendance_challenges')
                ->label('Attendance Challenges?')
                ->options(['yes' => 'Yes', 'no' => 'No'])
                ->inline()
                ->inlineLabel(false)
                ->live()
                ->visible(fn(Get $get) => $get('edu_currently_enrolled') === 'yes'),

            Forms\Components\TextInput::make('edu_attendance_notes')
                ->label('Attendance — Brief Reason')
                ->required(fn(Get $get) => $get('edu_attendance_challenges') === 'yes')
                ->visible(fn(Get $get) => $get('edu_currently_enrolled') === 'yes' && $get('edu_attendance_challenges') === 'yes'),

            Forms\Components\Radio::make('edu_performance_concern')
                ->label('Academic Performance Concern?')
                ->options(['yes' => 'Yes', 'no' => 'No'])
                ->inline()
                ->inlineLabel(false)
                ->live()
                ->visible(fn(Get $get) => $get('edu_currently_enrolled') === 'yes'),

            Forms\Components\TextInput::make('edu_performance_notes')
                ->label('Performance — Brief Reason')
                ->required(fn(Get $get) => $get('edu_performance_concern') === 'yes')
                ->visible(fn(Get $get) => $get('edu_currently_enrolled') === 'yes' && $get('edu_performance_concern') === 'yes'),

            Forms\Components\Select::make('edu_employment_status')
                ->label('Employment Status')
                ->options([
                    'unemployed'   => 'Unemployed',
                    'employed'     => 'Employed (formal)',
                    'self_employed'=> 'Self-employed / Informal',
                    'student'      => 'Student',
                    'retired'      => 'Retired',
                    'other'        => 'Other (specify)',
                ])
                ->native(false)
                ->live()
                ->visible(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) >= 18; }),

            Forms\Components\TextInput::make('edu_employment_status_other')
                ->label('Employment Status — Specify')
                ->placeholder('Describe employment situation…')
                ->required(fn(Get $get) => $get('edu_employment_status') === 'other')
                ->visible(function (Get $get) use ($visitId) { return $get('edu_employment_status') === 'other' && self::resolveClientAge($get, $visitId) >= 18; }),

            Forms\Components\TextInput::make('edu_occupation_type')
                ->label('Occupation / Role')
                ->placeholder('e.g. Teacher, Farmer, Hawker')
                ->visible(function (Get $get) use ($visitId) { return self::resolveClientAge($get, $visitId) >= 18; }),
        ];
    }

    public static function sectionGSchema(?int $visitId = null): array
    {
        return [
            Forms\Components\Placeholder::make('age_band_banner')
                ->hiddenLabel()
                ->content(function (Get $get) use ($visitId): HtmlString {
                    $vid = $get('visit_id') ?: $visitId;
                    if (!$vid) return new HtmlString('');
                    $client = Visit::with('client')->find($vid)?->client;
                    if (!$client?->date_of_birth) return new HtmlString('<div style="padding:8px 14px;background:#f1f5f9;border-radius:6px;color:#64748b;">Age band: unknown (DOB not recorded).</div>');
                    $months = (int) Carbon::parse($client->date_of_birth)->diffInMonths(now());
                    $years  = floor($months / 12);
                    $rem    = $months % 12;
                    $band   = self::detectBandKey($months);
                    $label  = self::ageBandLabel($band);
                    return new HtmlString('<div style="padding:10px 14px;background:#dbeafe;border-radius:8px;border-left:4px solid #3b82f6;color:#1e40af;font-size:13px;font-weight:600;">📊 Age Band: '.$label.' ('.$years.' yrs '.$rem.' mo) — questions below are age-appropriate.</div>');
                })
                ->columnSpanFull(),

            ...array_map(
                fn($bk, $band) => self::buildBandSection($bk, $band),
                array_keys(self::screeningQuestions()),
                array_values(self::screeningQuestions())
            ),
        ];
    }

    public static function sectionHSchema(?int $visitId = null): array
    {
        return [
            Forms\Components\CheckboxList::make('referral_source')
                ->label('Referral Source (tick all that apply)')
                ->options([
                    'self'             => 'Self / Family',
                    'school'           => 'School / ECD Centre',
                    'hospital'         => 'Hospital / Health Facility',
                    'community_worker' => 'Community Health Worker',
                    'social_media'     => 'Social Media',
                    'court'            => 'Court / Legal System',
                    'kise_internal'    => 'KISE Internal Referral',
                    'other'            => 'Other (specify)',
                ])
                ->columns(3)
                ->required()
                ->live()
                ->columnSpanFull(),

            Forms\Components\TextInput::make('referral_source_other')
                ->label('Referral Source — Specify')
                ->placeholder('Name the other referral source…')
                ->required(fn(Get $get) => in_array('other', (array)($get('referral_source') ?? []), true))
                ->visible(fn(Get $get) => in_array('other', (array)($get('referral_source') ?? []), true))
                ->columnSpanFull(),

            Forms\Components\TextInput::make('referral_contact')
                ->label('Referring Person / Institution')
                ->placeholder('Name or institution that referred the client')
                ->maxLength(250),

            Forms\Components\Textarea::make('reason_for_visit')
                ->label('Reason for Visit (one concise line)')
                ->placeholder('Primary reason for this visit…')
                ->rows(2)
                ->maxLength(250)
                ->helperText('Max 250 characters — avoid long narratives')
                ->required()
                ->columnSpanFull(),

            Forms\Components\Textarea::make('current_concerns')
                ->label('Current Concerns & Needs')
                ->placeholder('Additional concerns raised by client or family…')
                ->rows(3)
                ->columnSpanFull(),

            Forms\Components\Textarea::make('previous_interventions')
                ->label('Previous Interventions / Services Received')
                ->placeholder('What services has the client received before?')
                ->rows(2)
                ->columnSpanFull(),

            Forms\Components\FileUpload::make('h_reports_uploaded')
                ->label('Reports / Documents Uploaded')
                ->helperText('Previous reports, referral letters, doctor notes — PDF, JPG or PNG. Optional unless used to verify or adjust intake data.')
                ->multiple()
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->directory('intake-reports')
                ->maxSize(10240)
                ->columnSpanFull(),
        ];
    }

    public static function sectionISchema(?int $visitId = null): array
    {
        return [
            Forms\Components\CheckboxList::make('i_service_categories')
                ->label('Step 1 — Service Categories Needed (tick all that apply)')
                ->options([
                    'educational_assessment'  => 'Educational Assessment',
                    'psychological_assessment'=> 'Psychological Assessment',
                    'audiology'               => 'Audiology',
                    'physiotherapy'           => 'Physiotherapy',
                    'occupational_therapy'    => 'Occupational Therapy',
                    'speech_language'         => 'Speech & Language Therapy',
                    'vision'                  => 'Vision',
                    'counselling'             => 'Counselling',
                    'assistive_technology'    => 'Assistive Technology Consult',
                    'nutrition'               => 'Nutrition',
                    'other'                   => 'Other',
                ])
                ->columns(3)
                ->live()
                ->helperText('Selecting categories filters the service lists below.'),

            Forms\Components\Select::make('i_primary_service_id')
                ->label('Step 2 — Primary Service Posting (required)')
                ->options(function (Get $get) {
                    return self::filteredServiceOptions($get);
                })
                ->searchable()
                ->native(false)
                ->required()
                ->helperText('The service point the client visits first after billing.'),

            Forms\Components\CheckboxList::make('services_selected')
                ->label('Step 3 — Additional Services / Cross-Posting (optional)')
                ->options(function (Get $get) {
                    return self::filteredServiceOptions($get);
                })
                ->columns(2)
                ->helperText('Each additional service creates a cross-posting booking. Leave blank if primary only.'),

            Forms\Components\Placeholder::make('service_availability_notice')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<div style="padding:8px 14px;background:#fef9c3;border-radius:6px;border-left:4px solid #ca8a04;color:#713f12;font-size:12px;">'
                    . '📅 <b>Service Availability:</b> Live slot availability tracking is not yet integrated. '
                    . 'Confirm slot availability with Customer Care before finalising the booking.'
                    . '</div>'
                ))
                ->columnSpanFull(),

            Forms\Components\Textarea::make('handover_note')
                ->label('Handover Note to Service Provider (1–2 lines)')
                ->placeholder('Key cues for the service provider — no narratives…')
                ->rows(2)
                ->maxLength(300)
                ->columnSpanFull(),
        ];
    }

    public static function sectionJSchema(?int $visitId = null): array
    {
        $client = $visitId ? Visit::with('client')->find($visitId)?->client : null;
        $hasSha   = !empty($client?->sha_number);
        $hasNcpwd = !empty($client?->ncpwd_number);

        return [
            // ── Enrolled schemes ──────────────────────────────────────────────────
            Forms\Components\Placeholder::make('j_scheme_header')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<p style="font-size:13px;font-weight:600;color:#374151;margin:0 0 4px;">Step 1 — Enrolled Schemes</p>'
                    . '<p style="font-size:12px;color:#64748b;margin:0;">Tick all schemes the client is enrolled in. Auto-detected entries are pre-ticked from the client record.</p>'
                ))
                ->columnSpanFull(),

            Forms\Components\Toggle::make('sha_enrolled')
                ->label('SHA Enrolled')
                ->helperText($hasSha ? '✓ SHA number detected on record' : 'Social Health Authority enrolment')
                ->live(),

            Forms\Components\Toggle::make('ncpwd_covered')
                ->label('NCPWD Cover Applicable')
                ->helperText($hasNcpwd ? '✓ NCPWD number detected on record' : 'National Council for Persons with Disabilities')
                ->live(),

            Forms\Components\Toggle::make('has_private_insurance')
                ->label('Private / Employer Insurance')
                ->helperText('Tick if client holds an active private or employer health plan')
                ->live(),

            // ── Payment method radio cards ────────────────────────────────────────
            Forms\Components\Placeholder::make('j_method_header')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<p style="font-size:13px;font-weight:600;color:#374151;margin:8px 0 4px;">Step 2 — Primary Payment Method</p>'
                    . '<p style="font-size:12px;color:#64748b;margin:0;">Select the method the client will use at billing. M-PESA can always override at the cashier window.</p>'
                ))
                ->columnSpanFull(),

            Forms\Components\Radio::make('expected_payment_method')
                ->label('Primary Payment Method')
                ->hiddenLabel()
                ->options([
                    'sha'       => 'SHA — Social Health Authority',
                    'ncpwd'     => 'NCPWD — Disability Subsidy',
                    'insurance' => 'Private / Employer Insurance',
                    'mpesa'     => 'M-PESA (Mobile Money)',
                    'cash'      => 'Cash (Counter Payment)',
                    'ecitizen'  => 'eCitizen Portal',
                    'mixed'     => 'Hybrid / Multiple Methods',
                ])
                ->descriptions([
                    'sha'       => 'Covered by SHA enrolment — verify card or number at billing',
                    'ncpwd'     => 'Subsidised via NCPWD disability card — card must be sighted',
                    'insurance' => 'Billed to private or employer insurance — provide policy details',
                    'mpesa'     => 'Client pays via M-PESA at cashier — no eligibility documents needed',
                    'cash'      => 'Client pays cash at the cashier window',
                    'ecitizen'  => 'Government eCitizen portal payment — confirm reference at cashier',
                    'mixed'     => 'Combination of SHA, NCPWD, insurance and/or out-of-pocket',
                ])
                ->live()
                ->required()
                ->columnSpanFull(),

            // ── Eligibility status ────────────────────────────────────────────────
            Forms\Components\Placeholder::make('j_eligibility_status')
                ->label('Eligibility Status')
                ->content(function (Get $get): HtmlString {
                    $method = $get('expected_payment_method');
                    $sha    = (bool) $get('sha_enrolled');
                    $ncpwd  = (bool) $get('ncpwd_covered');
                    $insure = (bool) $get('has_private_insurance');

                    [$badge, $bg, $text, $note] = match ($method) {
                        'sha'       => $sha
                            ? ['✓ Eligible',  '#dcfce7', '#15803d', 'SHA enrolment confirmed — ready for billing.']
                            : ['⚠ Unconfirmed','#fef9c3', '#92400e', 'SHA enrolment not confirmed — verify card or number before billing.'],
                        'ncpwd'     => $ncpwd
                            ? ['✓ Eligible',  '#dcfce7', '#15803d', 'NCPWD cover noted — card must be sighted by the billing clerk.']
                            : ['⚠ Unconfirmed','#fef9c3', '#92400e', 'NCPWD cover not confirmed — verify card at billing.'],
                        'insurance' => $insure
                            ? ['✓ Eligible',  '#dcfce7', '#15803d', 'Private insurance noted — provide policy number and card to billing.']
                            : ['⚠ Unconfirmed','#fef9c3', '#92400e', 'Private insurance not confirmed — tick the toggle above if applicable.'],
                        'mpesa'     => ['✓ Ready',    '#f0fdf4', '#15803d', 'M-PESA payment — client pays at cashier using their mobile number.'],
                        'cash'      => ['✓ Ready',    '#f0fdf4', '#15803d', 'Cash payment — no eligibility documents required.'],
                        'ecitizen'  => ['✓ Ready',    '#f0fdf4', '#15803d', 'eCitizen payment — confirm transaction reference at cashier.'],
                        'mixed'     => ['ℹ Review',   '#eff6ff', '#1d4ed8', 'Hybrid pathway — Payment Admin will verify each component at billing.'],
                        default     => ['— Pending',  '#f8fafc', '#94a3b8', 'Select a payment method above to see eligibility guidance.'],
                    };

                    return new HtmlString(
                        "<div style='padding:10px 14px;background:{$bg};border-radius:8px;border:1px solid {$bg};display:flex;align-items:flex-start;gap:10px;'>"
                        . "<span style='font-weight:700;color:{$text};font-size:12px;white-space:nowrap;'>{$badge}</span>"
                        . "<span style='font-size:12px;color:#475569;line-height:1.5;'>{$note}</span>"
                        . "</div>"
                    );
                }),

            // ── Documents required ────────────────────────────────────────────────
            Forms\Components\Placeholder::make('j_documents_required')
                ->label('Documents to Bring')
                ->content(function (Get $get): HtmlString {
                    $method = $get('expected_payment_method');
                    $docs = match ($method) {
                        'sha'       => ['SHA card or registration number', 'National ID of principal member'],
                        'ncpwd'     => ['NCPWD card (original or certified copy)', 'National ID or passport'],
                        'insurance' => ['Insurance card / certificate', 'Policy or member number', 'Pre-authorisation letter (if required by insurer)'],
                        'mpesa'     => ['National ID or passport', 'Phone with M-PESA access'],
                        'cash'      => ['No additional documents required'],
                        'ecitizen'  => ['National ID or Birth Certificate', 'eCitizen account reference or receipt'],
                        'mixed'     => ['Documents per each pathway — Payment Admin will advise at billing'],
                        default     => ['Select a payment method above'],
                    };
                    $items = implode('', array_map(fn($d) => "<li style='margin:3px 0;color:#374151;'>{$d}</li>", $docs));
                    return new HtmlString(
                        "<ul style='margin:0;padding-left:18px;font-size:12px;line-height:1.6;'>{$items}</ul>"
                    );
                }),

            Forms\Components\Textarea::make('payment_notes')
                ->label('Notes for Payment Admin')
                ->placeholder('Document status, exemption notes, special arrangements…')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    public static function sectionKSchema(?int $visitId = null): array
    {
        return [
            Forms\Components\Toggle::make('defer_client')
                ->label('Defer client — cannot be served today')
                ->live()
                ->columnSpanFull(),

            Forms\Components\Select::make('deferral_reason')
                ->label('Reason for Deferral')
                ->options([
                    'service_unavailable'  => 'Service not available today',
                    'missing_documents'    => 'Missing documents',
                    'client_request'       => 'Client request',
                    'medical_clearance'    => 'Awaiting medical clearance',
                    'payment_pending'      => 'Payment arrangement needed',
                    'other'                => 'Other (specify)',
                ])
                ->native(false)
                ->live()
                ->visible(fn(Get $get) => $get('defer_client'))
                ->required(fn(Get $get) => $get('defer_client')),

            Forms\Components\TextInput::make('deferral_reason_other')
                ->label('Deferral Reason — Specify')
                ->placeholder('Describe the reason for deferral…')
                ->required(fn(Get $get) => $get('defer_client') && $get('deferral_reason') === 'other')
                ->visible(fn(Get $get) => $get('defer_client') && $get('deferral_reason') === 'other'),

            Forms\Components\DateTimePicker::make('next_appointment_date')
                ->label('Next Appointment Date & Time')
                ->minDate(now())
                ->visible(fn(Get $get) => $get('defer_client'))
                ->required(fn(Get $get) => $get('defer_client')),

            Forms\Components\Toggle::make('client_sensitized')
                ->label('Client / Caregiver has been sensitized about next steps')
                ->helperText('Must confirm before saving deferral')
                ->visible(fn(Get $get) => $get('defer_client'))
                ->required(fn(Get $get) => $get('defer_client'))
                ->columnSpanFull(),

            Forms\Components\Textarea::make('deferral_notes')
                ->label('Notes to Client / Caregiver')
                ->rows(2)
                ->visible(fn(Get $get) => $get('defer_client'))
                ->columnSpanFull(),
        ];
    }

    public static function sectionLSchema(?int $visitId = null): array
    {
        return [
            Forms\Components\Placeholder::make('l_data_quality_banner')
                ->hiddenLabel()
                ->content(function (Get $get): HtmlString {
                    $missing = [];
                    if (empty($get('referral_source')))          $missing[] = 'Referral Source (H)';
                    if (empty($get('reason_for_visit')))         $missing[] = 'Reason for Visit (H)';
                    if (empty($get('i_primary_service_id')))     $missing[] = 'Primary Service Posting (I)';
                    if (empty($get('expected_payment_method')))  $missing[] = 'Payment Pathway (J)';
                    if (empty($get('assessment_summary')))       $missing[] = 'Intake Summary (L)';

                    if (empty($missing)) {
                        return new HtmlString(
                            '<div style="padding:8px 14px;background:#dcfce7;border-radius:6px;border-left:4px solid #16a34a;color:#14532d;font-size:13px;font-weight:600;">'
                            . '✅ All required fields completed — form ready to submit.'
                            . '</div>'
                        );
                    }
                    $chips = implode(' &nbsp;', array_map(
                        fn($f) => "<span style='display:inline-block;padding:2px 8px;background:#fee2e2;color:#991b1b;border-radius:999px;font-size:11px;font-weight:600;margin:2px;'>{$f}</span>",
                        $missing
                    ));
                    return new HtmlString(
                        '<div style="padding:8px 14px;background:#fef2f2;border-radius:6px;border-left:4px solid #dc2626;color:#991b1b;font-size:13px;">'
                        . '<b>⚠ ' . count($missing) . ' required field(s) incomplete:</b><br>'
                        . '<div style="margin-top:6px;">' . $chips . '</div>'
                        . '</div>'
                    );
                })
                ->columnSpanFull(),

            Forms\Components\Select::make('priority_level')
                ->label('Priority Level')
                ->options([1 => '1 — Urgent', 2 => '2 — High', 3 => '3 — Medium', 4 => '4 — Low', 5 => '5 — Routine'])
                ->default(3)
                ->native(false),

            Forms\Components\Select::make('data_verified')
                ->label('Data Verified')
                ->options(['1' => 'Yes — all data confirmed', '0' => 'No — pending verification'])
                ->default('1')
                ->native(false),

            Forms\Components\Textarea::make('assessment_summary')
                ->label('Intake Summary')
                ->placeholder('Overall summary of this intake assessment…')
                ->rows(4)
                ->required()
                ->live(onBlur: true)
                ->columnSpanFull(),

            Forms\Components\Textarea::make('recommendations')
                ->label('Recommendations')
                ->placeholder('Recommended services, referrals, follow-up actions…')
                ->rows(3)
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('l_audit_officer')
                ->label('Intake Officer')
                ->content(fn() => new HtmlString(
                    '<span style="font-size:13px;color:#374151;">'
                    . htmlspecialchars(Auth::user()?->name ?? '—')
                    . ' <span style="color:#94a3b8;font-size:11px;">(ID: ' . (Auth::id() ?? '—') . ')</span>'
                    . '</span>'
                )),

            Forms\Components\Placeholder::make('l_audit_time')
                ->label('Intake Completion Time')
                ->content(fn() => new HtmlString(
                    '<span style="font-size:13px;color:#374151;">' . now()->format('d M Y, H:i') . '</span>'
                    . '<span style="color:#94a3b8;font-size:11px;margin-left:6px;">(auto-recorded on save)</span>'
                )),
        ];
    }

    // ─── Form ─────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Hidden::make('visit_id')
                ->default(fn() => request()->query('visit')),

            // ══ A — PROFILE CARD ═══════════════════════════════════════════════════
            Forms\Components\Section::make('')->schema([
                Forms\Components\Placeholder::make('client_info_card')
                    ->hiddenLabel()
                    ->content(function (Get $get): HtmlString {
                        $visitId = $get('visit_id') ?: request()->query('visit');
                        if (!$visitId) return new HtmlString('<div style="padding:14px;background:#fef3c7;border-radius:8px;color:#92400e;font-weight:600;">⚠ No visit selected.</div>');
                        $visit  = Visit::with(['client', 'triage'])->find($visitId);
                        if (!$visit) return new HtmlString('<div style="padding:14px;background:#fee2e2;border-radius:8px;color:#991b1b;">Visit not found.</div>');
                        $client = $visit->client;
                        $triage = $visit->triage;
                        if (!$client) return new HtmlString('<div style="padding:14px;background:#fee2e2;border-radius:8px;color:#991b1b;">Client not found.</div>');

                        // Block if triage is Medical Hold / Crisis
                        $blockedStatuses = ['medical_hold', 'crisis', 'emergency'];
                        if ($triage && in_array($triage->triage_status, $blockedStatuses)) {
                            $ts = ucfirst(str_replace('_', ' ', $triage->triage_status));
                            return new HtmlString("<div style='padding:16px;background:#fee2e2;border:2px solid #dc2626;border-radius:8px;color:#991b1b;font-weight:700;font-size:15px;'>🚫 INTAKE BLOCKED — Triage Status: <b>{$ts}</b>. Client must be medically cleared before intake can proceed. Contact the triage nurse or duty officer.</div>");
                        }

                        $name     = $client->full_name ?? '—';
                        $uci      = $client->uci ?? '—';
                        $age      = $client->age ?? '—';
                        $dob      = $client->date_of_birth ? Carbon::parse($client->date_of_birth)->format('d M Y') : '—';
                        $gender   = ucfirst($client->gender ?? '—');
                        $phone    = $client->phone_primary ?? '—';
                        $sha      = $client->sha_number    ?? 'Not registered';
                        $ncpwd    = $client->ncpwd_number  ?? 'Not registered';
                        $ctype    = ucfirst(str_replace('_', '-', $client->client_type ?? 'new'));
                        $avatarBg = ($client->gender === 'female') ? '#ec4899' : '#3b82f6';
                        $ctypeBg  = match ($client->client_type) { 'returning' => '#3b82f6', 'old_new' => '#f59e0b', default => '#10b981' };
                        $initials = strtoupper(substr($client->first_name ?? '', 0, 1) . substr($client->last_name ?? '', 0, 1));
                        $risk     = ucfirst($triage?->risk_level ?? 'not triaged');
                        $riskBg   = match ($triage?->risk_level ?? '') { 'critical' => '#dc2626', 'high' => '#ea580c', 'medium' => '#d97706', default => '#16a34a' };
                        $tStatus  = ucfirst(str_replace('_', ' ', $triage?->triage_status ?? ''));
                        $presenting = $triage?->presenting_complaint ?? $triage?->reason_for_visit ?? '';

                        $vitals = '';
                        if ($triage) {
                            $bp   = ($triage->systolic_bp && $triage->diastolic_bp) ? "{$triage->systolic_bp}/{$triage->diastolic_bp} mmHg" : '—';
                            $hr   = $triage->heart_rate ? "{$triage->heart_rate} bpm" : '—';
                            $temp = $triage->temperature ? "{$triage->temperature}°C" : '—';
                            $spo2 = $triage->oxygen_saturation ? "{$triage->oxygen_saturation}%" : '—';
                            $pain = $triage->pain_scale !== null ? "{$triage->pain_scale}/10" : '—';
                            $vitals = '<div style="background:#f1f5f9;padding:8px 20px;border-top:1px solid #e2e8f0;display:flex;gap:20px;font-size:12px;color:#475569;flex-wrap:wrap;">
                                <span>BP: '.$bp.'</span><span>HR: '.$hr.'</span><span>Temp: '.$temp.'</span><span>SpO₂: '.$spo2.'</span><span>Pain: '.$pain.'</span>
                            </div>';
                        }

                        $presentingRow = $presenting ? '<div style="background:#fffbeb;padding:6px 20px;border-top:1px solid #fde68a;font-size:12px;color:#92400e;"><b>Presenting Reason (Triage):</b> '.e($presenting).'</div>' : '';

                        return new HtmlString('
                        <div style="border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);border:1px solid #e2e8f0;">
                          <div style="background:#1e293b;padding:16px 20px;display:flex;align-items:center;gap:16px;">
                            <div style="width:56px;height:56px;border-radius:50%;background:'.$avatarBg.';display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:white;flex-shrink:0;">'.$initials.'</div>
                            <div style="flex:1;">
                              <div style="font-size:18px;font-weight:700;color:white;">'.$name.'</div>
                              <div style="font-size:12px;color:#94a3b8;font-family:monospace;margin-top:2px;">'.$uci.' &nbsp;·&nbsp; DOB: '.$dob.'</div>
                              <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
                                <span style="background:'.$ctypeBg.';color:white;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;">'.$ctype.'</span>
                                <span style="background:'.$riskBg.';color:white;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;">Risk: '.$risk.'</span>
                                '.($tStatus ? '<span style="background:#475569;color:white;padding:2px 8px;border-radius:12px;font-size:11px;">'.$tStatus.'</span>' : '').'
                              </div>
                            </div>
                            <div style="text-align:right;color:#94a3b8;font-size:12px;">
                              <div>Visit</div>
                              <div style="color:#38bdf8;font-size:13px;font-weight:600;font-family:monospace;">'.$visit->visit_number.'</div>
                              <div style="margin-top:4px;font-size:11px;">'.now()->format('d M Y').'</div>
                            </div>
                          </div>
                          <div style="background:#f8fafc;padding:12px 20px;display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
                            <div><div style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:2px;">Age / Gender</div><div style="font-size:14px;font-weight:600;color:#1e293b;">'.$age.' yrs / '.strtoupper(substr($gender,0,1)).'</div></div>
                            <div><div style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:2px;">Phone</div><div style="font-size:13px;color:#1e293b;">'.$phone.'</div></div>
                            <div><div style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:2px;">SHA #</div><div style="font-size:13px;color:'.(($client->sha_number ?? null) ? '#0d9488' : '#94a3b8').';font-weight:'.($client->sha_number ? '600' : '400').'">'.$sha.'</div></div>
                          </div>
                          '.$presentingRow.'
                          '.$vitals.'
                        </div>');
                    }),
            ])->columnSpanFull(),

            // ══ B — IDENTIFICATION & CONTACT ═══════════════════════════════════════
            Forms\Components\Section::make('B — Client Identification & Contact Details')
                ->description('Confirm and update client contact and identification details')
                ->icon('heroicon-o-identification')
                ->columns(3)
                ->schema(self::sectionBSchema((int) request()->query('visit'))),

            // ══ C — DISABILITY ══════════════════════════════════════════════════════
            Forms\Components\Section::make('C — Disability & NCPWD Registration')
                ->icon('heroicon-o-heart')
                ->collapsible()
                ->columns(2)
                ->schema(self::sectionCSchema((int) request()->query('visit'))),

            // ══ D — SOCIO-DEMOGRAPHICS ══════════════════════════════════════════════
            Forms\Components\Section::make('D — Socio-Demographic Data')
                ->icon('heroicon-o-home')
                ->collapsible()
                ->collapsed(true)
                ->columns(2)
                ->schema(self::sectionDSchema((int) request()->query('visit'))),

            // ══ E — MEDICAL HISTORY & RELATED ═══════════════════════════════════════
            ...self::sectionESchema((int) request()->query('visit')),

            // ══ F — EDUCATION & OCCUPATION ══════════════════════════════════════════
            Forms\Components\Section::make('F — Education & Occupation Status')
                ->icon('heroicon-o-academic-cap')
                ->collapsible()
                ->collapsed(true)
                ->columns(2)
                ->schema(self::sectionFSchema((int) request()->query('visit'))),

            // ══ G — FUNCTIONAL SCREENING (age-band-specific) ════════════════════════
            Forms\Components\Section::make('G — Functional Screening')
                ->description('Answer Yes / No / Unsure for each item. Referral flags are auto-created on save.')
                ->icon('heroicon-o-chart-bar')
                ->collapsible()
                ->schema(self::sectionGSchema((int) request()->query('visit'))),

            // ══ H — REFERRAL & PRESENTING CONCERN ═══════════════════════════════════
            Forms\Components\Section::make('H — Referral Source & Presenting Concern')
                ->icon('heroicon-o-arrow-right-circle')
                ->columns(2)
                ->schema(self::sectionHSchema((int) request()->query('visit'))),

            // ══ I — SERVICE SIGNPOSTING ══════════════════════════════════════════════
            Forms\Components\Section::make('I — Service Signposting & Booking')
                ->description('Select clinical categories first, then choose primary and additional service postings. Bookings are PENDING — Payment Admin confirms after eligibility check.')
                ->icon('heroicon-o-queue-list')
                ->columns(1)
                ->schema(self::sectionISchema((int) request()->query('visit'))),

            // ══ J — PAYMENT PATHWAY ══════════════════════════════════════════════════
            Forms\Components\Section::make('J — Payment Pathway Preview')
                ->description('Eligibility screening only — no payment processed here. On save, visit is handed off to Payment Admin.')
                ->icon('heroicon-o-banknotes')
                ->collapsible()
                ->columns(3)
                ->schema(self::sectionJSchema((int) request()->query('visit'))),

            // ══ K — DEFERRAL ══════════════════════════════════════════════════════════
            Forms\Components\Section::make('K — Deferral & Closure')
                ->icon('heroicon-o-calendar-days')
                ->collapsible()
                ->collapsed(true)
                ->columns(2)
                ->schema(self::sectionKSchema((int) request()->query('visit'))),

            // ══ L — ASSESSMENT SUMMARY & AUDIT ═══════════════════════════════════════
            Forms\Components\Section::make('L — Assessment Summary, Audit & Completion')
                ->icon('heroicon-o-document-text')
                ->columns(2)
                ->schema(self::sectionLSchema((int) request()->query('visit'))),

        ]);
    }

    // ─── Table ─────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('visit.visit_number')
                    ->label('Visit #')->searchable()->sortable()->color('primary')->weight('semibold'),
                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')->searchable()->weight('semibold')
                    ->description(fn($r) => $r->client?->uci),
                Tables\Columns\TextColumn::make('verification_mode')->label('Type')->badge()
                    ->formatStateUsing(fn($s) => match($s) { 'new_client' => 'New', 'old_new_client' => 'Old-New', 'returning_client' => 'Returning', default => ucfirst($s ?? '—') })
                    ->color(fn($s) => match($s) { 'new_client' => 'success', 'old_new_client' => 'warning', 'returning_client' => 'primary', default => 'gray' }),
                Tables\Columns\TextColumn::make('priority_level')->label('Priority')->badge()
                    ->color(fn($s) => match((int)$s) { 1 => 'danger', 2 => 'warning', 3 => 'primary', default => 'gray' })
                    ->formatStateUsing(fn($s) => match((int)$s) { 1 => 'Urgent', 2 => 'High', 3 => 'Medium', 4 => 'Low', 5 => 'Routine', default => '—' }),
                Tables\Columns\TextColumn::make('created_at')->label('Assessed')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('open_editor')
                    ->label('Open Editor')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn(IntakeAssessment $record) => route(
                        'filament.admin.pages.intake-assessment-editor',
                        ['intakeId' => $record->id]
                    )),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListIntakeAssessments::route('/'),
            'create' => Pages\CreateIntakeAssessment::route('/create'),
            'view'   => Pages\ViewIntakeAssessment::route('/{record}'),
            'edit'   => Pages\EditIntakeAssessment::route('/{record}/edit'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin', 'intake_officer']);
    }
}
