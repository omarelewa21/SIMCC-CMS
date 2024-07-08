<?php

namespace App\Http\Requests\Collection;

use App\Models\Competition;
use App\Models\DomainsTags;
use App\Rules\CheckMultipleVaildIds;
use Illuminate\Foundation\Http\FormRequest;

class CollectionListRequest extends FormRequest
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
            "id" => "integer",
            'name' => 'regex:/^[\.\,\s\(\)\[\]\w-]*$/',
            'status' => 'alpha',
            'competition_id' => new CheckMultipleVaildIds(new Competition()),
            'tag_id' => new CheckMultipleVaildIds(new DomainsTags()),
            'limits' => 'integer',
            'page' => 'integer',
            'search' => 'max:255'
        ];
    }
}
