<?php

namespace App\Rules;

use App\Models\Competition;
use App\Models\CompetitionOrganization;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use App\Models\User;
use App\Models\CompetitionOrganizationDate;
use App\Models\Participants;

class CheckUniqueIdentifierWithCompetitionID implements Rule, DataAwareRule
{
    protected $message = [];
    protected $data;
    protected $participant;

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
    public function __construct($participant)
    {
        $this->participant = $participant;
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
        if (!$this->participant) {
            $rowNum = explode(".", $attribute)[1];
            switch (auth()->user()->role_id) {
                case 0:
                case 1:
                    $competition_id = $this->data['participant'][$rowNum]['competition_id'];
                    break;
                case 2:
                case 3:
                case 4:
                case 5:
                    $competition_id = auth()->user()->competition_id;
                    break;
            }
        } else {
            $competition_id = $this->participant->competition()->id;
        }
        $competition_organization_ids = CompetitionOrganization::where(['competition_id' => $competition_id])->pluck('id');
        $q = Participants::whereIn('competition_organization_id', $competition_organization_ids)
            ->where('identifier', $value);
        if ($this->participant) {
            $q->where('id', '!=', $this->participant->id);
        }
        $found = $q->exists();
        if ($found) {
            $this->message = 'The identifier value must be unique to the competition.';
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
