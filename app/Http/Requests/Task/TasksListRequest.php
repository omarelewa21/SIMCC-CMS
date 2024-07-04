<?php

namespace App\Http\Requests\Task;

use App\Models\DomainsTags;
use App\Models\Languages;
use App\Rules\CheckMultipleVaildIds;
use Illuminate\Foundation\Http\FormRequest;

class TasksListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id'         => 'integer',
            'identifier' => 'regex:/^[\_\w-]*$/',
            'lang_id'    => new CheckMultipleVaildIds(new Languages()),
            'tag_id'     => new CheckMultipleVaildIds(new DomainsTags()),
            'status'     => 'string|max:255',
            'limits'     => 'integer',
            'page'       => 'integer',
            'search'     => 'string|max:255'
        ];
    }
}
