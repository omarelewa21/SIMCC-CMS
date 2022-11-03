<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

class CheckExistinglevelCollection implements Rule,DataAwareRule
{
    protected $data;
    protected $message;

    public function setData($data)
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
        if(!$value == null) return true;

        switch(Route::currentRouteName()) {
            case "competition.create":
            case "competition.rounds.add":
                $round = intVal(explode(".",$attribute)[1]);
                $level = intVal(explode(".",$attribute)[3]);
                $allLevels = $this->data['rounds'][$round]['levels'];
                break;
            case "competition.rounds.edit":
                $level = intVal(explode(".",$attribute)[1]);
                $allLevels = $this->data['levels'];
                break;
        }

        $duplicate = 0;
        $this->message = 'The selected collection already exist in this round, collection can only exist once per round.';

        //loop and check POST for duplicate entry
        collect($allLevels)->each(function ($round,$key) use($value,$level,&$duplicate) {
            if($key !== $level) {
                if($round['collection_id'] == $value) {
                    $duplicate = 1;
                }
            }
        });

        return $duplicate === 1 ? false : true;
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
