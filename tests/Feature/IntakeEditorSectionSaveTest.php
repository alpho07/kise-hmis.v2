<?php

namespace Tests\Feature;

use App\Filament\Pages\IntakeAssessmentEditor;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientDisability;
use App\Models\ClientEducation;
use App\Models\ClientMedicalHistory;
use App\Models\ClientSocioDemographic;
use App\Models\FunctionalScreening;
use App\Models\IntakeAssessment;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Comprehensive save tests for IntakeAssessmentEditor.
 *
 * Philosophy: saveSectionData() reads from $this->sectionData[$section] directly
 * (not getState()) so conditionally-hidden fields are preserved and saved.
 * Each test sets sectionData directly and calls saveSectionData() to verify
 * persistence — including fields that are hidden in the UI under certain conditions.
 */
class IntakeEditorSectionSaveTest extends TestCase
{
    use RefreshDatabase;

    protected User $officer;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['intake_officer', 'admin', 'super_admin'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->branch  = Branch::factory()->create();
        $this->officer = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->officer->assignRole('intake_officer');
        $this->actingAs($this->officer);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeIntake(array $overrides = []): IntakeAssessment
    {
        $client = Client::factory()->create(['branch_id' => $this->branch->id]);
        $visit  = Visit::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $client->id,
        ]);

        return IntakeAssessment::factory()->create(array_merge([
            'branch_id'   => $this->branch->id,
            'client_id'   => $client->id,
            'visit_id'    => $visit->id,
            'assessed_by' => $this->officer->id,
        ], $overrides));
    }

    /** Young client (age < 18) for perinatal / immunization / feeding sections. */
    private function makeIntakeWithYoungClient(int $ageYears = 8): IntakeAssessment
    {
        $client = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears($ageYears)->toDateString(),
        ]);
        $visit = Visit::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $client->id,
        ]);

        return IntakeAssessment::factory()->create([
            'branch_id'   => $this->branch->id,
            'client_id'   => $client->id,
            'visit_id'    => $visit->id,
            'assessed_by' => $this->officer->id,
        ]);
    }

    // ─── Section B → C: NCPWD cross-section sync ──────────────────────────────

    /** On mount, client with an existing ncpwd_number auto-sets C's registered = 'yes'. */
    public function test_mount_infers_ncpwd_registered_yes_when_client_has_number(): void
    {
        $client = Client::factory()->create([
            'branch_id'    => $this->branch->id,
            'ncpwd_number' => 'NCPWD-AUTO',
        ]);
        $visit  = Visit::factory()->create(['branch_id' => $this->branch->id, 'client_id' => $client->id]);
        $intake = IntakeAssessment::factory()->create([
            'branch_id'   => $this->branch->id,
            'client_id'   => $client->id,
            'visit_id'    => $visit->id,
            'assessed_by' => $this->officer->id,
        ]);
        // No ClientDisability record exists yet (ncpwd_registered not yet explicitly set)

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.C.dis_ncpwd_registered', 'yes')
            ->assertSet('sectionData.C.dis_ncpwd_number', 'NCPWD-AUTO');
    }

    /**
     * Saving Section B with an NCPWD number mirrors it into sectionData.C immediately
     * so that when the user navigates to Section C the fields are pre-filled.
     */
    public function test_saving_section_B_with_ncpwd_number_syncs_to_section_C(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.B.verification_mode', 'new_client')
            ->set('sectionData.B.b_ncpwd_number', 'NCPWD-555999')
            ->call('saveSectionData', 'B')
            // sectionData.C must now reflect the synced values without a page reload
            ->assertSet('sectionData.C.dis_ncpwd_number', 'NCPWD-555999')
            ->assertSet('sectionData.C.dis_ncpwd_registered', 'yes')
            // verification_status left null — user must confirm it in Section C
            ->assertSet('sectionData.C.dis_ncpwd_verification_status', null);
    }

    /**
     * Clearing the NCPWD number in Section B resets C's registered flag to null
     * so the user is not left with stale 'yes' state.
     */
    public function test_clearing_ncpwd_number_in_section_B_resets_section_C(): void
    {
        $intake = $this->makeIntake();

        $component = Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id]);

        // First set a number
        $component
            ->set('sectionData.B.b_ncpwd_number', 'NCPWD-111')
            ->call('saveSectionData', 'B')
            ->assertSet('sectionData.C.dis_ncpwd_registered', 'yes');

        // Now clear it
        $component
            ->set('sectionData.B.b_ncpwd_number', null)
            ->call('saveSectionData', 'B')
            ->assertSet('sectionData.C.dis_ncpwd_number', null)
            ->assertSet('sectionData.C.dis_ncpwd_registered', null);
    }

    /**
     * If the user has already explicitly set C's registered to 'no' or 'unknown',
     * saving a number in B does NOT overwrite their explicit choice.
     */
    public function test_section_B_sync_does_not_overwrite_explicit_no_in_section_C(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            // User explicitly sets C to 'no' first
            ->set('sectionData.C.dis_ncpwd_registered', 'no')
            // Then enters a number in B (maybe testing)
            ->set('sectionData.B.b_ncpwd_number', 'NCPWD-999')
            ->call('saveSectionData', 'B')
            // The explicit 'no' must be preserved
            ->assertSet('sectionData.C.dis_ncpwd_registered', 'no');
    }

    // ─── Section B: ID & Contact ──────────────────────────────────────────────

    /** Basic contact fields persist to the clients table. */
    public function test_section_B_saves_contact_to_client(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.B.verification_mode', 'returning_client')
            ->set('sectionData.B.b_phone_primary', '0712345678')
            ->set('sectionData.B.b_phone_secondary', '0798765432')
            ->set('sectionData.B.b_national_id', '12345678')
            ->set('sectionData.B.b_primary_address', '123 Nairobi Ave')
            ->call('saveSectionData', 'B');

        $client = Client::find($intake->client_id);
        $this->assertSame('0712345678', $client->phone_primary);
        $this->assertSame('0798765432', $client->phone_secondary);
        $this->assertSame('12345678', $client->national_id);
        $this->assertSame('123 Nairobi Ave', $client->primary_address);
    }

    /** verification_mode and verification_notes persist to intake_assessments. */
    public function test_section_B_saves_verification_to_intake(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.B.verification_mode', 'returning_client')
            ->set('sectionData.B.verification_notes', 'ID card presented.')
            ->call('saveSectionData', 'B');

        $fresh = IntakeAssessment::find($intake->id);
        $this->assertSame('returning_client', $fresh->verification_mode);
        $this->assertSame('ID card presented.', $fresh->verification_notes);
    }

    /**
     * HIDDEN FIELD: b_email is shown only when b_preferred_communication === 'email'.
     * The email must still persist when the field was filled but is conditionally hidden
     * (i.e. user changed preferred_communication away from email after filling it).
     */
    public function test_section_B_saves_email_even_when_preferred_communication_changed(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.B.verification_mode', 'new_client')
            ->set('sectionData.B.b_email', 'test@example.com')
            // preferred_communication is NOT 'email' — b_email field would be hidden
            ->set('sectionData.B.b_preferred_communication', 'phone')
            ->call('saveSectionData', 'B');

        // email was in sectionData so it should have been passed to saveSectionB
        $client = Client::find($intake->client_id);
        $this->assertSame('test@example.com', $client->email);
    }

    // ─── Section C: Disability & NCPWD ────────────────────────────────────────

    /** Core disability fields persist when disability is known. */
    public function test_section_C_saves_disability_record(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.C.dis_is_disability_known', true)
            ->set('sectionData.C.dis_disability_categories', ['physical', 'hearing'])
            ->set('sectionData.C.dis_onset', 'congenital')
            ->set('sectionData.C.dis_level_of_functioning', 'moderate')
            ->set('sectionData.C.dis_disability_notes', 'Uses AFO brace.')
            ->call('saveSectionData', 'C');

        $dis = ClientDisability::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($dis);
        $this->assertSame(['physical', 'hearing'], $dis->disability_categories);
        $this->assertSame('congenital', $dis->onset);
        $this->assertSame('moderate', $dis->level_of_functioning);
    }

    /**
     * HIDDEN FIELD: dis_ncpwd_number is only visible when dis_is_disability_known
     * AND dis_ncpwd_registered === 'yes'.
     * The NCPWD number must still be saved to the client record when data is present.
     */
    public function test_section_C_saves_ncpwd_number_when_registered(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.C.dis_is_disability_known', true)
            ->set('sectionData.C.dis_disability_categories', ['physical'])
            ->set('sectionData.C.dis_ncpwd_registered', 'yes')
            ->set('sectionData.C.dis_ncpwd_number', 'NCPWD-123456')
            ->call('saveSectionData', 'C');

        $this->assertSame('NCPWD-123456', Client::find($intake->client_id)->ncpwd_number);
    }

    /**
     * FIXED: dis_ncpwd_registered (Radio) now persists to client_disabilities.ncpwd_registered
     * and reloads correctly on mount.
     */
    public function test_section_C_saves_and_loads_ncpwd_registered(): void
    {
        $intake = $this->makeIntake();

        // Save
        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.C.dis_is_disability_known', true)
            ->set('sectionData.C.dis_disability_categories', ['physical'])
            ->set('sectionData.C.dis_ncpwd_registered', 'yes')
            ->set('sectionData.C.dis_ncpwd_number', 'NCPWD-987654')
            ->set('sectionData.C.dis_ncpwd_verification_status', 'seen')
            ->call('saveSectionData', 'C');

        $dis = ClientDisability::where('client_id', $intake->client_id)->first();
        $this->assertSame('yes', $dis->ncpwd_registered);
        $this->assertSame('seen', $dis->ncpwd_verification_status);
        $this->assertSame('NCPWD-987654', Client::find($intake->client_id)->ncpwd_number);

        // Load — re-mount and verify sectionData hydrates correctly
        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.C.dis_ncpwd_registered', 'yes')
            ->assertSet('sectionData.C.dis_ncpwd_verification_status', 'seen')
            ->assertSet('sectionData.C.dis_ncpwd_number', 'NCPWD-987654');
    }

    /** dis_ncpwd_registered = 'no' is stored and reloaded (not just inferred from number). */
    public function test_section_C_saves_ncpwd_registered_no(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.C.dis_is_disability_known', true)
            ->set('sectionData.C.dis_disability_categories', ['hearing'])
            ->set('sectionData.C.dis_ncpwd_registered', 'no')
            ->call('saveSectionData', 'C');

        $dis = ClientDisability::where('client_id', $intake->client_id)->first();
        $this->assertSame('no', $dis->ncpwd_registered);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.C.dis_ncpwd_registered', 'no');
    }

    /** Section C is skipped (no DB write) when dis_is_disability_known is empty. */
    public function test_section_C_skips_save_when_disability_not_known(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.C.dis_is_disability_known', null)
            ->call('saveSectionData', 'C');

        $this->assertNull(ClientDisability::where('client_id', $intake->client_id)->first());
    }

    // ─── Section D: Socio-Demographics ────────────────────────────────────────

    /** Core socio fields persist to client_socio_demographics. */
    public function test_section_D_saves_socio_record(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.D.socio_marital_status', 'single')
            ->set('sectionData.D.socio_living_arrangement', 'with_family')
            ->set('sectionData.D.socio_household_size', 5)
            ->set('sectionData.D.socio_primary_language', 'kiswahili')
            ->set('sectionData.D.socio_source_of_support', ['family', 'ngo'])
            ->call('saveSectionData', 'D');

        $socio = ClientSocioDemographic::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($socio);
        $this->assertSame('single', $socio->marital_status);
        $this->assertSame('kiswahili', $socio->primary_language);
        $this->assertSame(['family', 'ngo'], $socio->source_of_support);
    }

    /**
     * FIXED: socio_marital_other now persists to marital_status_other and reloads.
     * marital_status stays as ENUM value 'other'; free text in separate column.
     */
    public function test_section_D_saves_and_loads_marital_other(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.D.socio_marital_status', 'other')
            ->set('sectionData.D.socio_marital_other', 'Cohabiting')
            ->set('sectionData.D.socio_primary_language', 'kiswahili')
            ->call('saveSectionData', 'D');

        $socio = ClientSocioDemographic::where('client_id', $intake->client_id)->first();
        $this->assertSame('other', $socio->marital_status);
        $this->assertSame('Cohabiting', $socio->marital_status_other);

        // Load round-trip
        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.D.socio_marital_status', 'other')
            ->assertSet('sectionData.D.socio_marital_other', 'Cohabiting');
    }

    /**
     * FIXED: socio_living_other now persists to living_arrangement_other and reloads.
     */
    public function test_section_D_saves_and_loads_living_other(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.D.socio_living_arrangement', 'other')
            ->set('sectionData.D.socio_living_other', 'Boarding school')
            ->set('sectionData.D.socio_primary_language', 'english')
            ->call('saveSectionData', 'D');

        $socio = ClientSocioDemographic::where('client_id', $intake->client_id)->first();
        $this->assertSame('other', $socio->living_arrangement);
        $this->assertSame('Boarding school', $socio->living_arrangement_other);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.D.socio_living_arrangement', 'other')
            ->assertSet('sectionData.D.socio_living_other', 'Boarding school');
    }

    /**
     * FIXED: socio_caregiver_other persists via 'other: <text>' in primary_caregiver column.
     * On reload, socio_primary_caregiver = 'other' and socio_caregiver_other = text.
     */
    public function test_section_D_saves_and_loads_caregiver_other(): void
    {
        $intake = $this->makeIntakeWithYoungClient(10);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.D.socio_primary_caregiver', 'other')
            ->set('sectionData.D.socio_caregiver_other', 'Step-parent')
            ->set('sectionData.D.socio_primary_language', 'english')
            ->call('saveSectionData', 'D');

        $socio = ClientSocioDemographic::where('client_id', $intake->client_id)->first();
        $this->assertSame('other: Step-parent', $socio->primary_caregiver);

        // Load round-trip: split back into enum key + free text
        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.D.socio_primary_caregiver', 'other')
            ->assertSet('sectionData.D.socio_caregiver_other', 'Step-parent');
    }

    /**
     * FIXED: socio_other_support maps to other_support_source column (was never wired).
     */
    public function test_section_D_saves_and_loads_other_support_source(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.D.socio_source_of_support', ['ngo', 'other'])
            ->set('sectionData.D.socio_other_support', 'Church sponsorship')
            ->set('sectionData.D.socio_primary_language', 'kiswahili')
            ->call('saveSectionData', 'D');

        $socio = ClientSocioDemographic::where('client_id', $intake->client_id)->first();
        $this->assertSame('Church sponsorship', $socio->other_support_source);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.D.socio_other_support', 'Church sponsorship');
    }

    /**
     * FIXED: socio_school_enrolled (Currently Enrolled in School/Programme?) now
     * persists to client_socio_demographics.school_enrolled and reloads.
     */
    public function test_section_D_saves_and_loads_school_enrolled(): void
    {
        $intake = $this->makeIntakeWithYoungClient(12);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.D.socio_school_enrolled', 'yes')
            ->set('sectionData.D.socio_primary_language', 'english')
            ->call('saveSectionData', 'D');

        $socio = ClientSocioDemographic::where('client_id', $intake->client_id)->first();
        $this->assertSame('yes', $socio->school_enrolled);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.D.socio_school_enrolled', 'yes');
    }

    /**
     * FIXED: primary_language 'other' round-trip — on reload, the select shows 'other'
     * and the free-text input shows the actual language name.
     */
    public function test_section_D_saves_other_language_free_text(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.D.socio_marital_status', 'single')
            ->set('sectionData.D.socio_primary_language', 'other')
            ->set('sectionData.D.socio_language_other', 'Somali')
            ->call('saveSectionData', 'D');

        $socio = ClientSocioDemographic::where('client_id', $intake->client_id)->first();
        $this->assertSame('other: Somali', $socio->primary_language);

        // Load round-trip: select shows 'other', text input shows 'Somali'
        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->assertSet('sectionData.D.socio_primary_language', 'other')
            ->assertSet('sectionData.D.socio_language_other', 'Somali');
    }

    // ─── Section E: Medical History ───────────────────────────────────────────

    /** Core medical conditions and medications save to client_medical_histories. */
    public function test_section_E_saves_medical_history(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['epilepsy', 'asthma'])
            ->set('sectionData.E.med_current_medications', 'Phenobarbitone 30mg daily')
            ->set('sectionData.E.med_surgical_history', 'Tonsillectomy 2022')
            ->call('saveSectionData', 'E');

        $med = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($med);
        $this->assertSame(['epilepsy', 'asthma'], $med->medical_conditions);
        $this->assertSame('Phenobarbitone 30mg daily', $med->current_medications);
    }

    /** family_history saves to intake_assessments (not client_medical_histories). */
    public function test_section_E_saves_family_history_to_intake(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['none'])
            ->set('sectionData.E.family_history', 'Mother has hypertension')
            ->call('saveSectionData', 'E');

        $this->assertSame(
            'Mother has hypertension',
            IntakeAssessment::find($intake->id)->family_history
        );
    }

    /**
     * HIDDEN FIELD: med_conditions_other is visible only when 'other' is in
     * med_medical_conditions. Its text must be merged into medical_conditions as
     * 'other: <text>' when present.
     */
    public function test_section_E_merges_other_condition_into_medical_conditions(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['epilepsy', 'other'])
            ->set('sectionData.E.med_conditions_other', 'Fragile X syndrome')
            ->call('saveSectionData', 'E');

        $med = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $this->assertContains('epilepsy', $med->medical_conditions);
        $this->assertContains('other: Fragile X syndrome', $med->medical_conditions);
    }

    /**
     * HIDDEN FIELD: allergy fields appear inside a Repeater (allergy_items).
     * They must persist even though the allergen detail sub-fields are hidden
     * until a category is selected.
     */
    public function test_section_E_saves_allergy_items(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['none'])
            ->set('sectionData.E.allergy_items', [
                ['allergen_category' => 'drug', 'allergen_names' => ['penicillin'], 'reaction' => 'Rash'],
            ])
            ->call('saveSectionData', 'E');

        $med = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $this->assertNotEmpty($med->allergies);
        $this->assertSame('drug', $med->allergies[0]['allergen_category']);
    }

    /**
     * HIDDEN FIELD (age-gated): perinatal history fields are only shown for clients
     * under 19. They must still be saved correctly for a young client.
     */
    public function test_section_E_saves_perinatal_history_for_young_client(): void
    {
        $intake = $this->makeIntakeWithYoungClient(5);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['none'])
            ->set('sectionData.E.peri_place_of_birth', 'hospital')
            ->set('sectionData.E.peri_mode_of_delivery', 'normal_vaginal')
            ->set('sectionData.E.peri_gestation_weeks', 38)
            ->set('sectionData.E.peri_birth_weight_kg', 3.2)
            ->call('saveSectionData', 'E');

        $med = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($med->perinatal_history);
        $this->assertSame('hospital', $med->perinatal_history['place_of_birth']);
        $this->assertSame('normal_vaginal', $med->perinatal_history['mode_of_delivery']);
        $this->assertEquals(38, $med->perinatal_history['gestation_weeks']);
    }

    /**
     * HIDDEN FIELD: peri_place_of_birth_other is visible only when
     * peri_place_of_birth === 'other'. Stored as 'other: <text>'.
     */
    public function test_section_E_saves_perinatal_other_place_of_birth(): void
    {
        $intake = $this->makeIntakeWithYoungClient(3);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['none'])
            ->set('sectionData.E.peri_place_of_birth', 'other')
            ->set('sectionData.E.peri_place_of_birth_other', 'Traditional birth attendant')
            ->call('saveSectionData', 'E');

        $med = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $this->assertSame('other: Traditional birth attendant', $med->perinatal_history['place_of_birth']);
    }

    /**
     * HIDDEN FIELD (age-gated): immunization fields are only shown for clients < 19.
     * Records must persist correctly.
     */
    public function test_section_E_saves_immunization_records(): void
    {
        $intake = $this->makeIntakeWithYoungClient(7);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['none'])
            ->set('sectionData.E.imm_epi_status', ['bcg', 'pentavalent'])
            ->set('sectionData.E.imm_epi_card_seen', 'yes')
            ->set('sectionData.E.imm_missed_doses', 'no')
            ->call('saveSectionData', 'E');

        $med = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($med->immunization_records);
        $this->assertSame(['bcg', 'pentavalent'], $med->immunization_records['epi_status']);
        $this->assertSame('yes', $med->immunization_records['epi_card_seen']);
    }

    /**
     * HIDDEN FIELD: imm_missed_doses_which is only shown when imm_missed_doses === 'yes'.
     * Must persist even though it is conditionally hidden otherwise.
     */
    public function test_section_E_saves_missed_doses_detail_when_present(): void
    {
        $intake = $this->makeIntakeWithYoungClient(6);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['none'])
            ->set('sectionData.E.imm_missed_doses', 'yes')
            ->set('sectionData.E.imm_missed_doses_which', 'Measles 2nd dose')
            ->call('saveSectionData', 'E');

        $med = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $this->assertSame('yes', $med->immunization_records['missed_doses']);
        $this->assertSame('Measles 2nd dose', $med->immunization_records['missed_doses_which']);
    }

    /**
     * HIDDEN FIELD (age-gated): feeding history is only visible for clients < 19.
     * All sub-fields must persist.
     */
    public function test_section_E_saves_feeding_history(): void
    {
        $intake = $this->makeIntakeWithYoungClient(2);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['none'])
            ->set('sectionData.E.feeding_method', 'breastfed')
            ->set('sectionData.E.feeding_diet_appetite', 'good')
            ->set('sectionData.E.feeding_swallowing_concerns', ['gagging'])
            ->set('sectionData.E.feeding_growth_concern', 'yes')
            ->call('saveSectionData', 'E');

        $med = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($med->feeding_history);
        $this->assertSame('breastfed', $med->feeding_history['feeding_method']);
        $this->assertSame(['gagging'], $med->feeding_history['swallowing_concerns']);
    }

    /**
     * HIDDEN FIELD: feeding_swallowing_concerns_other is visible only when 'other' is
     * selected in feeding_swallowing_concerns. Must be merged into the array as 'other: ...'.
     */
    public function test_section_E_merges_other_swallowing_concern(): void
    {
        $intake = $this->makeIntakeWithYoungClient(4);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['none'])
            ->set('sectionData.E.feeding_swallowing_concerns', ['gagging', 'other'])
            ->set('sectionData.E.feeding_swallowing_concerns_other', 'Frequent vomiting')
            ->call('saveSectionData', 'E');

        $med  = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $cons = $med->feeding_history['swallowing_concerns'];
        $this->assertContains('gagging', $cons);
        $this->assertContains('other: Frequent vomiting', $cons);
        // raw 'other' must be removed from the final array
        $this->assertNotContains('other', $cons);
    }

    /**
     * HIDDEN FIELD: e2_current_devices (repeater) visible only when e2_has_at === 'yes'.
     * AT devices must be saved to client_disabilities.assistive_technology.
     */
    public function test_section_E_saves_at_current_devices(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.E.med_medical_conditions', ['none'])
            ->set('sectionData.E.e2_has_at', 'yes')
            ->set('sectionData.E.e2_current_devices', [
                ['device_type' => 'mobility', 'device_name' => 'Manual wheelchair', 'source' => 'ngo'],
            ])
            ->call('saveSectionData', 'E');

        $dis = ClientDisability::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($dis);
        $this->assertNotEmpty($dis->assistive_technology);
        $this->assertSame('mobility', $dis->assistive_technology[0]['device_type']);
    }

    // ─── Section F: Education & Work ──────────────────────────────────────────

    /** Core education level and status saves to client_educations. */
    public function test_section_F_saves_education_record(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.F.edu_education_level', 'primary')
            ->set('sectionData.F.edu_school_type', 'special')
            ->set('sectionData.F.edu_school_name', 'Joytown Primary')
            ->set('sectionData.F.edu_currently_enrolled', 'yes')
            ->call('saveSectionData', 'F');

        $edu = ClientEducation::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($edu);
        $this->assertSame('primary', $edu->education_level);
        $this->assertSame('Joytown Primary', $edu->school_name);
        $this->assertTrue($edu->currently_enrolled);
    }

    /**
     * HIDDEN FIELD: edu_attendance_notes is visible only when enrolled === 'yes'
     * AND attendance_challenges === 'yes'. The notes must still be saved.
     */
    public function test_section_F_saves_attendance_notes_when_enrolled_with_challenges(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.F.edu_education_level', 'primary')
            ->set('sectionData.F.edu_currently_enrolled', 'yes')
            ->set('sectionData.F.edu_attendance_challenges', 'yes')
            ->set('sectionData.F.edu_attendance_notes', 'Often absent due to seizures')
            ->call('saveSectionData', 'F');

        $edu = ClientEducation::where('client_id', $intake->client_id)->first();
        $this->assertTrue($edu->attendance_challenges);
        $this->assertSame('Often absent due to seizures', $edu->attendance_notes);
    }

    /**
     * HIDDEN FIELD: edu_performance_notes is visible only when enrolled === 'yes'
     * AND performance_concern === 'yes'.
     */
    public function test_section_F_saves_performance_notes(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.F.edu_education_level', 'primary')
            ->set('sectionData.F.edu_currently_enrolled', 'yes')
            ->set('sectionData.F.edu_performance_concern', 'yes')
            ->set('sectionData.F.edu_performance_notes', 'Struggles with reading')
            ->call('saveSectionData', 'F');

        $edu = ClientEducation::where('client_id', $intake->client_id)->first();
        $this->assertTrue($edu->performance_concern);
        $this->assertSame('Struggles with reading', $edu->performance_notes);
    }

    // ─── Section G: Functional Screening ──────────────────────────────────────

    /** Overall summary persists to functional_screenings and screening scores to intake. */
    public function test_section_G_saves_functional_screening(): void
    {
        $intake = $this->makeIntakeWithYoungClient(8);

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.G.visit_id', $intake->visit_id)
            ->set('sectionData.G.func_overall_summary', 'Moderate functional limitations across mobility and communication.')
            // b7y band: provide answers for hearing domain
            ->set('sectionData.G.g_b7y_hearing_q1', 'yes')
            ->set('sectionData.G.g_b7y_hearing_q2', 'no')
            ->call('saveSectionData', 'G');

        $fs = FunctionalScreening::where('intake_assessment_id', $intake->id)->first();
        $this->assertNotNull($fs);
        $this->assertSame('Moderate functional limitations across mobility and communication.', $fs->overall_summary);

        $scores = IntakeAssessment::find($intake->id)->functional_screening_scores;
        $this->assertNotNull($scores);
        $this->assertArrayHasKey('band', $scores);
    }

    // ─── Section H: Presenting Concern ────────────────────────────────────────

    /** All H fields save to intake_assessments. */
    public function test_section_H_saves_all_fields(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.H.referral_source', ['hospital', 'self'])
            ->set('sectionData.H.referral_contact', 'Dr Jane Doe')
            ->set('sectionData.H.reason_for_visit', 'Hearing loss evaluation')
            ->set('sectionData.H.current_concerns', 'Parents noticed lack of response to sounds')
            ->set('sectionData.H.previous_interventions', 'None yet')
            ->call('saveSectionData', 'H');

        $fresh = IntakeAssessment::find($intake->id);
        $this->assertSame('Hearing loss evaluation', $fresh->reason_for_visit);
        $this->assertSame('Parents noticed lack of response to sounds', $fresh->current_concerns);
        $sr = $fresh->services_required;
        $this->assertSame('Dr Jane Doe', $sr['referral_contact']);
    }

    /**
     * HIDDEN FIELD: referral_source_other is visible only when 'other' is in
     * referral_source. The custom text must be merged into the referral_source array
     * as 'other: <text>' and raw 'other' removed.
     */
    public function test_section_H_merges_other_referral_source(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.H.referral_source', ['self', 'other'])
            ->set('sectionData.H.referral_source_other', 'Mosque community leader')
            ->set('sectionData.H.reason_for_visit', 'Speech delay')
            ->call('saveSectionData', 'H');

        $sr = IntakeAssessment::find($intake->id)->services_required;
        $this->assertContains('self', $sr['referral_source']);
        $this->assertContains('other: Mosque community leader', $sr['referral_source']);
        $this->assertNotContains('other', $sr['referral_source']);
    }

    // ─── Section I: Service Plan ───────────────────────────────────────────────

    /** Service IDs, categories, and priority save to services_required JSON. */
    public function test_section_I_saves_service_plan(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.I.i_primary_service_id', 42)
            ->set('sectionData.I.i_service_categories', ['audiology', 'physiotherapy'])
            ->set('sectionData.I.services_selected', [42, 55])
            ->set('sectionData.I.priority_level', 2)
            ->call('saveSectionData', 'I');

        $fresh = IntakeAssessment::find($intake->id);
        $sr = $fresh->services_required;
        $this->assertSame(42, $sr['primary_service_id']);
        $this->assertSame(['audiology', 'physiotherapy'], $sr['service_categories']);
        $this->assertSame(2, $fresh->priority_level);
    }

    // ─── Section J: Payment Pathway ────────────────────────────────────────────

    /** Payment method and flags save to services_required JSON. */
    public function test_section_J_saves_payment_pathway(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.J.expected_payment_method', 'sha')
            ->set('sectionData.J.sha_enrolled', true)
            ->set('sectionData.J.ncpwd_covered', false)
            ->set('sectionData.J.has_private_insurance', false)
            ->set('sectionData.J.payment_notes', 'SHA card number 12345')
            ->call('saveSectionData', 'J');

        $sr = IntakeAssessment::find($intake->id)->services_required;
        $this->assertSame('sha', $sr['payment_method']);
        $this->assertTrue($sr['sha_enrolled']);
        $this->assertSame('SHA card number 12345', $sr['payment_notes']);
    }

    // ─── Section K: Deferral ───────────────────────────────────────────────────

    /**
     * HIDDEN FIELDS: deferral_reason, deferral_notes, next_appointment_date are all
     * hidden until defer_client is toggled on. They must all persist to the visits
     * table when defer_client is true.
     */
    public function test_section_K_saves_deferral_to_visit(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.K.defer_client', true)
            ->set('sectionData.K.deferral_reason', 'financial')
            ->set('sectionData.K.deferral_notes', 'Family needs time to arrange funds')
            ->set('sectionData.K.next_appointment_date', '2026-04-15')
            ->call('saveSectionData', 'K');

        $visit = Visit::find($intake->visit_id);
        $this->assertSame('deferred', $visit->status);
        $this->assertSame('financial', $visit->deferral_reason);
        $this->assertSame('Family needs time to arrange funds', $visit->deferral_notes);
        $this->assertSame('2026-04-15', $visit->next_appointment_date->toDateString());
    }

    /**
     * HIDDEN FIELD: deferral_reason_other is visible only when deferral_reason === 'other'.
     * Must be stored as 'other: <text>' in the deferral_reason column.
     */
    public function test_section_K_saves_other_deferral_reason(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.K.defer_client', true)
            ->set('sectionData.K.deferral_reason', 'other')
            ->set('sectionData.K.deferral_reason_other', 'Client hospitalized')
            ->set('sectionData.K.next_appointment_date', '2026-05-01')
            ->call('saveSectionData', 'K');

        $visit = Visit::find($intake->visit_id);
        $this->assertSame('other: Client hospitalized', $visit->deferral_reason);
    }

    /** Section K does NOT change visit status when defer_client is false/empty. */
    public function test_section_K_does_not_defer_when_toggle_is_off(): void
    {
        $intake = $this->makeIntake();
        $originalStatus = Visit::find($intake->visit_id)->status;

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.K.defer_client', false)
            ->call('saveSectionData', 'K');

        $this->assertSame($originalStatus, Visit::find($intake->visit_id)->status);
    }

    // ─── Section L: Summary & Finalize ────────────────────────────────────────

    /** Summary, recommendations, priority and data_verified save to intake_assessments. */
    public function test_section_L_saves_summary_fields(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.L.assessment_summary', 'Client presents with moderate bilateral hearing loss.')
            ->set('sectionData.L.recommendations', 'Refer to audiology for hearing aids fitting.')
            ->set('sectionData.L.priority_level', 1)
            ->set('sectionData.L.data_verified', true)
            ->call('saveSectionData', 'L');

        $fresh = IntakeAssessment::find($intake->id);
        $this->assertSame('Client presents with moderate bilateral hearing loss.', $fresh->assessment_summary);
        $this->assertSame('Refer to audiology for hearing aids fitting.', $fresh->recommendations);
        $this->assertSame(1, $fresh->priority_level);
        $this->assertTrue($fresh->data_verified);
    }

    // ─── Cross-section: section status ────────────────────────────────────────

    /** Saving section B with verification_mode marks it complete. */
    public function test_section_B_becomes_complete_after_valid_save(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.B.verification_mode', 'new_client')
            ->call('saveSectionData', 'B')
            ->assertSet('sectionStatus.B', 'complete');
    }

    /** Saving section L with assessment_summary marks it complete. */
    public function test_section_L_becomes_complete_after_summary_filled(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.L.assessment_summary', 'Summary text')
            ->call('saveSectionData', 'L')
            ->assertSet('sectionStatus.L', 'complete');
    }

    /** Saving section L without assessment_summary stays in_progress. */
    public function test_section_L_stays_in_progress_when_summary_is_missing(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(IntakeAssessmentEditor::class, ['intakeId' => $intake->id])
            ->set('sectionData.L.assessment_summary', null)
            ->call('saveSectionData', 'L')
            ->assertSet('sectionStatus.L', 'in_progress');
    }
}
