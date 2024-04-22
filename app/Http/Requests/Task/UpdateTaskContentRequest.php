<?php

namespace App\Http\Requests\Task;

use App\Models\Tasks;
use App\Traits\TaskAuthorizeRequestTrait;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskContentRequest extends FormRequest
{
    use TaskAuthorizeRequestTrait;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'id'                        => 'required|integer|exists:tasks,id',
            're-moderate'               => 'required|boolean',
            'taskContents'              => 'required|array',
            'taskContents.*.title'      => 'required|string|max:255',
            'taskContents.*.lang_id'    => 'required|integer',
            'taskContents.*.content'    => 'required|string|max:65535'
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
        if(auth()->user()->hasRole('Super Admin')) return;

        $task = Tasks::find($this->id);
        $validator->after(function ($validator) use($task){
            if ($task->isTaskRestricted()) {
                $validator->errors()->add('authorize', 'Task is computed before, you can\'t update its content');
            }
        });
    }
}
