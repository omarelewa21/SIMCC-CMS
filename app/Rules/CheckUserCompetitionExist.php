<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use App\Models\User;
use App\Models\Competition;

class CheckUserCompetitionExist implements Rule, DataAwareRule
{

    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];
    private $countryId;

    // ...

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($countryId=null)
    {
        $this->countryId = $countryId;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */

//Rule::exists('users',"id")->where(function ($query) use($request,$requestCounter) {
//                $query->where('country_id', $request[$requestCounter]['country_id'])
//                    ->where('status', 'active');
//                $requestCounter += 1;
//            })

    public function passes($attribute, $value)
    {
        $rowNum = explode(".",$attribute)[1];

        if($this->countryId == null) {
            $countryId = $this->data['participant'][$rowNum]["country_id"] ?? null;
        }
        else{
            $countryId = $this->countryId;
        }

        $competitionId = ($this->data['participant'][$rowNum[0]]['competition_id']);
        $competitionStatus = Competition::find($competitionId)->status;

        $user = User::where([
            "id" => $value,
            "country_id" => $countryId
        ])->first();

        if(!$user) return false;

        if($user->parent) {
            if($user->parent->CompetitionOrganization->where("competition_id",$competitionId)->first() && $competitionStatus == 'active') return true;
        } else {
            if($user->CompetitionOrganization->where("competition_id",$competitionId)->first() && $competitionStatus == 'active') return true;
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'user not found or invalid';
    }
}
