<?php

namespace App\Http\Request;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth()->user()->hasRole(['Admin', 'Super Admin', 'Country Partner', 'Country Partner Assistant']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "id"    => "required|array",
            "id.*"  => ['required', 'integer', Rule::exists('schools','id')->where(fn(Builder $query) => $query->where('status', '<>', 'active'))],
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

        if(Auth()->user()->hasRole(['Country Partner', 'Country Partner Assistant'])){
            $validator->after(function ($validator) {
                foreach($this->id as $schoolId){
                    $userWhoCreatedSchool = User::findOrFail(School::whereId($schoolId)->value('created_by_userid'));
                    if(Auth()->user()->organization_id != $userWhoCreatedSchool->organization_id){
                        $validator->errors()->add('id', 'Un-authorized to approve this school created by another organization.');
                    }
                    if(Auth()->id() == $userWhoCreatedSchool->id){
                        $validator->errors()->add('id', 'Un-authorized to approve your own school.');
                    }
                }
            });
        }
    }
}
