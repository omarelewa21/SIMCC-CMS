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
            'answers'           => 'required|array',
            'answers.*.answer_id' => 'integer|nullable|exists:task_answers,id',
            'answers.*.label_id'  => 'integer|nullable|exists:task_labels,id',
            'answers.*.label'     => 'string|nullable',
            'answers.*.answer'    => 'string|nullable',
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
            if (!$task->status==Tasks::STATUS_VERIFIED) {
                $validator->errors()->add('authorize', 'Task is verified, No update to answers is allowed');
            }
        });
    }
}
