<?php

namespace App\Http\Requests\collection;

use App\Rules\CheckCollectionUse;
use App\Traits\CollectionAuthorizeRequestTrait;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCollectionsRequest extends FormRequest
{
    use CollectionAuthorizeRequestTrait;

    protected $mode = 'delete';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'id'    => 'required|array',
            'id.*'  => ['required', 'integer', 'distinct', new CheckCollectionUse]
        ];
    }
}
