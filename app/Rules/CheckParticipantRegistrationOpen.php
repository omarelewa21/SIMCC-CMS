<?php

namespace App\Rules;

use App\Models\Competition;
use App\Models\CompetitionOrganization;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use App\Models\User;
use App\Models\CompetitionOrganizationDate;

class CheckParticipantRegistrationOpen implements Rule, DataAwareRule
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
        $todayDate = date('Y-m-d', strtotime('now'));

        $competition = Competition::findOrFail($value);
        $competitionFormat = $competition->format;
        $competitionGlobalEndDate =  $competition->global_registration_end_date;
        $closeDate = $competitionGlobalEndDate;

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

        $CompetitionOrganization = CompetitionOrganization::where(['competition_id' => $value,'organization_id' => $organization_id,'country_id' => $country_id])->firstOrFail();

        $organizationRegistrationDate = date('Y-m-d', strtotime($CompetitionOrganization->registration_open_date));

        if($competitionFormat == 0) {
            $competitionDates = CompetitionOrganizationDate::where('competition_organization_id',$CompetitionOrganization->id)->orderBy('competition_date','desc');
            $competitionDateCount = $competitionDates->count();

            if($competitionDateCount == 0){
                $this->message = 'Organization competition date not yet assigned, add competition dates before add particitpants';
                return false;
            }

            $competitionLastDate = date('Y-m-d', strtotime($competitionDates->first()->competition_date));
            $closeDate = $competitionLastDate;
        }

        if($todayDate < $organizationRegistrationDate) {
            $this->message = 'The registration is yet to open';
            return false;
        }

        if($todayDate > $closeDate) {
            $this->message = 'The registration is closed';
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
