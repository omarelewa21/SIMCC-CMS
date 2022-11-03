<?php

namespace App\Rules;

use App\Models\Collections;
use Illuminate\Contracts\Validation\Rule;
use App\Models\CompetitionLevels;
use App\Helpers\General\CollectionCompetitionStatus;

class CheckCollectionUse implements Rule
{
    protected $message;

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
        if(Collection::where('id',$value)->count() == 0) {
            $this->message = 'The selected collection id is invalid.';
            return false;
        }

        $activeCompetition = CollectionCompetitionStatus::CheckStatus($value,'active');

        if($activeCompetition > 0)
        {
            $this->message = 'The selected collection id is in use with active competition.';
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
