<?php

namespace App\Http\Requests\Task;

use App\Models\Tasks;
use App\Rules\CheckTaskUse;
use Illuminate\Foundation\Http\FormRequest;

class DeleteTaskRequest extends FormRequest
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
            'id'    => 'required|array',
            'id.*'  => ['required', 'integer', 'distinct', new CheckTaskUse]
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
        $validator->after(function ($validator) use ($task) {
            if (!$task->status == Tasks::STATUS_VERIFIED) {
                $validator->errors()->add('authorize', 'Task is verified, Deleting verified task is not allowed');
            }
        });
    }
}
