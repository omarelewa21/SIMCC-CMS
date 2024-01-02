<?php

namespace App\Http\Requests\Task;

use App\Models\Tasks;
use App\Rules\CheckAnswerLabelEqual;
use App\Traits\TaskAuthorizeRequestTrait;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskAnswerRequest extends FormRequest
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
            'id'                => 'required|integer|exists:tasks,id',
            'answer_type'       => 'required|integer|exists:answer_type,id',
            'answer_structure'  => 'required|integer|exists:answer_structure,id',
            'answer_sorting'    => 'integer|nullable|required_if:answer_type,1|exists:answer_sorting,id',
            'answer_layout'     => 'integer|nullable|required_if:answer_type,1|exists:answer_layout,id',
            'labels'            => 'required|array',
            'labels.*'          => 'nullable',
            'answers'           => ['required', 'array', new CheckAnswerLabelEqual],
            'answers.*'         => 'string|max:65535|nullable'
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
        $task = Tasks::find($this->id);
        $validator->after(function ($validator) use($task){
            if (!auth()->user()->hasRole('super admin') && $task->isTaskRestricted()) {
                $validator->errors()->add('authorize', 'Task is computed before, you can\'t update its answers');
            }
        });
    }
}
