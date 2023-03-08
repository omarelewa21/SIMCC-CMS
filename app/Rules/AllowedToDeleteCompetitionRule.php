<?php

namespace App\Rules;

use App\Models\Competition;
use App\Models\Participants;
use Illuminate\Contracts\Validation\InvokableRule;

class AllowedToDeleteCompetitionRule implements InvokableRule
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
        $competition = Competition::findOrFail($value);
        $competitionOrganizationIds = $competition->competitionOrganization()
            ->pluck('competition_organization.id')->toArray();
        if(Participants::whereIn('competition_organization_id', $competitionOrganizationIds)->exists()){
            $fail(sprintf("Competition %s cannot be deleted because participants has been linked to this competition", $competition->name));
        }
    }
}
