<?php

namespace App\Rules;

use App\Models\DomainsTags;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class CheckMultipleVaildIds implements Rule, DataAwareRule
{
    protected $data = [];
    public $class;

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */

    public function setData ($data) {
        $this->data = $data;

         return $this;
    }

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($class)
    {
        $this->class = $class;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if(!is_numeric(str_replace(',','',$value))) {
            return false;
        }

        $noOfItems = array_map(function ($val) {
            return intVal($val);
        },explode(',',$value));

        if($this->class->whereIn('id',$noOfItems)->count() == 0 ) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Invaild id, only accept numeric value and existing id';
    }
}
