<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompetitionListRequest extends FormRequest
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
            'id'        => 'integer',
            'name'      => "/^[\.\,\s\(\)\[\]\w-]*$/",
            'format'    => 'boolean',
            'status'    => 'alpha_dash',
            'limits'    => 'integer',
            'page'      => 'integer',
            'search'    => 'max:255'
        ];
    }
}
