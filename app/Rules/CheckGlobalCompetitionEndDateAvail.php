<?php

namespace App\Rules;

use App\Models\Competition;
use App\Models\CompetitionOrganization;
use App\Models\CompetitionOrganizationDate;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class CheckGlobalCompetitionEndDateAvail implements Rule
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
        $partnerCompetitonEndDate = date('Y-m-d', strtotime(CompetitionOrganizationDate::whereIn('competition_organization_id',$partnerCompetitonIds)->orderBy('competition_date','desc')->first()->competition_date));
        $currentGlobalCompetitionEndDate = date('Y-m-d', strtotime($value));

        if($partnerCompetitonEndDate > $currentGlobalCompetitionEndDate) {

            $this->message = 'The selected global competition end date is in use by partner.';
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
