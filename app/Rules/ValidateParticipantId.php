<?php

namespace App\Rules;

use App\Models\Competition;
use App\Models\Participants;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidateParticipantId implements ValidationRule
{
    protected User $user;

    public function __construct(protected Participants|null $participant = null, protected string $mode = 'update')
    {
        $this->user = auth()->user();
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if($this->user->hasRole(['admin', 'super admin'])) return;

        if(is_null($this->participant)) {
            $this->participant = Participants::with('competition', 'result')->findOrfail($value);
        }

        if($this->user->hasRole(['country partner', 'country partner assistant'])) {
            $this->validateForCountryPartner($fail);
        } elseif ($this->user->hasRole(['teacher', 'school manager'])) {
            $this->validateForTeacherOrSchoolManager($fail);
        }

        if($this->isRejectedDeleteAction()) {
            $fail("The selected participant has already marked and cannot be deleted");
        }
    }

    private function validateForCountryPartner($fail)
    {
        if (!$this->participantBelongsToCP()) {
            $fail("The selected participant is not in the same country or organization as current user.");
        }
        if($this->competitionOrganizationIsNotActive()) {
            $fail("Please enter the registration start date before making any changes to the participants.");
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

    private function isAcceptedDeleteAction(): bool
    {
        return $this->mode == 'delete'
            && $this->participant->competition->format === Competition::LOCAL
            && is_null($this->participant->result);
    }

    private function isRejectedDeleteAction(): bool
    {
        return !$this->isAcceptedDeleteAction();
    }
}
