<?php

namespace App\Http\Requests\collection;

use App\Models\Collections;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;

class RejectCollectionRequest extends FormRequest
{
    private Collections $collection;

    function __construct(Route $route)
    {
        $this->collection = $route->parameter('collection');
    }

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
            "reason"    => "required|string|min:1|max:400"
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator){
            if ($this->collection->status !== 'Pending Moderation') {
                $validator->errors()->add('Status', "You can't reject collection which is not in pending status");
            }
        });
    }
}
