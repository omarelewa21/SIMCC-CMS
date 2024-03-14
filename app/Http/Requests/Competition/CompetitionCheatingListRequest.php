<?php

namespace App\Http\Requests\Competition;

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
            $this->merge([
                'country' => json_decode($this->country, true),
            ]);
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
        ];
    }
}
