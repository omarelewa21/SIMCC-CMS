<?php

namespace Database\Factories;

use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company,
            'person_incharge' => $this->faker->name,
            'address' => $this->faker->address,
            'billing_address' => $this->faker->address,
            'mailing_address' => $this->faker->address,
            'email' => $this->faker->email,
            'logo' => null,
            'phone' => $this->faker->phoneNumber,
            'created_by_userid' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'status' => 'active',
        ];
    }
}
