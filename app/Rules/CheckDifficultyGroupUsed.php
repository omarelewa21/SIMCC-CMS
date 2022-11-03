<?php

namespace App\Rules;

use App\Models\TaskDifficulty;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CheckDifficultyGroupUsed implements Rule
{
    protected $message;
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
        $groupDifficultyIds = TaskDifficulty::where('difficulty_groups_id',$value)->pluck('id')->toArray();
        $found = DB::table('competition_task_difficulty')->whereIn('difficulty_id',$groupDifficultyIds)->count();

        if($found > 0) {
            $this->message = 'The selected '.$attribute.' is current in use.';
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
