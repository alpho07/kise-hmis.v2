<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'code'          => strtoupper($this->faker->unique()->lexify('???-###')),
            'name'          => $this->faker->unique()->words(3, true),
            'base_price'    => 500,
            'is_active'     => true,
        ];
    }
}
