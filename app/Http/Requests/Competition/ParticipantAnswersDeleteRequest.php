<?php

namespace App\Http\Requests\Competition;

use App\Services\GradeService;
use Illuminate\Foundation\Http\FormRequest;

class ParticipantAnswersDeleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->hasRole(['admin', 'super admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'indexes'       => 'array|min:1',
            'indexes.*'     => 'string|exists:participants,index_no',
            'grade'         => 'integer|in:' . implode(',', GradeService::ALLOWED_GRADE_NUMBERS),
            'country_id'    => 'integer|exists:all_countries,id',
            'status'        => 'string|in:active,result computed,absent',
            'school_id'     => 'integer|exists:schools,id',
        ];
    }
}
