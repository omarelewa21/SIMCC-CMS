<?php

namespace App\Rules;

use App\Models\CompetitionLevels;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class CheckCompetitionLevelExist implements Rule
{
    protected $data;
    protected $message;
    protected $competition_id;


    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($competition_id)
    {
        $this->competition_id = $competition_id;
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
        $levelExist = CompetitionLevels::where('id',$value)->count();

        if($levelExist) {
            $competitionId = CompetitionLevels::find($value)->rounds->competition->id;

            if($competitionId === intVal($this->competition_id)) {
                return true;
            }
            else
            {
                $this->message = 'The selected level does not exist in the selected competition.';
            }
        } else {
            $this->message = 'The selected level id is invalid.';
        }

        return false;
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
