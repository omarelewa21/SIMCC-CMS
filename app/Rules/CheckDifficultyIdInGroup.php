<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use App\Models\TaskDifficulty;

class CheckDifficultyIdInGroup implements Rule, DataAwareRule
{
    /**
     * All of the data under validation.
     *
     * @var array
     */

    protected $data = [];
    protected $message;

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
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        $found = TaskDifficulty::where(['id' => $value,'difficulty_groups_id'=> $this->data['id']])->count();

        if($found == 0) {
            $this->message = 'The selected '.$attribute.' is invalid.';
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
        return $this->message;
    }
}
