<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class getParticipantListRequest extends FormRequest
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
            'index_no'                      => 'alpha_num',
            'country_id'                    => 'integer',
            'organization_id'               => 'integer',
            'competition_organization_id'   => 'integer',
            'competition_id'                => 'integer',
            'school_id'                     => 'integer',
            'status'                        => 'string',
            'private'                       => 'boolean',
            'limits'                        => 'integer|nullable',
            'page'                          => 'integer',
            'search'                        => 'max:255'
        ];
    }
}
