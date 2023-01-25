<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class CheckOrganizationCountryPartnerExist implements Rule, DataAwareRule
{
    protected $data;
    protected $message;

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
        $found = User::where([
                'organization_id'   => $organization_id,
                'country_id'        => $value,
                'role_id'           => 2,
                'status'            => 'active'
            ])->exists();

        if(!$found) {
            $this->message = "The selected organization does not have country partner in the selected country.";
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
