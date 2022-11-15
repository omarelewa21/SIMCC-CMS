<?php

namespace App\Http\Requests;

use App\Models\CompetitionMarkingGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rule;

class UpdateCompetitionMarkingGroupRequest extends FormRequest
{
    protected $competitionMarkingGroup;

    function __construct(Route $route)
	{
		$this->competitionMarkingGroup = $route->parameter('competition_marking_group');
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
        $rules = [
            "name"          => "required|string",
            "countries.*"   => "required|array" 
        ];

        $excludeCountries = CompetitionMarkingGroup::where('competition_id', $this->competitionMarkingGroup->competition->id)
                            ->where('competition_marking_group.id', '!=', $this->competitionMarkingGroup->id)
                            ->join('competition_marking_group_country as cm', 'competition_marking_group.id', '=', 'cm.marking_group_id')
                            ->join('all_countries as al_c', 'al_c.id', '=', 'cm.country_id')
                            ->select('al_c.id as country_id')->pluck('country_id')->toArray();
        
        foreach($this->countries as $key=> $country_id){
            $rules = array_merge($rules, [
                "countries.". $key     => ['integer', 'distinct', 'exists:all_countries,id', Rule::notIn($excludeCountries)]
            ]);
        }
        
        return $rules;
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
            if($this->competitionMarkingGroup->status === 'closed'){
                $validator->errors()->add('Competition', 'The selected competition is close for edit');
            }

            foreach($this->countries as $country_id){
                if($this->competitionMarkingGroup->competition->participants()->where('participants.country_id', $country_id)->count() === 0) {
                    $validator->errors()->add('Country', 'The selected country id have no participants');
                }
            }
        });
    }
}
