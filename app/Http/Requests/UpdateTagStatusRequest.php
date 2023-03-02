<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTagStatusRequest extends FormRequest
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
            "id"    => "array",
            "id.*"  => ['required', 'integer', Rule::exists('domains_tags', 'id')->where(fn(Builder $query) => $query->whereNull('deleted_at'))]
        ];
    }
}
