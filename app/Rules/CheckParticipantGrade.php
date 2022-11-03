<?php

namespace App\Rules;

use App\Models\Participants;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Support\Arr;

class CheckParticipantGrade implements Rule,DataAwareRule
{
    protected $data;
    protected $message;


    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

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
        $temp = Participants::with(['competition_organization.competition'])->find($this->data['id'])->toArray();
        $grades = Arr::pull($temp, 'competition_organization.competition')['allowed_grades'];

        if(!in_array($value,$grades)) {
            $this->message = 'Selected grade does not exists in competition';
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
