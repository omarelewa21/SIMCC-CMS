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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'School'    => 'integer|exists:schools,id',
            'Country'   => 'integer|exists:all_countries,id',
            'Grade'     => 'integer|exists:participants,grade',
            'cheat_percentage' => 'integer',
            'group_id'  => 'integer|exists:cheating_participants,group_id',
            'search'    => 'string',
        ];
    }
}
