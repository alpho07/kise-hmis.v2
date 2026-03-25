<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'code'     => strtoupper($this->faker->unique()->lexify('???')),
            'name'     => $this->faker->company() . ' Branch',
            'type'     => 'main',
            'is_active' => true,
        ];
    }
}
