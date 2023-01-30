<?php

namespace App\Http\Requests;

use App\Rules\AllowedToDeleteCompetitionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteCompetitionRequest extends FormRequest
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
            "id"    => "array",
            "id.*"  => ["required", "integer", Rule::exists("competition", "id")->where("status", "active"), new AllowedToDeleteCompetitionRule]
        ];
    }
}