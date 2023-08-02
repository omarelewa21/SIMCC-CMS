<?php

namespace App\Http\Requests\Collection;

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
            'recommendations.*.grade'       => 'required_with:recommendation.*.difficulty|integer|distinct',
            'recommendations.*.difficulty'  => 'required_with:recommendation.*.grade|string'
        ];
    }
}
