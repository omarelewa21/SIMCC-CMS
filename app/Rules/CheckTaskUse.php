<?php

namespace App\Rules;

use App\Models\Tasks;
use Illuminate\Contracts\Validation\InvokableRule;

class CheckTaskUse implements InvokableRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function __invoke($attribute, $value, $fail)
    {
        if( Tasks::whereId($value)->doesntExist() ){
            $this->message = "The selected task {$value} is invalid.";
            return false;
        }

        if( Tasks::checkStatusForDeletion($value, 'active') ){
            $this->message = 'The selected task id is in use with active competition.';
            return false;
        }

        return true;
    }
}
