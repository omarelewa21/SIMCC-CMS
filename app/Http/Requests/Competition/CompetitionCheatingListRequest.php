<?php

namespace App\Http\Requests\Competition;

use App\Models\Countries;
use Illuminate\Foundation\Http\FormRequest;

class CompetitionCheatingListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin']);
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if($this->has('country')) {
            $requestCountries = $this->query('country');
            if (is_string($requestCountries)) {
                // Assuming the string is like "[41,202]", strip the brackets and explode by comma
                $requestCountries = trim($requestCountries, "[]");
                $requestCountries = explode(',', $requestCountries);
            }
            $competitionCountries = Countries::getCompetitionCountryList($this->route()->competition);

            if(empty(array_diff($competitionCountries, $requestCountries))) {
                $this->offsetUnset('country');
            } else {
                $countries = array_intersect($competitionCountries, $requestCountries);
                sort($countries);
                $this->merge([
                    'country' => $countries,
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'school'            => 'integer|exists:schools,id',
            'grade'             => 'integer|exists:participants,grade',
            'search'            => 'string',
            'percentage'        => 'numeric',
            'question_number'   => 'integer',
            'country'           => 'array',
            'country.*'         => 'integer|exists:all_countries,id',
            'number_of_incorrect_answers' => 'integer',
            'get_data'          => 'boolean',
        ];
    }
}
