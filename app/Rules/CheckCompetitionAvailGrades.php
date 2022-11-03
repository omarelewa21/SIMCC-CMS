<?php

namespace App\Rules;

use App\Models\Competition;
use App\Models\CompetitionRounds;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Support\Facades\Route;

class CheckCompetitionAvailGrades implements Rule, DataAwareRule
{
    protected $allActiveCompetitionGrades;
    protected $data;
    protected $message;

    function setData($data)
    {
        $this->data = $data;
        // TODO: Implement setData() method.
    }

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $allCompetitionsGrades = Competition::where('status' , 'active')->get()->mapWithKeys(function ($item,$key) {
            return [$item['id'] => $item['allowed_grades']];
        })->toArray();

        switch(Route::currentRouteName()) {
            case "participant.create":
                $rowNum = explode(".",$attribute)[1];
                $competitionId = $this->data['participant'][$rowNum]['competition_id'];
            case "competition.create" :
            case "competition.rounds.add":
                $competitionId = $this->data['competition_id'];
                break;
            case "competition.rounds.edit":
                $competitionId = CompetitionRounds::find($this->data['id'])->competition_id;
                break;
        }

        if(!is_numeric($competitionId)) return true;

        if(!in_array($value,$allCompetitionsGrades[$competitionId])) {
            $this->message = "The selected grade does not exists in the selected competition";
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
