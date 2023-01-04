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
            $fail("The selected task $value is invalid.");
        }

        if( Tasks::checkStatusForDeletion($value) ){
            $fail("The selected task id $value is in use in a collection.");
        }

        return true;
    }
}
