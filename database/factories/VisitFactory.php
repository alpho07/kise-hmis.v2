<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'branch_id'     => Branch::factory(),
            'client_id'     => Client::factory(),
            'visit_number'  => 'VST-' . now()->format('Ymd') . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'visit_date'    => now()->toDateString(),
            'visit_type'    => 'walk_in',
            'current_stage' => 'intake',
            'status'        => 'in_intake',
        ];
    }

    public function deferred(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deferred',
        ]);
    }
}
