<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator as Validate;

use Illuminate\Support\Str;

class CreateBaseRequest extends FormRequest
{
    protected $uniqueFields = [];
    protected $rules = [];

    /**
     * Overwrite validation return
     *
     * @return HttpResponseException
     */
    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach($this->all() as $key=>$data){
                $this->rules = array_merge($this->rules, $this->validationRules($key));
            }
            Validate::validate($this->all(), $this->rules);
            $this->checkUniqueness($validator);
        });
    }

    /**
     * 
     * @return arr of rules
     */
    protected function validationRules($key)
    {
        return [];
    }

    /**
     * Check if request data has same inputs for the unique fields
     * 
     * @param  \Illuminate\Validation\Validator  $validator
     */
    protected function checkUniqueness($validator){
        foreach($this->uniqueFields as $field_check){
            $list_check = [];
            foreach($this->all() as $data){
                $list_check[] = $data[$field_check];
            }
            if(count($list_check) > count(array_unique($list_check))){
                $validator->errors()->add(
                    'errors', 
                    $field_check . ' has duplicates, please review ' . $field_check . ' field across your submitted data'
                );
            }
        }
    }
}
