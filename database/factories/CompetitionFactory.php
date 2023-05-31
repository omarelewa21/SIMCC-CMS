<?php

namespace Database\Factories;

use App\Models\Competition;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompetitionFactory extends Factory
{
    protected $model = Competition::class;

    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true),
            'global_registration_date' => Carbon::now()->subMonths(4),
            'global_registration_end_date' => Carbon::now()->addMonths(4),
            'competition_start_date' => Carbon::now()->subMonths(4),
            'competition_end_date' => Carbon::now()->addMonths(20),
            'competition_mode' => 2,
            'parent_competition_id' => null,
            'allowed_grades' => [1, 2, 3],
            'alias' => $this->faker->word,
            'format' => 'normal',
            'status' => 'active',
            'created_by_userid' => 1,
            'difficulty_group_id' => 1,
            'award_type' => 'static',
            'min_points' => 0,
            'default_award_name' => 'Participant'
        ];
    }
}
