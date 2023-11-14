<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Helpers\AnswerUploadHelper; // Adjust the namespace based on your actual namespace

class ValidGradesForCompetition implements Rule
{
    private $competition;
    private $with_tasks;
    private $level;

    public function __construct($competition, $with_tasks)
    {
        $this->competition = $competition;
        $this->with_tasks = $with_tasks;
    }

    public function passes($attribute, $value)
    {
        $level = AnswerUploadHelper::getParticipantLevelByGrade($this->competition, $value);

        if ($level) {
            if ($this->with_tasks) {
                $level->tasks = $level->collection->sections()->pluck('tasks')
                    ->map(function ($taskCollection) {
                        return collect($taskCollection->toArray())->pluck('task_id')->flatten();
                    })->flatten()->sort();
            }
            $this->level = $level; // Store the level as a property
            return true;
        } else {
            return false;
        }
    }

    public function message()
    {
        return "The provided grade is invalid for the given competition.";
    }
    
    public function getLevel()
    {
        return $this->level;
    }
}