<?php

namespace App\Http\Requests\collection;

use App\Rules\CheckCollectionUse;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCollectionsRequest extends FormRequest
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
            'id'    => 'required|array',
            'id.*'  => ['required', 'integer', 'distinct', new CheckCollectionUse]
        ];
    }
}