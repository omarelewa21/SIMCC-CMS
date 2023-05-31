<?php

namespace Database\Factories;

use App\Models\CompetitionOrganizationDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompetitionOrganizationDateFactory extends Factory
{
    protected $model = CompetitionOrganizationDate::class;

    public function definition()
    {
        return [
            'created_by_userid' => 1,
            'competition_date' => $this->faker->dateTimeBetween('+4 month', '+20 months'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
