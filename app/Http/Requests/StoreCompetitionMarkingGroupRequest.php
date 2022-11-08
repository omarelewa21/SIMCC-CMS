<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CompetitionMarkingGroup;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Route;

class StoreCompetitionMarkingGroupRequest extends FormRequest
{
    protected $competition;

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
     * @return array
     */
    public function rules()
    {
        $rules = [
            "name"          => "required|string",
            "countries.*"   => "required|array" 
        ];

        $excludeCountries = CompetitionMarkingGroup::where('competition_id', $this->competition->id)
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
}
