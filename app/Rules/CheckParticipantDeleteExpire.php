<?php

namespace App\Rules;

use App\Models\Competition;
use App\Models\Participants;
use Illuminate\Contracts\Validation\InvokableRule;

class CheckParticipantDeleteExpire implements InvokableRule
{

    protected int $days;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(int $days)
    {
        $this->days = $days;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure  $fail
     * @return void
     */
    public function __invoke($attribute, $value, $fail)
    {
        if(Auth()->user()->hasRole('Super Admin', 'Admin')) return;

        $participant = Participants::where('id', $value)->with('competition_organization.competition')->first();

        if($participant->competition_organization->competition->format === Competition::LOCAL){
            $restrictDeleteDate = date_add($participant->created_at, date_interval_create_from_date_string($this->days . " days"));
            $todayDate = date('Y-m-d', strtotime('now'));
            if($todayDate > $restrictDeleteDate) {
                $fail('The selected participant could not be delete after 1 week of creation');
            }
        }
    }
}
