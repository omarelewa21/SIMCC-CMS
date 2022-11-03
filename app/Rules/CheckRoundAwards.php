<?php

namespace App\Rules;

use App\Models\Competition;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

class CheckRoundAwards implements Rule
{
    protected $competition_id;
    protected $message;
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
        $roundNum = intVal(explode(".",$attribute)[3]);
        $rounds = Competition::with('rounds.roundsAwards')->find($this->competition_id)->rounds->get($roundNum);
        $awards_id = (Arr::pluck($rounds->toArray()['rounds_awards'],'id'));

        if(in_array($value,$awards_id)){
            return true;
        } else {
            $this->message = "The selected award id does not belongs to the corresponding round";
            return false;
        }

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
