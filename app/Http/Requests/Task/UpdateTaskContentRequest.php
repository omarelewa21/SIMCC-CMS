<?php

namespace App\Http\Requests\tasks;

use App\Models\Tasks;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskContentRequest extends FormRequest
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
        $task = Tasks::find($this->id);
        $validator->after(function ($validator) use($task){
            if (!$task->allowedToUpdateAll()) {
                $validator->errors()->add('authorize', 'Task is in use by an active competition, No update to content is allowed');
            }
        });
    }
}
