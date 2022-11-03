<?php

namespace App\Rules;

use App\Models\Participants;
use Illuminate\Contracts\Validation\Rule;

class CheckParticipantDeleteExpire implements Rule
{
    protected $messages;
    protected $days;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($days)
    {
        $this->days = $days;
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
        if(in_array(auth()->user()->role_id,[0,1])) return true;

        $participant = Participants::where('id',$value)->first();
        $restrictDeleteDate = date_add($participant->created_at,date_interval_create_from_date_string($this->days . " days"));
        $todayDate = date('Y-m-d', strtotime('now'));

        if($todayDate > $restrictDeleteDate) {
            $this->message = 'The selected participant could not be delete after 1 week of creation';
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
