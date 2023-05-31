<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company,
            'about' => $this->faker->paragraph,
            'address' => $this->faker->address,
            'postal' => $this->faker->postcode,
            'phone' => $this->faker->phoneNumber,
            'email' => $this->faker->email,
            'province' => 'SINGAPORE',
            'private' => 0,
            'status' => 'active',
            'created_by_userid' => 1,
        ];
    }
}
