<?php

namespace App\Rules;

use App\Models\CompetitionOrganization;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use App\Models\Participants;

class CheckUniqueIdentifierWithCompetitionID implements Rule, DataAwareRule
{
    protected $message = 'The identifier value must be unique to the competition.';
    protected $data;
    protected $participant;

    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function __construct($participant = null)
    {
        $this->participant = $participant;
    }

    public function passes($attribute, $value)
    {
        if (is_null($value)) return true;

        $rowNum = explode(".", $attribute)[1];
        $competition_id = $this->participant
            ? $this->participant->competition->id
            : $this->data['participant'][$rowNum]['competition_id'];

        $competition_organization_ids = CompetitionOrganization::where('competition_id', $competition_id)->pluck('id');

        $query = Participants::whereIn('competition_organization_id', $competition_organization_ids)
            ->where('identifier', $value);

        if ($this->participant) {
            $query->where('id', '!=', $this->participant->id);
        }

        return !$query->exists();
    }

    public function message()
    {
        return $this->message;
    }
}
