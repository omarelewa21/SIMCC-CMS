<?php

namespace App\Http\Requests\School;

use App\Rules\CheckSchoolUnique;
use App\Rules\NoSpecialCharacters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSchoolRequest extends FormRequest
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
            "role_id"               => "nullable",
            "school.*.country_id"   => 'exclude_if:role_id:2,4|required_if:role_id:0,1|integer|exists:all_countries,id',
            "school.*.name"         => ["required", "string", new NoSpecialCharacters, new CheckSchoolUnique, Rule::notIn(['Organization School','ORGANIZATION SCHOOL','organization school'])],
            "school.*.private"      => "required|boolean",
            "school.*.address"      => "max:255",
            "school.*.postal"       => "max:255",
            "school.*.phone"        => "required|regex:/^[0-9]*$/",
            "school.*.email"        => "required|email",
            "school.*.province"     => "required|max:255",
        ];
    }
}
