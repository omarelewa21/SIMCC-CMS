<?php

namespace App\Rules;

use App\Models\Participants;
use Illuminate\Contracts\Validation\Rule;
use App\Rules\CheckUniqueIdentifierWithCompetitionID; // Add this import if needed

class CheckParticipantIndexNoUniqueIdentifier implements Rule
{
    protected $indexNos;
    protected $message;

    public function __construct($indexNos)
    {
        $this->indexNos = $indexNos;
    }

    public function passes($attribute, $value)
    {
        $participants = Participants::whereIn('index_no', $this->indexNos)->get();

        foreach ($participants as $participant) {
            $uniqueIdentifierRule = new CheckUniqueIdentifierWithCompetitionID($participant);
            if (!$uniqueIdentifierRule->passes($attribute, $value)) {
                $this->message = $uniqueIdentifierRule->message();
                return false;
            }
        }

        return true;
    }

    public function message()
    {
        return ':attribute is invalid' . ($this->message ? ': ' . $this->message : '');
    }
}
