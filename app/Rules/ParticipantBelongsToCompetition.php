<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Competition;

class ParticipantBelongsToCompetition implements Rule
{
    private $competitionId;
    private $attribute;

    public function __construct($competitionId)
    {
        $this->competitionId = $competitionId;
    }

    public function passes($attribute, $value)
    {
        $registeredParticipants = Competition::find($this->competitionId)
            ->participants()
            ->pluck('index_no')
            ->toArray();

        return in_array($value, $registeredParticipants);
    }

    public function message()
    {
        return "Participant not registered for this competition.";
    }
}
