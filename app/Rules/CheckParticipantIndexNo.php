<?php

namespace App\Rules;

use App\Models\Participants;
use Illuminate\Contracts\Validation\Rule;

class CheckParticipantIndexNo implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        return Participants::where('index_no', $value)->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The selected :attribute is invalid. A user with that index_no does not exist.';
    }
}
