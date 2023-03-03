<?php

namespace App\Http\Requests\collection;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionSettingsRequest extends FormRequest
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
            'settings.name'                 => 'sometimes|required|distinct|unique:collection,name',
            'settings.identifier'           => 'sometimes|required|distinct|unique:collection,identifier|regex:/^[\_\w-]*$/',
            'settings.time_to_solve'        => 'required|integer|min:0|max:600',
            'settings.initial_points'       => 'integer|min:0',
            'settings.tags'                 => 'array',
            'settings.tags.*'               => ['integer', Rule::exists('domains_tags',"id")->where(fn(Builder $query) => $query->where('is_tag', 1))],
            'settings.description'          => 'string|max:65535',
        ];
    }
}
