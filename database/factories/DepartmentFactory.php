<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code'      => strtoupper($this->faker->unique()->lexify('???')),
            'name'      => $this->faker->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
