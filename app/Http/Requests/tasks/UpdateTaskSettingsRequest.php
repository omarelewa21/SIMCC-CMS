<?php

namespace App\Http\Requests\tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskSettingsRequest extends FormRequest
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
            'id'            => 'required|integer|exists:tasks,id',
            'title'         => ['required', 'string', Rule::unique('task_contents','task_title')->where(fn ($query) => $query->where('task_title', '!=', $this->title))],
            'identifier'    => 'required|regex:/^[\_\w-]*$/',
            'tag_id'        => 'array|nullable',
            'tag_id.*'      => ['exclude_if:*.tag_id,null', 'integer', Rule::exists('domains_tags', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'description'   => 'max:255',
            'solutions'     => 'max:255',
            'image'         => 'exclude_if:*.image,null|max:1000000'
        ];
    }
}
