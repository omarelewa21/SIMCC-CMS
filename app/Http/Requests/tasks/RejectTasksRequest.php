<?php

namespace App\Http\Requests\tasks;

use App\Models\Tasks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;

class RejectTasksRequest extends FormRequest
{
    private Tasks $task;

    function __construct(Route $route)
    {
        $this->task = $route->parameter('task');
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
            if ($this->task->status !== 'pending moderation') {
                $validator->errors()->add('authorize', 'You cant reject task which is not in pending status');
            }
        });
    }
}
