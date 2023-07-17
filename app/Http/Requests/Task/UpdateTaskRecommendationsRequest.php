<?php

namespace App\Http\Requests\tasks;

use App\Rules\CheckMissingGradeDifficulty;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRecommendationsRequest extends FormRequest
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
            'id'                        => 'required|integer|exists:tasks,id',
            'recommended_grade'         => 'required|array',
            'recommended_grade.*'       => ['integer', new CheckMissingGradeDifficulty('recommended_difficulty')],
            'recommended_difficulty'    => 'required|array',
            'recommended_difficulty.*'  => ['string', 'max:255', new CheckMissingGradeDifficulty('recommended_grade')]
        ];
    }
}
