<?php

namespace App\Rules;

use App\Models\Participants;
use App\Models\User;
use Illuminate\Contracts\Validation\InvokableRule;

class ParticipantIDOnUpdateRule implements InvokableRule
{
    private Participants $participant;
    private User $user;

    public function __construct(Participants $participant)
    {
        $this->participant = $participant->load('competition_organization', 'result');
        $this->user = auth()->user();
    }

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
        if($this->user->hasRole(['admin', 'super admin'])) return;

        if($this->user->hasRole(['country partner', 'country partner assistant'])) {
            $this->validateForCountryPartner($fail);
        } elseif ($this->user->hasRole(['teacher', 'school manager'])) {
            $this->validateForTeacherOrSchoolManager($fail);
        }

        // if($this->participantIsComputedAndNotEditable()) {
        //     $fail("The selected participant already have results and cannot be edited.");
        // }
    }

    private function validateForCountryPartner($fail)
    {
        if (!$this->participantBelongsToCP()) {
            $fail("The selected participant is not in the same country or organization as current user.");
        }
        if($this->competitionOrganizationIsNotActive()) {
            $fail("You have to enter registration start date first before making changes to participants.");
        }
    }

    private function participantBelongsToCP(): bool
    {
        return $this->participant->country_id === $this->user->country_id
            && $this->participant->competition_organization->organization_id === $this->user->organization_id;
    }

    private function competitionOrganizationIsNotActive(): bool
    {
        return $this->participant->competition_organization->status !== 'active';
    }

    private function validateForTeacherOrSchoolManager($fail)
    {
        if ($this->participant->school_id !== $this->user->school_id) {
            $fail("The selected participant is not in the same school as current user.");
        }
    }

    private function participantIsComputedAndNotEditable(): bool
    {
        return !is_null($this->participant->result?->global_rank);
    }
}
