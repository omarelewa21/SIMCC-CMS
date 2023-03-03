<?php

namespace App\Http\Requests\Collection;

use Illuminate\Foundation\Http\FormRequest;

class AddSectionsRequest extends FormRequest
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
            'collection_id'                 => 'required|integer|exists:collection,id',
            'section'                       => 'required|array',
            'section.groups'                => 'required|array',
            'section.groups.*.task_id'      => 'array|required',
            'section.groups.*.task_id.*'    => 'required|integer|exists:tasks,id',
            'section.sort_randomly'         => 'boolean|required',
            'section.allow_skip'            => 'boolean|required',
            'section.description'           => 'string|max:65535|nullable',
        ];
    }
}
