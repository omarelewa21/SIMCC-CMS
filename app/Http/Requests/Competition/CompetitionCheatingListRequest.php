<?php

namespace App\Http\Requests\Competition;

use App\Models\CompetitionParticipantsResults;
use App\Models\Countries;
use App\Services\GradeService;
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
            'force_compute'     => 'boolean',
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
        return;
        $validator->after(function ($validator) {
            if(!$this->recompute) return;

            $countryIds = $this->filled('country') ? $this->country : Countries::getCompetitionCountryList($this->route()->competition);

            $this->validateConfirmedCountry($validator, $countryIds);
            if(!$this->force_compute) {
                $this->validateCompetitionGlobalRankStatus($validator);
            }

            if(Route::currentRouteName() === 'competition.cheaters.sameParticipant') return;

            $this->validateCountriesWithNoAnswersUploaded($validator, $countryIds);
            $this->validateGradesWithVerifiedCollections($validator);
        });
    }

    /**
     * Validate confirmed countries
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param array<int> $countryIds
     * @return void
     */
    private function validateConfirmedCountry($validator, $countryIds)
    {
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
    }

    /**
     * Validate competition global rank status
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateCompetitionGlobalRankStatus($validator)
    {
        $levels = $this->route()->competition->levels()->pluck('competition_levels.id');
        $check = CompetitionParticipantsResults::whereIn('level_id', $levels)
            ->whereNotNull('global_rank')
            ->exists();

        if($check === false) return;

        $validator->errors()->add(
            'global_rank',
            Route::currentRouteName() === 'competition.cheaters.sameParticipant'
                ? "MAP Generation can only be done before Global ranking is generated"
                : "Integrity IAC Generation can only be done before Global ranking is generated"
        );
    }

    /**
     * Validate countries with no answers uploaded
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param array<int> $countryIds
     * @return void
     */
    private function validateCountriesWithNoAnswersUploaded($validator, $countryIds)
    {
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
    }

    /**
     * Validate grades with verified collections
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateGradesWithVerifiedCollections($validator)
    {
        $gradesWithVerifiedCollections = GradeService::getGradesWithVerifiedCollections($this->route()->competition);
        if(empty($gradesWithVerifiedCollections)) {
            $validator->errors()->add(
                'grades',
                "There is no verified collections in this competition, you need to verify at least one collection to perform IAC integrity check"
            );
        }
    }
}
