<?php

namespace App\Rules;

use App\Models\Competition;
use App\Models\CompetitionOrganization;
use App\Models\CompetitionOrganizationDate;
use Illuminate\Contracts\Validation\Rule;

class CheckGlobalCompetitionStartDateAvail implements Rule
{
    protected $message =[];
    protected Competition $competition;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(Competition $competition)
    {
        $this->competition = $competition;
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
        $competition_id = $this->competition->id;
        $partnerCompetitonIds = CompetitionOrganization::where('competition_id',$competition_id)->pluck('id')->toArray();
        $count = CompetitionOrganizationDate::whereIn('competition_organization_id',$partnerCompetitonIds)->orderBy('competition_date')->count();
        if($count == 0) {
            return true;
        }
        $partnerCompetitonStartDate = date('Y-m-d', strtotime(CompetitionOrganizationDate::whereIn('competition_organization_id',$partnerCompetitonIds)->orderBy('competition_date')->first()->competition_date));
        $currentGlobalCompetitionStartDate = date('Y-m-d', strtotime($value));
        if($partnerCompetitonStartDate < $currentGlobalCompetitionStartDate) {

            $this->message = 'The selected global competition start date is in use by organization.';
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
