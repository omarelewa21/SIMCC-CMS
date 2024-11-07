<?php

namespace App\Http\Requests\Participant;

use App\Services\GradeService;
use Illuminate\Foundation\Http\FormRequest;

class AnswerReportRequest extends FormRequest
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
            'countries'     => 'array|min:1',
            'countries.*'   => 'integer|exists:all_countries,id',
            'grade'         => 'integer|in:' .implode(',', GradeService::getAllowedGradeNumbers()),
        ];
    }
}
