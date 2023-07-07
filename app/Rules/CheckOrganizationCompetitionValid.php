<?php

namespace App\Rules;

use App\Models\Competition;
use App\Models\CompetitionOrganization;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use App\Models\User;
use App\Models\CompetitionOrganizationDate;

class CheckOrganizationCompetitionValid implements Rule, DataAwareRule
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

        switch(auth()->user()->role_id) {
            case 0:
            case 1:
                $organization_id =$this->data['participant'][$rowNum]['organization_id'];
                $country_id =  $this->data['participant'][$rowNum]['country_id'];
                break;
            case 2 :
            case 3 :
            case 4 :
            case 5 :
                $organization_id = auth()->user()->organization_id;
                $country_id = auth()->user()->country_id;
                break;
        }

        $competitionOrganization = CompetitionOrganization::where(
            ['competition_id' => $value,'organization_id' => $organization_id,'country_id' => $country_id]
        )->first();

        if(!$competitionOrganization) {
            $this->message = 'The selected competition does not exist or not available for this organization.';
            return false;
        }

        switch ($competitionOrganization->status) {
            case 'active':
                return true;
                break;
            case 'ready':
                if(!auth()->user()->hasRole(['super admin', 'admin', 'country partner'])){
                    $this->message = "your organization is ready, you can't add new participants to this competittion. please ask the admin to change the status or to add on your behalf.";
                    return false;
                }
                break;
            case 'lock':
                if(!auth()->user()->hasRole(['super admin', 'admin'])){
                    $this->message = "your organization is locked, you can't add new participants to this competittion. please ask the admin to change the status or to add on your behalf.";
                    return false;
                }
                break;
            default:
                break;
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
