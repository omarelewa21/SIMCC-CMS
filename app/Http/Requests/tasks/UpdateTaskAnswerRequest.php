<?php

namespace App\Http\Requests\tasks;

use App\Models\Tasks;
use App\Rules\CheckAnswerLabelEqual;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskAnswerRequest extends FormRequest
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
            'id'                => 'required|integer|exists:tasks,id',
            'answer_type'       => 'required|integer|exists:answer_type,id',
            'answer_structure'  => 'required|integer|min:1|max:4',
            'answer_sorting'    => 'integer|nullable|required_if:answer_type,1|min:1|max:2',
            'answer_layout'     => 'integer|nullable|required_if:answer_type,1|min:1|max:2',
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
            if (!$task->allowedToUpdateAll()) {
                $validator->errors()->add('authorize', 'Task is in use by an active competition, No update to content can be allowed');
            }
        });
    }
}
