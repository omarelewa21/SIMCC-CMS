<?php

namespace App\Http\Requests\Task;

use App\Rules\CheckMissingGradeDifficulty;
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
            'recommended_grade'         => 'required|array',
            'recommended_grade.*'       => ['integer', new CheckMissingGradeDifficulty('recommended_difficulty')],
            'recommended_difficulty'    => 'required|array',
            'recommended_difficulty.*'  => ['string', 'max:255', new CheckMissingGradeDifficulty('recommended_grade')]
        ];
    }
}
