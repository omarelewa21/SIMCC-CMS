<?php

namespace Database\Factories;

use App\Models\CompetitionOrganization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompetitionOrganizationFactory extends Factory
{
    protected $model = CompetitionOrganization::class;

    public function definition()
    {
        return [
            'registration_open_date' => '2022-10-11 00:00:00',
            'edit_sessions' => 0,
            'competition_mode' => 0,
            'translate' => $this->faker->text,
            'created_by_userid' => 1,
            'approved_by_userid' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'status' => 'active'
        ];
    }
}
