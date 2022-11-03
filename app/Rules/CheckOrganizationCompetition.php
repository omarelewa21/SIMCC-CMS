<?php

namespace App\Rules;

use App\Models\Competition;
use App\Models\CompetitionOrganization;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use App\Models\User;
use App\Models\CompetitionOrganizationDate;

class CheckOrganizationCompetition implements Rule, DataAwareRule
{
    protected $message =[];
    protected $data;

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Create a new rule instance.
     *1
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
        $rowNum = explode(".",$attribute)[1];
        $competition_id = $value;

        switch(auth()->user()->role_id) {
            case 0:
            case 1:
                $organization_id =$this->data['participant'][$rowNum]['organization_id'];
                $country_id =  $this->data['participant'][$rowNum]['country_id'];
            case 2 :
            case 3 :
            case 4 :
            case 5 :
                $organization_id = auth()->user()->organization_id;
                $country_id = auth()->user()->country_id;
                break;
        }

        $found = CompetitionOrganization::where(['competition_id' => $competition_id,'organization_id' => $organization_id,'country_id' => $country_id])->count();

        if(!$found) {
            $this->message = 'The selected competition does not exist or not available for this organization.';
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
