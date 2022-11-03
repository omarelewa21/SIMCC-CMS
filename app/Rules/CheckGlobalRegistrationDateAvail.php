<?php

namespace App\Rules;

use App\Models\CompetitionOrganization;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class CheckGlobalRegistrationDateAvail implements Rule,DataAwareRule
{
    protected $data = [];
    protected $message =[];

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
        $competition_id = $this->data['id'];
        $organizationRegistrationDate = date('Y-m-d', strtotime(CompetitionOrganization::where('competition_id',$competition_id)->orderBy('registration_open_date')->first()->registration_open_date));

        if("1970-01-01" == $organizationRegistrationDate) {
            return true;
        }
        $currentGlobalRegistrationOpenDate = date('Y-m-d', strtotime($value));
        if($organizationRegistrationDate < $currentGlobalRegistrationOpenDate) {
            $this->message = 'The selected global registration date is in use by organization';
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
