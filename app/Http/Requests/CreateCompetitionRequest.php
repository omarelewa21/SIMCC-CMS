<?php

namespace App\Http\Requests;

use App\Rules\CheckCompetitionAvailGrades;
use App\Rules\CheckLevelUsedGrades;
use App\Rules\CheckOrganizationCountryPartnerExist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Database\Query\Builder;

class CreateCompetitionRequest extends FormRequest
{
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
            "name"                                  => "unique:competition,name|required|regex:/^[\.\,\s\(\)\[\]\w-]*$/",
            "alias"                                 => "sometimes|required|string|distinct|unique:competition,alias|" ,
            "competition_mode"                      => "required|min:0|max:2",
            "competition_start_date"                => "required|date|after_or_equal:global_registration_date",                                                                             //06/10/2011 19:00:02
            "competition_end_date"                  => "required|date|after_or_equal:competition_start_date",                                                                               //06/10/2011 19:00:02
            "global_registration_date"              => "required|date",                                                                                                                     //06/10/2011 19:00:02
            "global_registration_end_date"          => ["required_if:format,1", "date", "after_or_equal:global_registration_date", "before:competition_start_date"],                        //06/10/2011 19:00:02
            "allowed_grades"                        => "required|array" ,
            "allowed_grades.*"                      => "required|integer|min:1" ,
            "format"                                => "required|boolean",
            "re-run"                                => "required|boolean",
            "parent_competition_id"                 => ["required_if:re-run,1", "integer", "nullable", Rule::exists('competition','id')->where(function(Builder $query){
                                                            return $query->where('format', 0)->where('status', 'closed');
                                                        })],    //use for re-run competition,
            "difficulty_group_id"                   => "required|integer|exists:difficulty_groups,id",
            "organizations"                         => 'required|array',
            "organizations.*.organization_id"       => ["required", "integer", Rule::exists('organization',"id")->where(fn(Builder $query) => $query->where('status','active')), "distinct"],
            "organizations.*.country_id"            => ['required', 'integer', new CheckOrganizationCountryPartnerExist],
            "organizations.*.translate"             => "json",
            "organizations.*.edit_sessions.*"       => 'boolean',
            "rounds"                                => "array|required",
            "rounds.*.name"                         => "required|regex:/^[\.\,\s\(\)\[\]\w-]*$/",
            "rounds.*.round_type"                   => ["required", "integer", Rule::in([0, 1])],
            "rounds.*.team_setting"                 => ["exclude_if:rounds.*.round_type,0", "required_if:rounds.*.round_type,1", "integer", Rule::in([0, 1])],
            "rounds.*.individual_points"            => ["exclude_if:rounds.*.round_type,0", "required_if:rounds.*.round_type,1", "integer", Rule::in([0, 1])],
            "rounds.*.award_type"                   => "required|integer|boolean",
            "rounds.*.assign_award_points"          => "required|integer|boolean",
            "rounds.*.default_award_name"           => "required|string",
            "rounds.*.default_award_points"         => "integer|nullable",
            "rounds.*.levels"                       => "array|required",
            "rounds.*.levels.*.name"                => "required|regex:/^[\.\,\s\(\)\[\]\w-]*$/",
            "rounds.*.levels.*.collection_id"       => ["integer", "nullable", Rule::exists('collection','id')->where(fn(Builder $query) => $query->where('status','active'))],
            "rounds.*.levels.*.grades"              => "array|required",
            "rounds.*.levels.*.grades.*"            => ["required", "integer", new CheckCompetitionAvailGrades, new CheckLevelUsedGrades]
        ];
    }
}
