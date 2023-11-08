<?php

namespace App\Http\Requests\Task;

use App\Rules\CheckMissingGradeDifficulty;
use App\Services\DifficultyService;
use App\Services\GradeService;
use App\Traits\TaskAuthorizeRequestTrait;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRecommendationsRequest extends FormRequest
{
    use TaskAuthorizeRequestTrait;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'id'                        => 'required|integer|exists:tasks,id',
            'recommended_grade'         => 'array',
            'recommended_grade.*'       => 'integer|nullable|in:'.implode(',', GradeService::ALLOWED_GRADE_NUMBERS),
            'recommended_difficulty'    => 'array',
            'recommended_difficulty.*'  => "string|nullable|max:255|in:".implode(',', DifficultyService::ALLOWED_DIFFICULTIES)
        ];
    }
}
