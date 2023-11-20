<?php

namespace App\Rules;

use App\Models\Participants;
use Illuminate\Contracts\Validation\Rule;

class CheckParticipantIndexNoUniqueIdentifier implements Rule
{
    protected $participants;
    protected $message;

    public function __construct($participants)
    {
        $this->participants = $participants;
    }

    public function passes($attribute, $value)
    {
        // Validate uniqueness of identifier across participants in the payload

        $payloadIdentifiers = collect($this->participants)->pluck('identifier');

        $duplicates = $payloadIdentifiers->duplicates();
        if ($duplicates->count() > 0 && $duplicates->contains($value)) {
            $this->message = 'The identifier value must be unique among participants in the payload.';
            return false;
        }

        // Validate uniqueness of identifier within the specified index numbers

        foreach ($this->participants as $payLoadParticipant) {
            $participant = Participants::where('index_no', $payLoadParticipant['index_no'])->first();

            if ($participant->identifier == $payLoadParticipant['identifier']) {
                continue;
            }

            // Use CheckUniqueIdentifierWithCompetitionID rule for other cases
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
