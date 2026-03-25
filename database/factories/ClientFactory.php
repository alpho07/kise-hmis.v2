<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'branch_id'    => Branch::factory(),
            'uci'          => 'KISE/A/' . str_pad($this->faker->unique()->numberBetween(1, 99999), 6, '0', STR_PAD_LEFT) . '/' . now()->year,
            'first_name'   => $this->faker->firstName(),
            'last_name'    => $this->faker->lastName(),
            'date_of_birth' => $this->faker->date('Y-m-d', '-5 years'),
            'gender'       => $this->faker->randomElement(['male', 'female']),
            'client_type'  => 'new',
            'is_active'    => true,
        ];
    }
}
