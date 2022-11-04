<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CompetitionMarkingGroup;
use Illuminate\Validation\Rule;

class StoreCompetitionMarkingGroupRequest extends FormRequest
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
     * @return array
     */
    public function rules()
    {
        $validation = [
            "name"          => "required|string",
            "countries"     => "required|array" 
        ];
        
        $levelCountries = CompetitionMarkingGroup::where('competition_level_id',$level_id)->pluck('country_group')->flatten()->toArray();

        foreach($this->countries as $key=>$country_id){
            $validation = array_merge($validation, [
                "countries.". $key     => ['required', 'integer', 'distinct', 'exists:all_countries,id', Rule::notIn($levelCountries)]
            ]);
        }
        
        return $validation;
    }
}
