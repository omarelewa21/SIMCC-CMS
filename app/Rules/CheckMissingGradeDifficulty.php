<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class CheckMissingGradeDifficulty implements Rule, DataAwareRule
{
    protected $data = [];
    private $check;
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
    public function __construct($check)
    {
        $this->check = $check;
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
        $temp = explode(".",$attribute);

        if(count($temp) == 2) {
            $recommendedGradeDifficultyNum = $temp[1];

            if(isset($this->data[$this->check][$recommendedGradeDifficultyNum])) {
                return true;
            }
        }

        if(count($temp) == 3) {
            $rowNum = $temp[0];

            $recommendedGradeDifficultyNum = $temp[2];

            if(isset($this->data[$rowNum][$this->check][$recommendedGradeDifficultyNum])) {
                return true;
            }
        }

        return false;

    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        switch ($this->check) {
            case "recommended_difficulty" :
                return 'Total no. of grades must match total no. of difficulty';
            case "recommended_grade" :
                return 'Total no. of difficulty must match total no. of grades';
        }

    }
}
