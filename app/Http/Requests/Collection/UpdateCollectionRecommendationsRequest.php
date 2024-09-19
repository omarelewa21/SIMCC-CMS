<?php

namespace App\Http\Requests\Collection;

use App\Services\DifficultyService;
use App\Services\GradeService;
use App\Traits\CollectionAuthorizeRequestTrait;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionRecommendationsRequest extends FormRequest
{
    use CollectionAuthorizeRequestTrait;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'collection_id'                 => 'required|integer|exists:collection,id',
            'recommendations'               => 'array',
            'recommendations.*.grade'       => 'integer|nullable|in:'.implode(',', GradeService::getAllowedGradeNumbers()),
            'recommendations.*.difficulty'  => "string|nullable|max:255|in:".implode(',', DifficultyService::ALLOWED_DIFFICULTIES)
        ];
    }
}
