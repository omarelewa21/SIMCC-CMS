<?php

namespace App\Http\Requests\Tags;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagsListRequest extends FormRequest
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
            'domain_id' =>  ['integer', Rule::exists('domains_tags', "id",)
                ->where("domain_id", NULL)
                ->where("is_tag", 0)
            ],
            'status'    => 'alpha',
            'limits'    => 'integer',
            'page'      => 'integer',
            'search'    => 'max:255'
        ];
    }
}
