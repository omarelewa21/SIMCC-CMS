<?php

namespace App\Http\Requests;

use App\Models\Competition;
use App\Rules\CheckGlobalCompetitionEndDateAvail;
use App\Rules\CheckGlobalCompetitionStartDateAvail;
use App\Rules\CheckGlobalRegistrationDateAvail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rule;

class UpdateCompetitionRequest extends FormRequest
{
    private Competition $competition;

    function __construct(Route $route)
    {
        $this->competition = $route->parameter('competition');
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "name"                          => ["sometimes", "required", "regex:/^[\.\,\s\(\)\[\]\w-]*$/", Rule::unique('competition')->ignore($this->competition)],
            "alias"                         => ["sometimes", "required", "string", "distinct", Rule::unique('competition')->ignore($this->competition)] ,
            "competition_mode"              => "required|min:0|max:2",
            "competition_start_date"        => ["required","date","after_or_equal:global_registration_date", new CheckGlobalCompetitionStartDateAvail($this->competition)], //06/10/2011 19:00:02
            "competition_end_date"          => ["required","date","after_or_equal:competition_start_date", new CheckGlobalCompetitionEndDateAvail($this->competition)], //06/10/2011 19:00:02
            "global_registration_date"      => ["required","date", new CheckGlobalCompetitionStartDateAvail($this->competition)], //06/10/2011 19:00:02
            "global_registration_end_date"  => ["sometimes","required","date", "after_or_equal:global_registration_date","before:competition_start_date"], //06/10/2011 19:00:02
            "allowed_grades"                => "required|array" ,
            "allowed_grades.*"              => "required|integer|min:1" ,
            "difficulty_group_id"           => "required|integer|exists:difficulty_groups,id"
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if($this->competition->status !== 'active') {
                $validator->errors()->add('Competition Colsed', 'The selected competition is closed, no edit is allowed.');
            }
        });
    }
}
