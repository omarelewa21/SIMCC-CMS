<?php

namespace App\Http\Requests\collection;

use App\Models\Collections;
use App\Models\CollectionSections;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveCollectionRequest extends FormRequest
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
            'ids.*'     => [Rule::exists('collection', 'id')->where(fn($query) => $query->whereNotIn('status', ['Active', 'Deleted']))]
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
            foreach($this->ids as $index=>$collectionId){
                $numberOfTasksNotActive = CollectionSections::where('collection_id', $collectionId)->get()
                ->pluck('section_task')
                ->flatten()
                ->filter(fn($task) => $task->status !== 'Active')
                ->count();
                
                if($numberOfTasksNotActive > 0){
                    $validator->errors()->add('Status', sprintf("Collection %s has %s tasks that are not active yet", $index+1, $numberOfTasksNotActive));
                }
            }
        });
    }
}
