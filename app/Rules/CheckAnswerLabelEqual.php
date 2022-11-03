<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class CheckAnswerLabelEqual implements Rule, DataAwareRule
{
    protected $data = [];
    private $message = [];

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
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {

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
        $totalAnswer = count($value);

        if(is_numeric($rowNum))
        {
            if(count($this->data[$rowNum]['labels']) !== $totalAnswer) {
                $this->message = 'Total no. of answers does not match total no. of correct answers';
                return false;
            }
        }
        else
        {
            if(count($this->data['labels']) !== $totalAnswer) {
                $this->message = 'Total no. of answers does not match total no. of correct answers';
                return false;
            }
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
        return $this->message;
    }
}
