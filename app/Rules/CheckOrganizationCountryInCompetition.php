<?php

namespace App\Rules;

use App\Models\CompetitionOrganization;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class CheckOrganizationCountryInCompetition implements Rule,DataAwareRule
{
    protected $message;
    protected $data;

    public function setData($data)
    {
        // TODO: Implement setData() method.
        $this->data = $data;
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
        $rowNum = explode(".",$attribute)[1];
        $competition_id = $this->data['competition_id'];
        $country_id = $this->data['organizations'][$rowNum]['country_id'];

        $found = CompetitionOrganization::where(['competition_id' => $competition_id,'organization_id' => $value, 'country_id' => $country_id])->count();

        if($found > 0) {
            $this->message = 'The selected organization\'s country already participated in the selected competition.';
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
