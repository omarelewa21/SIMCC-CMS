<?php

namespace App\Http\Requests\collection;

use App\Models\Competition;
use App\Models\DomainsTags;
use App\Rules\CheckMultipleVaildIds;
use Illuminate\Foundation\Http\FormRequest;

class CollectionListRequest extends FormRequest
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
            "id"                => "integer",
            'name'              => 'regex:/^[\.\,\s\(\)\[\]\w-]*$/',
            'status'            => 'alpha',
            'competition_id'    => new CheckMultipleVaildIds(new Competition()),
            'tag_id'            => new CheckMultipleVaildIds(new DomainsTags()),
            'limits'            => 'integer',
            'page'              => 'integer',
            'search'            => 'max:255'
        ];
    }
}
