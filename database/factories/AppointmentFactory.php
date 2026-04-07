<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'client_id'        => Client::factory(),
            'branch_id'        => Branch::factory(),
            'department_id'    => Department::factory(),
            'service_id'       => Service::factory(),
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '09:00:00',
            'appointment_type' => 'follow_up',
            'status'           => 'scheduled',
            'payment_status'   => 'pending',
            'created_by'       => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }

    public function today(): static
    {
        return $this->state(fn () => ['appointment_date' => today()->toDateString()]);
    }
}
