<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use App\Models\CompetitionOrganization;
use App\Models\Participants;
use Illuminate\Support\Str;

class CheckUniqueIdentifierWithCountryId implements Rule, DataAwareRule
{
    protected $message = [];
    protected $data;
    protected $participant;

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    public function __construct($participant)
    {
        $this->participant = $participant;
    }

    public function passes($attribute, $value)
    {
        if (is_null($value)) {
            return true;
        }

        $value = Str::lower($value);
        $rowNum = explode(".", $attribute)[1];
        $countryId = $this->getCountryId($rowNum);

        $participants = $this->data['participant'];

        $duplicateCount = 0;
        foreach ($participants as $index => $participant) {
            // Check for duplicates within the same country
            if (
                $index != $rowNum &&
                $participant['country_id'] == $countryId &&
                Str::lower($participant['identifier']) == $value
            ) {
                $duplicateCount++;
            }
        }

        // Check for duplicates in the database
        $found = Participants::where('country_id', $countryId)
            ->where('identifier', $value);

        if ($this->participant) {
            $found->where('id', '!=', $this->participant->id);
        }

        $found = $found->exists();

        if ($duplicateCount > 0 || $found) {
            $this->message = 'The identifier value must be unique within the same country.';
            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->message;
    }

    private function getCountryId($rowNum)
    {
        switch (auth()->user()->role_id) {
            case 0:
            case 1:
                return $this->data['participant'][$rowNum]['country_id'];
            case 2:
            case 3:
            case 4:
            case 5:
                return auth()->user()->country_id;
            default:
                return null;
        }
    }
}
