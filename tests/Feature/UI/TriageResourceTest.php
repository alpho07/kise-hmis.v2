<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\TriageQueueResource;
use App\Filament\Resources\TriageResource\Pages\CreateTriage;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class TriageResourceTest extends TestCase
{
    use WorkflowFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorkflowFixture();

        // Bypass Shield policy checks — permissions are not seeded in tests.
        // shouldRegisterNavigation() (role gate) uses hasRole(), not Gate, so it is unaffected.
        Gate::before(fn () => true);
    }

    public function test_triage_nurse_submits_vitals_and_creates_triage_record(): void
    {
        $visit = $this->makeVisitAt('triage');
        $this->actingAs($this->triageNurse);

        Livewire::test(CreateTriage::class)
            ->fillForm([
                'visit_id'          => $visit->id,
                'client_id'         => $this->client->id,
                'systolic_bp'       => 120,
                'diastolic_bp'      => 80,
                'heart_rate'        => 72,
                'temperature'       => 36.6,
                'oxygen_saturation' => 98,
                'consciousness_level' => 'alert',
                'risk_level'        => 'low',
                'triage_status'     => 'cleared',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('triages', [
            'visit_id'    => $visit->id,
            'systolic_bp' => 120,
            'heart_rate'  => 72,
        ]);
    }

    public function test_completing_triage_advances_new_client_to_intake(): void
    {
        $visit = $this->makeVisitAt('triage');
        $this->actingAs($this->triageNurse);

        Livewire::test(CreateTriage::class)
            ->fillForm([
                'visit_id'            => $visit->id,
                'client_id'           => $this->client->id,
                'systolic_bp'         => 118,
                'diastolic_bp'        => 78,
                'heart_rate'          => 70,
                'temperature'         => 36.5,
                'oxygen_saturation'   => 98,
                'consciousness_level' => 'alert',
                'risk_level'          => 'low',
                'triage_status'       => 'cleared',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'intake',
        ]);
    }

    public function test_receptionist_cannot_see_triage_queue_in_navigation(): void
    {
        $this->actingAs($this->receptionist);

        $this->assertFalse(
            TriageQueueResource::shouldRegisterNavigation(),
            'Triage queue nav should not be visible to receptionist'
        );
    }
}
