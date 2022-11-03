<?php

namespace App\Rules;

use App\Models\CompetitionOrganization;
use App\Models\Participants;
use Illuminate\Contracts\Validation\Rule;

class CheckCompetitionEnded implements Rule
{
    protected $message = [];
    protected $createOrDelete;

    /**
     * Create a new rule instance.
     *1
     * @return void
     */
    public function __construct($createOrDelete)
    {
        $this->createOrDelete = $createOrDelete;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
//        if(in_array(auth()->user()->role_id,[0,1])) return true;

        if($this->createOrDelete == 'create') {
            $competitionId = $value;
        }

        if($this->createOrDelete == 'delete') {
           $competitionId = Participants::where('id',$value)->first()->competition_organization->competition->id;
        }

        $query = CompetitionOrganization::where(['competition_id' => $competitionId]);

        if($query->count() == 0){
            $this->message = 'The selected competition does not exist';
            return false;
        }

        $competitionEndDate = $query->first()->competition->competition_end_date;
        $todayDate = date('Y-m-d', strtotime('now'));

        if ($todayDate > $competitionEndDate) {
            $this->message = 'The selected competition has ended, adding or deleting participant is prohibited';
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
