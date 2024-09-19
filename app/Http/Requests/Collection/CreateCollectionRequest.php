<?php

namespace App\Http\Requests\Collection;

use App\Services\DifficultyService;
use App\Services\GradeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCollectionRequest extends FormRequest
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
            '*.settings.name'           => 'required|distinct|unique:collection,name|regex:/^[\/\.\,\s\(\)\[\]\w-]*$/',
            '*.settings.identifier'     => 'required|distinct|unique:collection,identifier|regex:/^[\/\_\w-]*$/',
            '*.settings.time_to_solve'  => 'required|integer|min:0|max:600',
            '*.settings.initial_points' => 'integer|min:0',
            '*.settings.tags'           => 'array',
            '*.settings.tags.*'         => ['integer',Rule::exists('domains_tags','id')->where('is_tag',1)],
            '*.settings.description'    => 'string|max:65535',
            '*.recommendations'         => 'array',
            '*.recommendations.*.grade' => 'integer|nullable|in:'.implode(',', GradeService::getAllowedGradeNumbers()),
            '*.recommendations.*.difficulty' => "string|nullable|max:255|in:".implode(',', DifficultyService::ALLOWED_DIFFICULTIES),
            '*.sections'                => 'required|array',
            '*.sections.*.groups'       => 'required|array',
            '*.sections.*.groups.*.task_id'     => 'array|required',
            '*.sections.*.groups.*.task_id.*'   => 'required|integer|exists:tasks,id',
            '*.sections.*.sort_randomly' => 'boolean|required',
            '*.sections.*.allow_skip'   => 'boolean|required',
            '*.sections.*.description'  => 'string|max:65535',
        ];
    }
}
