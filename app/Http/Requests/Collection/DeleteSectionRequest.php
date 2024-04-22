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

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        if(auth()->user()->hasRole('Super Admin')) return;

        $collection = Collections::find($this->collection_id);
        $validator->after(function ($validator) use ($collection) {
            if ($collection->isCollectionRestricted()) {
                $validator->errors()->add('authorize', 'This Collection is used in a computed level, you cannot delete any section.');
            }
        });
    }
}
