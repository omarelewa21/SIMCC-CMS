<?php

namespace App\Rules;

use App\Models\CompetitionOrganization;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Support\Arr;

class CheckOrganizationCountryInCompetition implements Rule, DataAwareRule
{
    protected $message;
    protected $data;

    public function setData($data)
    {
        $this->data = $data;
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
        $rowNum = explode(".", $attribute)[1];
        $organization_id = $this->data['organizations'][$rowNum]['organization_id'];

        if(
            collect($this->data['organizations'])->filter(
                fn($organization)=> $organization['organization_id'] === $organization_id
            )->count() > 2
        ){
            $this->message = 'You have selected same organization ';
            $this->message = 'The selected organization\'s country already participated in the selected competition.';
        }

        if($found > 0) {
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
