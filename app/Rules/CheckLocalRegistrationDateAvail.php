<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;


class CheckLocalRegistrationDateAvail implements Rule,DataAwareRule
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
        $competitionFormat = $this->data['competition_format'];
        $globalRegistrationDate = date('Y-m-d', strtotime($this->data['global_registration_date']));
        $globalRegistrationEndDate = date('Y-m-d', strtotime($this->data['global_registration_end_date']));
        $localRegistrationDate = date('Y-m-d', strtotime($value));
        $latestCompetitionDate = date('Y-m-d', strtotime(collect($this->data['competition_dates'])->sortByDesc('date',)->first()));

        if($competitionFormat == 0) {
            if($localRegistrationDate > $latestCompetitionDate) {
                $this->message = 'The selected local registration date must be before or equal to the last competition date';
                return false;
            }
        }

        if($competitionFormat == 1) {
            if($localRegistrationDate <= $globalRegistrationEndDate) {
                $this->message = 'The selected local registration date must be before global registration end date';
                return false;
            }
        }

        if($localRegistrationDate < $globalRegistrationDate) {
            $this->message = 'The selected local registration date must be after global registration start date';
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
