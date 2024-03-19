<?php

namespace App\Rules;

use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use Illuminate\Contracts\Validation\InvokableRule;

class ParticipantIDOnUpdateRule implements InvokableRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function __invoke($attribute, $value, $fail)
    {
        if(auth()->user()->hasRole('country partner', 'country partner assistant')) 
            return $this->validateForCountryPartner($value, $fail);

        if(auth()->user()->hasRole('teacher', 'school manager'))
            return $this->validateForTeacherOrSchoolManager($value, $fail);
    }

    private function validateForCountryPartner($value, $fail)
    {
        if ($this->participantIsNotRelatedToCP($value)) {
            $fail("The selected participant is not in the same country or organization as current user.");
        }
        if($this->participantIsComputedAndNotEditable($value)) {
            $fail("The selected participant already have results and cannot be edited.");
        }
    }

    private function validateForTeacherOrSchoolManager($value, $fail)
    {
        $schoolId = auth()->user()->school_id;
        if (Participants::whereId($value)->where("school_id", $schoolId)->doesntExist()) {
            $fail("The selected participant is not in the same school as current user.");
        }
    }

    private function participantIsNotRelatedToCP($participantId): bool
    {
        $user = auth()->user();
        return Participants::join('competition_organization', 'participants.competition_organization_id', '=', 'competition_organization.id')
            ->where([
                'participants.id' => $participantId,
                'participants.country_id' => $user->country_id,
                'competition_organization.organization_id' => $user->organization_id,
                'competition_organization.status' => 'active'
            ])
            ->doesntExist();
    }

    private function participantIsComputedAndNotEditable($participantId): bool
    {
        return CompetitionParticipantsResults::whereRelation('participant', 'id', $participantId)
            ->whereNotNull('global_rank')
            ->exists();
    }
}
