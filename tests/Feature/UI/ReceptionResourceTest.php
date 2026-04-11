<?php

namespace Tests\Feature\UI;

use App\Filament\Resources\ReceptionResource;
use App\Filament\Resources\ReceptionResource\Pages\CreateReception;
use App\Filament\Resources\ReceptionResource\Pages\ListReceptions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Support\WorkflowFixture;
use Tests\TestCase;

class ReceptionResourceTest extends TestCase
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

    public function test_receptionist_can_load_reception_list(): void
    {
        $this->actingAs($this->receptionist);

        Livewire::test(ListReceptions::class)
            ->assertSuccessful();
    }

    public function test_create_form_saves_visit_at_reception_stage(): void
    {
        $this->actingAs($this->receptionist);

        Livewire::test(CreateReception::class)
            ->fillForm([
                'client_id'         => $this->client->id,
                'visit_type'        => 'walk_in',
                'service_available' => 'yes',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('visits', [
            'client_id'     => $this->client->id,
            'current_stage' => 'reception',
            'visit_type'    => 'walk_in',
        ]);

        $visit = \App\Models\Visit::where('client_id', $this->client->id)->first();
        $this->assertNotNull($visit);

        $this->assertDatabaseHas('visit_stages', [
            'visit_id' => $visit->id,
            'stage'    => 'reception',
        ]);
    }

    public function test_cashier_cannot_access_reception_resource(): void
    {
        $this->actingAs($this->cashier);

        $this->assertFalse(
            ReceptionResource::shouldRegisterNavigation(),
            'Reception nav should not be visible to cashier'
        );
    }
}
