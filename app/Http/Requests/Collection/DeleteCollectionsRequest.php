<?php

namespace App\Http\Requests\Collection;

use App\Models\Collections;
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

    public function withValidator($validator)
    {
        if(auth()->user()->hasRole('Super Admin')) return;

        $collection = Collections::find($this->id);
        $validator->after(function ($validator) use ($collection) {
            if (!$collection->status == Collections::STATUS_VERIFIED) {
                $validator->errors()->add('authorize', 'Collection is verified, Deleting collection is not allowed');
            }
        });
    }
}
