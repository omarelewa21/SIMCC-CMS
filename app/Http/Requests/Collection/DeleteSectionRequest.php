<?php

namespace App\Http\Requests\Collection;

use App\Models\Collections;
use App\Traits\CollectionAuthorizeRequestTrait;
use Illuminate\Foundation\Http\FormRequest;

class DeleteSectionRequest extends FormRequest
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
            'collection_id' => 'required|integer|exists:collection,id',
            'id'            => 'required|array',
            'id.*'          => 'required|integer|distinct|exists:collection_sections,id'
        ];
    }
}
