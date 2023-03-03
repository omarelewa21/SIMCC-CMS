<?php

namespace App\Http\Requests;

use App\Models\School;
use App\Rules\CheckSchoolUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolRequest extends FormRequest
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
            "id"        => "required|integer|exists:schools,id",
            "name"      => ["sometimes", "required", "distinct", "regex:/^[\'\;\.\,\s\(\)\[\]\w-]*$/", new CheckSchoolUnique, Rule::notIn(['Organization School','ORGANIZATION SCHOOL','organization school'])],
            "address"   => "max:255",
            "postal"    => "integer",
            "phone"     => "required|regex:/^[0-9]*$/",
            "email"     => "required|email",
            "province"  => "sometimes|required|max:255",
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
        $school = School::where('id', $this->id)->where('status', '!=', 'deleted')->firstORFail();
        $user = auth()->user();

        $validator->after(function ($validator) use($school, $user){
            if ($user->hasRole(['Country Partner', 'Country Partner Assistant']) && $school->country_id != $user->country_id) {
                $validator->errors()->add('Permission Denied', "Only allowed to edit school's from current country");
            }
            if ($user->hasRole(['Teacher', 'School Manager']) && $user->school_id != $school->id) {
                $validator->errors()->add('Permission Denied', "Only allowed to edit current school");
            }
        });
    }
}