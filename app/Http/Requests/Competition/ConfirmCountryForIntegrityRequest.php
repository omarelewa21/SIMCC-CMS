<?php

namespace App\Http\Requests\Competition;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmCountryForIntegrityRequest extends FormRequest
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
            'countries'     => "required|array",
            'countries.*'   => "required|array",
            'countries.*.id' => "required|exists:all_countries,id",
            'countries.*.is_confirmed' => "required|boolean"
        ];
    }
}
