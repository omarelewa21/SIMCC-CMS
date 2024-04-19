<?php

namespace App\Http\Requests\Competition;

use App\Models\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

class CompetitionCheatingListRequest extends FormRequest
{
    use \App\Traits\IntegrityTrait;

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
        if($this->filled('country')) {
            $requestCountries = json_decode($this->country, true);
            $competitionCountries = Countries::getCompetitionCountryList($this->route()->competition);
            if(empty(array_diff($competitionCountries, $requestCountries))) {
                $this->merge(['country' => null]);
            } else {
                $countries = array_intersect($competitionCountries, $requestCountries);
                sort($countries);
                $this->merge(['country' => $countries]);
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
            'country'           => 'array|nullable:min:1',
            'country.*'         => 'integer|exists:all_countries,id',
            'number_of_incorrect_answers' => 'integer',
            'get_data'          => 'boolean',
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
            if(!$this->recompute) return;
            $countryIds = $this->filled('country') ? $this->country : Countries::getCompetitionCountryList($this->route()->competition);
            $confirmedCountries = $this->hasConfirmedCountry($this->route()->competition, $countryIds);

            if ($confirmedCountries){
                $validator->errors()->add(
                    'countries',
                    sprintf(
                        "You need to revoke IAC confirmation from these countries: %s, before you can perform IAC integrity check or MAP check on them"
                        , Arr::join($confirmedCountries, ', ', ' and ')
                    )
                );
            }

            if(Route::currentRouteName() === 'competition.cheaters.sameParticipant') return;
            $countriesWithNoAnswersUploaded = $this->getCountriesWithNoAnswersUploaded($this->route()->competition, $countryIds);
            if ($countriesWithNoAnswersUploaded){
                $validator->errors()->add(
                    'countries',
                    sprintf(
                        "You need to upload answers for these countries: %s, before you can perform IAC integrity check"
                        , Arr::join($countriesWithNoAnswersUploaded, ', ', ' and ')
                    )
                );
            }
        });
    }
}
