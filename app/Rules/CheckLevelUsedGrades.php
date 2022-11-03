<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Support\Facades\Route;

class CheckLevelUsedGrades implements Rule, DataAwareRule
{
    protected $data;
    protected $message;

    function setData($data)
    {
        // TODO: Implement setData() method.
        $this->data = $data;
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
        $exist = true;
        $this->message = 'The select grades already existed in another level of the same round.';

        switch(Route::currentRouteName()) {
            case "competition.create":
            case "competition.rounds.add":
                $round =  explode(".",$attribute)[1];
                $level =  explode(".",$attribute)[3];
                $grades = $this->data['rounds'][$round]['levels'];
                break;
            case "competition.rounds.edit":
                $level =  explode(".",$attribute)[1];
                $grades = $this->data['levels'];
                break;
        }

        collect($grades)->each(function ($item,$key)  use($level,$value,&$exist) {
            if($key != $level) {
                if(in_array($value,$item['grades'])) {
                 $exist = false;
                 return false;
                }
            }
        });

        return $exist;
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

