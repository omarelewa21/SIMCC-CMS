<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Route;


class getActiveParticipantsByCountryRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        $rules = [
            "countries.*"   => "required|array",
            "countries"     => 'required_array_keys:0'
        ];
        
        $countriesIdsList = $this->competition->participants()->pluck('participants.country_id')->toArray();

        foreach($this->countries as $key=> $country_id){
            $rules = array_merge($rules, [
                "countries.". $key     => ['integer', 'distinct', 'exists:all_countries,id', Rule::in($countriesIdsList)]
            ]);
        }
        
        return $rules;
    }
}
