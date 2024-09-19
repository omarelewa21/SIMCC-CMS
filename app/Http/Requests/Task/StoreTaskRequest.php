<?php

namespace App\Http\Requests\Task;

use App\Http\Requests\CreateBaseRequest;
use App\Rules\CheckAnswerLabelEqual;
use App\Services\DifficultyService;
use App\Services\GradeService;
use Illuminate\Validation\Rule;


class StoreTaskRequest extends CreateBaseRequest
{
    function __construct()
    {
        $this->uniqueFields = ['identifier'];
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
     * @return arr of rules
     */
    protected function validationRules($key)
    {
        return [
            $key.'.title'               => 'required|distinct|unique:task_contents,task_title|regex:/^[\.\,\s\(\)\[\]\w-]*$/',
            $key.'.identifier'          => 'required|distinct|unique:tasks,identifier|regex:/^[\_\w-]*$/',
            $key.'.tag_id'              => 'array|nullable',
            $key.'.tag_id.*'            => ['exclude_if:*.tag_id,null','integer', Rule::exists('domains_tags', 'id')->where(function ($query) {
                                                $query->where('status', 'active')
                                                    ->whereNotNull('domain_id')
                                                    ->orWhere('is_tag',1);
                                            })],
            $key.'.description'         => 'max:255',
            $key.'.solutions'           => 'max:255',
            $key.'.image'               => 'exclude_if:*.image,null|max:1000000',
            $key.'.recommended_grade'   => 'array',
            $key.'.recommended_grade.*' => 'integer|nullable|in:'.implode(',', GradeService::getAllowedGradeNumbers()),
            $key.'.recommended_difficulty'      => 'array',
            $key.'.recommended_difficulty.*'    => "string|nullable|max:255|in:".implode(',', DifficultyService::ALLOWED_DIFFICULTIES),
            $key.'.content'             => 'string|max:65535',
            $key.'.answer_type'         => 'required|integer|exists:answer_type,id',
            $key.'.answer_structure'    => 'required|integer|min:1|max:4',
            $key.'.answer_sorting'      => 'integer|nullable|required_if:*.answer_type_id,1|min:1|max:2', //
            $key.'.answer_layout'       => 'integer|nullable|required_if:*.answer_type_id,1|min:1|max:2',
            $key.'.image_label'         => 'integer|min:0|max:1',
            $key.'.labels'              => 'required|array',
            $key.'.labels.*'            => 'nullable',
            $key.'.answers'             => ['required', 'array', new CheckAnswerLabelEqual],
            $key.'.answers.*'           => 'string|max:65535|nullable',
        ];
    }
}
