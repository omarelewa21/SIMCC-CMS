<?php

namespace App\Rules;

use App\Models\Collections;
use Illuminate\Contracts\Validation\Rule;
use App\Helpers\General\CollectionCompetitionStatus;

class CheckCollectionUse implements Rule
{
    protected string $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if( Collections::whereId($value)->doesntExist() ){
            $this->message = "The selected collection {$value} is invalid.";
            return false;
        }

        if( CollectionCompetitionStatus::CheckStatus($value, 'active') ){
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
