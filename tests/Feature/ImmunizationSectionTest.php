<?php

namespace Tests\Feature;

use App\Filament\Resources\IntakeAssessmentResource;
use App\Filament\Resources\IntakeAssessmentResource\Pages\EditIntakeAssessment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientMedicalHistory;
use App\Models\Department;
use App\Models\IntakeAssessment;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImmunizationSectionTest extends TestCase
{

    protected User $intakeOfficer;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear static age cache so prior test classes don't poison Section E4 visibility.
        IntakeAssessmentResource::clearAgeCache();

        foreach (['intake_officer', 'admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->branch = Branch::factory()->create();
        $this->intakeOfficer = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->intakeOfficer->assignRole('intake_officer');

        $this->actingAs($this->intakeOfficer);
    }

    private function makeVisitWithYoungClient(): array
    {
        $client = Client::factory()->create([
            'branch_id'     => $this->branch->id,
            'date_of_birth' => Carbon::now()->subYears(8)->toDateString(),
        ]);
        $visit = Visit::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $client->id,
        ]);
        return compact('client', 'visit');
    }

    private function makeIntake(array $overrides = []): IntakeAssessment
    {
        ['client' => $client, 'visit' => $visit] = $this->makeVisitWithYoungClient();

        return IntakeAssessment::factory()->create(array_merge([
            'branch_id'        => $this->branch->id,
            'client_id'        => $client->id,
            'visit_id'         => $visit->id,
            'assessed_by'      => $this->intakeOfficer->id,
            'verification_mode'=> 'new_client',
        ], $overrides));
    }

    /** Returns the minimum required form data to pass Filament's form validation. */
    private function minimumRequiredFormData(int $serviceId): array
    {
        return [
            'verification_mode'       => 'new_client',
            'referral_source'         => ['general_practitioner'],
            'reason_for_visit'        => 'Routine follow-up',
            'assessment_summary'      => 'Test assessment summary for immunization section.',
            'expected_payment_method' => 'cash',
            'i_primary_service_id'    => $serviceId,
        ];
    }

    private function makeService(): Service
    {
        $dept = Department::firstOrCreate(
            ['name' => 'General'],
            ['code' => 'GEN', 'branch_id' => $this->branch->id, 'is_active' => true]
        );
        return Service::firstOrCreate(
            ['name' => 'General Assessment'],
            [
                'code'          => 'GEN-001',
                'department_id' => $dept->id,
                'base_price'    => 0,
                'is_active'     => true,
            ]
        );
    }

    // ── Bug-fix tests (critical — these prevent regressions) ──────────────────

    /** imm_epi_status initialises to [] (not null) on Edit — prevents "all checked" bug */
    public function test_epi_status_initialises_to_empty_array_on_edit(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(EditIntakeAssessment::class, ['record' => $intake->getRouteKey()])
            ->assertSet('data.imm_epi_status', []);
    }

    /** A live roundtrip triggered by another field (imm_epi_card_seen) must not corrupt
     *  imm_epi_status — it must remain [] not null/true. */
    public function test_epi_status_stays_array_after_live_roundtrip(): void
    {
        $intake = $this->makeIntake();

        Livewire::test(EditIntakeAssessment::class, ['record' => $intake->getRouteKey()])
            ->set('data.imm_epi_card_seen', 'yes')
            ->assertSet('data.imm_epi_status', []);
    }

    /** Previously saved vaccines load back into the form correctly on Edit. */
    public function test_saved_vaccines_load_back_into_form(): void
    {
        $intake = $this->makeIntake();

        ClientMedicalHistory::updateOrCreate(
            ['client_id' => $intake->client_id],
            ['immunization_records' => ['epi_status' => ['bcg', 'pentavalent', 'measles_rubella']]]
        );

        Livewire::test(EditIntakeAssessment::class, ['record' => $intake->getRouteKey()])
            ->assertSet('data.imm_epi_status', ['bcg', 'pentavalent', 'measles_rubella']);
    }

    // ── Persistence tests ─────────────────────────────────────────────────────

    /** Selected vaccines persist to ClientMedicalHistory.immunization_records on save. */
    public function test_selecting_vaccines_saves_to_immunization_records(): void
    {
        $intake  = $this->makeIntake();
        $service = $this->makeService();

        Livewire::test(EditIntakeAssessment::class, ['record' => $intake->getRouteKey()])
            ->set('data.verification_mode',       'new_client')
            ->set('data.referral_source',         ['general_practitioner'])
            ->set('data.reason_for_visit',        'Routine follow-up')
            ->set('data.assessment_summary',      'Test assessment summary.')
            ->set('data.expected_payment_method', 'cash')
            ->set('data.i_primary_service_id',    $service->id)
            ->set('data.imm_epi_status',          ['bcg', 'opv', 'pcv'])
            ->call('save');

        $med = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($med, 'ClientMedicalHistory was not created on save');
        $this->assertNotNull($med->immunization_records, 'immunization_records column is null; mutateFormDataBeforeSave may not have run. Raw: ' . json_encode($med->toArray()));
        $this->assertSame(['bcg', 'opv', 'pcv'], $med->immunization_records['epi_status'] ?? null);
    }

    /** "none" alone saves correctly to immunization_records. */
    public function test_none_only_selection_saves_correctly(): void
    {
        $intake  = $this->makeIntake();
        $service = $this->makeService();

        Livewire::test(EditIntakeAssessment::class, ['record' => $intake->getRouteKey()])
            ->set('data.verification_mode',       'new_client')
            ->set('data.referral_source',         ['general_practitioner'])
            ->set('data.reason_for_visit',        'Routine follow-up')
            ->set('data.assessment_summary',      'Test assessment summary.')
            ->set('data.expected_payment_method', 'cash')
            ->set('data.i_primary_service_id',    $service->id)
            ->set('data.imm_epi_status',          ['none'])
            ->call('save');

        $med = ClientMedicalHistory::where('client_id', $intake->client_id)->first();
        $this->assertNotNull($med, 'ClientMedicalHistory was not created on save');
        $this->assertSame(['none'], $med->immunization_records['epi_status'] ?? null);
    }
}

