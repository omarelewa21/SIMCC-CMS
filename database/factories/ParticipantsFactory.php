<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\CompetitionOrganization;
use App\Models\CompetitionOrganizationDate;
use App\Models\Countries;
use App\Models\Organization;
use App\Models\Participants;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParticipantsFactory extends Factory
{
    protected $model = Participants::class;

    public function definition()
    {
        return [
            'index_no' => $this->faker->unique()->randomNumber(6),
            'grade' => 1,
            'name' => $this->faker->name,
            'class' => 'NA',
            'email' => $this->faker->email,
            'tuition_centre_id' => null,
        ];
    }
}
