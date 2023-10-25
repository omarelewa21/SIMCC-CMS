<?php

namespace App\Http\Requests;

use App\Rules\CheckDomainTagsExist;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDomainTagRequest extends FormRequest
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
            '*.is_tag'      => 'required_if:*.domain_id,null|boolean',
            '*.domain_id'   => ['integer', 'exclude_if:*.is_tag,1', Rule::exists('domains_tags', 'id')->whereNull('deleted_at')],
            '*.name'        => 'required|array',
            '*.name.*'      => [
                'required',
                'regex:/^[\.\,\s\(\)\[\]\w-]*$/',
                new CheckDomainTagsExist,
                // Rule::unique('domains_tags', 'name')
                //     ->where(function (Builder $query) {
                //         return $query->whereNull('domain_id')->whereNull('deleted_at');
                //     })
            ],
        ];
    }
}
