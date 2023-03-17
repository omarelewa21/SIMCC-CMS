<?php

namespace App\Http\Requests\tasks;

use App\Models\DomainsTags;
use App\Models\Languages;
use App\Models\Tasks;
use App\Rules\CheckMultipleVaildIds;
use Illuminate\Foundation\Http\FormRequest;

class TasksListRequest extends FormRequest
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
            'collection_id' => 'exists:collection,id',
            'id'            => 'integer',
            'identifier'    => 'regex:/^[\_\w-]*$/',
            'lang_id'       => new CheckMultipleVaildIds(new Languages()),
            'tag_id'        => new CheckMultipleVaildIds(new DomainsTags()),
            'status'        => 'string|max:255',
            'limits'        => 'integer',
            'page'          => 'integer',
            'search'        => 'string|max:255',
            'status'        => sprintf("in:%s", implode(',', Tasks::STATUS))
        ];
    }
}
