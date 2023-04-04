<?php

namespace App\Http\Requests\Schools;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth()->user()->hasRole(['Admin', 'Super Admin', 'Country Partner']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "id"    => "required|array",
            "id.*"  => ['required', 'integer', Rule::exists('schools','id')->where('created_by_userid', '<>', Auth()->id())],
        ];
    }
}
