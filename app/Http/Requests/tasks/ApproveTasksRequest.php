<?php

namespace App\Http\Requests\tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveTasksRequest extends FormRequest
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
            'ids'       => 'required|array|min:1',
            'ids.*'     => [Rule::exists('tasks', 'id')->whereNotIn('status', ['Active', 'Deleted'])]
        ];
    }
}