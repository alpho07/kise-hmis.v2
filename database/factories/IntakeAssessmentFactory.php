<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Client;
use App\Models\IntakeAssessment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntakeAssessmentFactory extends Factory
{
    protected $model = IntakeAssessment::class;

    public function definition(): array
    {
        $branch = Branch::factory()->create();
        $client = Client::factory()->create(['branch_id' => $branch->id]);
        $user   = User::factory()->create(['branch_id' => $branch->id]);
        $visit  = Visit::factory()->create([
            'branch_id' => $branch->id,
            'client_id' => $client->id,
        ]);

        return [
            'visit_id'          => $visit->id,
            'client_id'         => $client->id,
            'branch_id'         => $branch->id,
            'assessed_by'       => $user->id,
            'verification_mode' => 'new_client',
            'data_verified'     => false,
            'is_editable'       => true,
            'section_status'    => null,
            'is_finalized'      => false,
        ];
    }
}
