<?php

namespace Tests\Feature;

use App\Filament\Pages\IntakeAssessmentEditor;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientDisability;
use App\Models\IntakeAssessment;
use App\Models\User;
use App\Models\Visit;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IntakeAssessmentEditorTest extends TestCase
{

    protected User $intakeOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist (permission tables are seeded via migration)
        Role::firstOrCreate(['name' => 'intake_officer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',          'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'receptionist',   'guard_name' => 'web']);

        // Create an intake officer with a branch so BelongsToBranch scope resolves
        $branch = Branch::factory()->create();
        $this->intakeOfficer = User::factory()->create(['branch_id' => $branch->id]);
        $this->intakeOfficer->assignRole('intake_officer');

        $this->actingAs($this->intakeOfficer);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /**
     * Create an IntakeAssessment whose client/visit share the
     * same branch as the acting user (avoids BelongsToBranch scope issues).
     */
    private function makeIntake(array $overrides = []): IntakeAssessment
    {
        $branch = $this->intakeOfficer->branch;
        $client = Client::factory()->create(['branch_id' => $branch->id]);
        $visit  = Visit::factory()->create([
            'branch_id' => $branch->id,
            'client_id' => $client->id,
        ]);

        return IntakeAssessment::factory()->create(array_merge([
            'branch_id'   => $branch->id,
            'client_id'   => $client->id,
            'visit_id'    => $visit->id,
            'assessed_by' => $this->intakeOfficer->id,
        ], $overrides));
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    /** Section A auto-completes when a client_id is set */
    public function test_mounts_with_section_A_complete_when_client_id_is_set(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionStatus.A', 'complete')
            ->assertSet('activeSection', 'A');
    }

    /** switchSection changes the active section */
    public function test_switches_sections(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->call('switchSection', 'D')
            ->assertSet('activeSection', 'D');
    }

    /** saveSectionData persists reason_for_visit to the DB */
    public function test_autosave_saveSectionData_updates_the_db(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.H.reason_for_visit', 'Speech delay concerns')
            ->call('saveSectionData', 'H');

        $this->assertSame(
            'Speech delay concerns',
            IntakeAssessment::find($intake->id)->reason_for_visit
        );
    }

    /** After filling reason_for_visit, section H status becomes complete */
    public function test_autosave_section_status_becomes_complete_when_required_fields_are_filled(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.H.reason_for_visit', 'Speech delay')
            ->call('saveSectionData', 'H')
            ->assertSet('sectionStatus.H', 'complete');
    }

    /** A non-intake role (receptionist) cannot access the page */
    public function test_blocks_access_for_non_intake_roles(): void
    {
        $branch = $this->intakeOfficer->branch;
        $receptionist = User::factory()->create(['branch_id' => $branch->id]);
        $receptionist->assignRole('receptionist');
        $this->actingAs($receptionist);

        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertStatus(403);
    }

    /** intakeId = 0 returns a 404 */
    public function test_returns_404_when_intakeId_is_0(): void
    {
        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => 0])
            ->assertStatus(404);
    }

    /** finalize() sends a danger notification when sections are incomplete */
    public function test_blocks_finalize_when_sections_are_incomplete(): void
    {
        $sections = ['A','B','C','D','E','F','G','H','I','J','K','L'];
        $intake   = $this->makeIntake([
            'section_status' => array_fill_keys($sections, 'incomplete'),
        ]);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->call('finalize')
            ->assertNotified("Cannot finalize");
    }

    /** finalize() sends a notification when visit is deferred */
    public function test_blocks_finalize_for_deferred_visits(): void
    {
        $sections = ['A','B','C','D','E','F','G','H','I','J','K','L'];
        $branch   = $this->intakeOfficer->branch;
        $client   = Client::factory()->create(['branch_id' => $branch->id]);
        $visit    = Visit::factory()->create([
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'status'    => 'deferred',
        ]);
        $intake = IntakeAssessment::factory()->create([
            'branch_id'      => $branch->id,
            'client_id'      => $client->id,
            'visit_id'       => $visit->id,
            'assessed_by'    => $this->intakeOfficer->id,
            'section_status' => array_fill_keys($sections, 'complete'),
        ]);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->call('finalize')
            ->assertNotified("Visit is deferred");
    }

    // ─── Section C — Disability Categories checkbox bug ──────────────────────

    /**
     * dis_disability_categories must start as an empty array, not null/true.
     * A non-array initial state causes PHP's loose in_array to match all options.
     */
    public function test_section_c_disability_categories_initialises_as_empty_array(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.C.dis_disability_categories', []);
    }

    /**
     * Legacy/live records may contain JSON false in disability_categories.
     * The editor must normalize that to [] before the checkbox list renders.
     */
    public function test_section_c_legacy_false_disability_categories_initialises_as_empty_array(): void
    {
        $intake = $this->makeIntake();

        ClientDisability::create([
            'client_id' => $intake->client_id,
            'is_disability_known' => true,
            'disability_categories' => false,
        ]);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.C.dis_disability_categories', []);
    }

    /**
     * dis_disability_categories must be preserved when the toggle is off.
     * Without ->dehydratedWhenHidden() the value was stripped to null when hidden,
     * causing Livewire to treat the next click as a boolean toggle → all boxes checked.
     */
    public function test_section_c_disability_categories_preserved_while_toggle_is_off(): void
    {
        $intake = $this->makeIntake();

        // Toggle starts OFF — categories still present as []
        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.C.dis_is_disability_known', false)
            ->assertSet('sectionData.C.dis_disability_categories', []);
    }

    /**
     * Selecting one disability category must produce a single-item array,
     * not [true] which PHP's loose in_array would match against every option.
     */
    public function test_section_c_ticking_one_checkbox_selects_only_that_option(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.C.dis_is_disability_known', true)
            ->set('sectionData.C.dis_disability_categories', ['hearing'])
            ->assertSet('sectionData.C.dis_disability_categories', ['hearing'])
            // Must NOT contain all options
            ->tap(function ($component) {
                $cats = $component->get('sectionData.C.dis_disability_categories');
                $this->assertIsArray($cats, 'categories must be an array');
                $this->assertCount(1, $cats, 'only one category should be selected');
                $this->assertContains('hearing', $cats);
            });
    }

    /**
     * After saving section C with one category, the DB must store only that value.
     */
    public function test_section_c_save_persists_single_category(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.C.dis_is_disability_known', true)
            ->set('sectionData.C.dis_disability_categories', ['visual'])
            ->call('saveSectionData', 'C');

        $disability = \App\Models\ClientDisability::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($disability);
        $this->assertSame(['visual'], $disability->disability_categories);
    }
}
