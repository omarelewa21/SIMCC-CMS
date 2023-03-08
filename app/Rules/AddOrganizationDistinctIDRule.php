<?php

namespace App\Rules;

use App\Models\Organization;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Support\Arr;

class AddOrganizationDistinctIDRule implements DataAwareRule, InvokableRule
{
    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];
 
    // ...
 
    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;
 
        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function __invoke($attribute, $value, $fail)
    {
        if(is_array($this->data['organizations'])){
            $duplicates = collect($this->data['organizations'])->duplicates('organization_id');
            if($duplicates->isNotEmpty()){
                $duplicates->each(function($organization_id) use($fail){
                    if(
                        collect($this->data['organizations'])->filter(
                            fn ($arr) => $arr['organization_id'] === $organization_id
                        )->duplicates('country_id')->isNotEmpty()
                    ){
                        $organizationName = Organization::whereId($organization_id)->value('name');
                        $fail("You have added same country for organization '$organizationName' twice, please remove one");
                    }
                });
            }
            if($duplicates = collect($this->data['organizations'])->duplicates('organization_id'));
        }
    }
}
