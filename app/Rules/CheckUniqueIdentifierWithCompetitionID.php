<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use App\Models\CompetitionOrganization;
use App\Models\Participants;
use Illuminate\Support\Str;

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
        $competitionId = $this->getCompetitionId($rowNum);
        $countryId = $this->getCountryId($rowNum);

        $participants = $this->data['participant'];

        $duplicateCount = 0;
        foreach ($participants as $index => $participant) {
            // Check for duplicates within the same competition and country
            if (
                $index != $rowNum &&
                $participant['competition_id'] == $competitionId &&
                $participant['country_id'] == $countryId &&
                Str::lower($participant['identifier']) == $value
            ) {
                $duplicateCount++;
            }
        }

        // Check for duplicates in the database
        $found = Participants::whereIn('competition_organization_id', $this->getCompetitionOrganizationIds($competitionId))
            ->where('identifier', $value);

        if ($this->participant) {
            $found->where('id', '!=', $this->participant->id);
        }

        $found = $found->exists();

        if ($duplicateCount > 0 || $found) {
            $this->message = 'The identifier value must be unique to the competition and country.';
            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->message;
    }

    private function getCompetitionId($rowNum)
    {
        switch (auth()->user()->role_id) {
            case 0:
            case 1:
                return $this->data['participant'][$rowNum]['competition_id'];
            case 2:
            case 3:
            case 4:
            case 5:
                return auth()->user()->competition_id;
            default:
                return null;
        }
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

    private function getCompetitionOrganizationIds($competitionId)
    {
        return CompetitionOrganization::where(['competition_id' => $competitionId])->pluck('id');
    }
}
