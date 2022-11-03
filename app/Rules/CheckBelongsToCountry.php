<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class CheckBelongsToCountry implements Rule, DataAwareRule
{

    protected $data = [];
    protected $class;

    function setData($data)
    {
        // TODO: Implement setData() method.
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
        $rowNum = explode(".",$attribute)[0];

        return $this->class->where('id',$value)
            ->where('country_id', $this->data[$rowNum]['country_id'])
            ->where('status', 'active')
            ->count();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Invaild Id';
    }
}
