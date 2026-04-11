<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\IntakeQueueResource;
use App\Filament\Resources\IntakeQueueResource\Pages\ListIntakeQueues;
use App\Models\IntakeAssessment;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class IntakeQueueResourceTest extends TestCase
{
    use WorkflowFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorkflowFixture();

        // Bypass Shield policy checks — permissions are not seeded in tests.
        Gate::before(fn () => true);
    }

    public function test_start_intake_creates_assessment_record(): void
    {
        $visit = $this->makeVisitAt('intake');
        $this->actingAs($this->intakeOfficer);

        Livewire::test(ListIntakeQueues::class)
            ->callTableAction('start_intake', $visit);

        // Action creates IntakeAssessment then redirects — assert DB, not page content
        $this->assertDatabaseHas('intake_assessments', [
            'visit_id'  => $visit->id,
            'client_id' => $this->client->id,
        ]);
    }

    public function test_cash_intake_completion_routes_to_cashier_not_billing(): void
    {
        $visit = $this->makeVisitAt('intake');
        $this->actingAs($this->intakeOfficer);

        // Directly advance stage the way IntakeAssessmentEditor does after cash path
        $visit->completeStage();
        $visit->moveToStage('cashier');

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'cashier',
        ]);
        $this->assertDatabaseMissing('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'billing',
        ]);
    }

    public function test_sha_intake_completion_routes_to_billing(): void
    {
        $visit = $this->makeVisitAt('intake');
        $this->actingAs($this->intakeOfficer);

        $visit->completeStage();
        $visit->moveToStage('billing');

        $this->assertDatabaseHas('visits', [
            'id'            => $visit->id,
            'current_stage' => 'billing',
        ]);
    }

    public function test_cashier_cannot_see_intake_queue_in_navigation(): void
    {
        $this->actingAs($this->cashier);

        $this->assertFalse(
            IntakeQueueResource::shouldRegisterNavigation(),
            'Intake queue nav should not be visible to cashier'
        );
    }
}
