<?php

namespace App\Rules;

use App\Models\Participants;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\InvokableRule;

class ParticipantEmailRule implements DataAwareRule, InvokableRule
{
    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];
 
    // ...
 
    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;
 
        return $this;
    }


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
        $rowNum = explode(".", $attribute)[1];
        if (Participants::where('email', $value)->where('country_id', $this->data['participant'][$rowNum]['country_id'])->exists() ) {
            $fail("Participant $rowNum email is found, email must be unique for each country");
        }
    }
}
