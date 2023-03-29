<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SchoolListRequest extends FormRequest
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
            'id'            => "integer",
            'status'        => 'alpha',
            'country_id'    => 'integer',
            'private'       => 'boolean',
            'limits'        => 'integer',
            'page'          => 'integer',
            'show_teachers' => 'boolean',
            'search'        => 'max:255'
        ];
    }
}
