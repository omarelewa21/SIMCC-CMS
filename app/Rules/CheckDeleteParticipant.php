<?php

namespace App\Rules;

use App\Models\Participants;
use Illuminate\Contracts\Validation\Rule;

class CheckDeleteParticipant implements Rule
{
    protected $message;
    protected $role_id;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($role_id)
    {
        $this->role_id = $role_id;
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
        $participant = Participants::with(['competition_organization'])->where(['id' => $value,'status' => 'active',]);

        if($participant->count() > 0) {
            $participantOrganizationId = $participant->first()->competition_organization->organization_id;
            $participantCountryId = $participant->first()->country_id;
            $participantSchoolId = $participant->first()->school_id;

            switch($this->role_id) {
                case 0:
                case 1:
                    return true;
                    break;
                case 2:
                case 4:
                    if($participantOrganizationId == auth()->user()->organization_id && $participantCountryId == auth()->user()->country_id) {
                        return true;
                    }
                    break;
                case 3:
                case 5:
                    if($participantOrganizationId == auth()->user()->organization_id && $participantCountryId == auth()->user()->country_id && $participantSchoolId == auth()->user()->school_id) {
                        return true;
                    }
                    break;
            }

            $this->message = "The selected participant for delete is invalid.";
            return false;

        } else {
            $this->message = "The selected participant for delete is invalid.";
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
