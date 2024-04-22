<?php

namespace App\Http\Requests\Collection;

use App\Models\Collections;
use App\Traits\CollectionAuthorizeRequestTrait;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionSectionRequest extends FormRequest
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
            'section_id'                    => 'sometimes|integer|exists:collection_sections,id',
            'section'                       => 'required',
            'section.groups'                => 'required|array',
            'section.groups.*.task_id'      => 'array|required',
            'section.groups.*.task_id.*'    => 'required|integer',
            'section.sort_randomly'         => 'boolean|required',
            'section.allow_skip'            => 'boolean|required',
            'section.description'           => 'string|max:65535',
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
        $validator->after(function ($validator) use($collection){
            if (!$collection->status == Collections::STATUS_VERIFIED) {
                $validator->errors()->add('authorize', 'Collection is verified, No update to sections is allowed');
            }
        });
    }
}
