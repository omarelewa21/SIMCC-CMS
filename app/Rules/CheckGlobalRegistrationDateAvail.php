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
        $organizationRegistrationDate = CompetitionOrganization::where('competition_id',$competition_id)->orderBy('registration_open_date')->where('registration_open_date','>','0000-00-00 00:00:00'); //Retrieve Date that have been set by organization/partner
        $currentGlobalRegistrationOpenDate = date('Y-m-d', strtotime($value));
        $organizationRegistrationDateFound = $organizationRegistrationDate->count();

        if($organizationRegistrationDateFound > 0) { // if date found, compare it to the global registration date, if it's not within the range prompt error msg
            $organizationRegistrationDateFormatted = date('Y-m-d', strtotime($organizationRegistrationDate->first()->registration_open_date));

            if($organizationRegistrationDateFormatted < $currentGlobalRegistrationOpenDate) {
                $this->message = 'The selected global registration date is in use by organization';
                return false;
            }
        }

        return true; // When no valid organization/partner found or organization/partner date is within global registration date range return true;
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
