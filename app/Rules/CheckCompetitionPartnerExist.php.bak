<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use App\Models\User;
use App\Models\Competition;

class CheckCompetitionPartnerExist implements Rule, DataAwareRule
{

    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];
    public $countryId;

    // ...

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {

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
        $rowNum = explode(".",$attribute)[0];

        if($this->countryId == null) {
            $countryId = isset($this->data[$rowNum]["country_id"]) ? $this->data[$rowNum]["country_id"] : null;
        }
        else{
            $countryId = $this->countryId;
        }

        $competitionId = ($this->data[$rowNum[0]]['competition_id']);
        $competitionStatus = Competition::find($competitionId)->status;

        $user = User::where([
            "id" => $value,
            "country_id" => $countryId
        ])->first();

        if(!$user || !$user->parent) return false;

        if($user->parent->competitionPartner->where("id",$competitionId)->first() && $competitionStatus == 'active') return true;

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'user not found/invalid';
    }
}
