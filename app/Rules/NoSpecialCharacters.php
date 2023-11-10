<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class NoSpecialCharacters implements Rule
{
    public function passes($attribute, $value)
    {
        // Use a regular expression to check for special characters
        return preg_match('/^[A-Za-z0-9,\'\s.()\/\\\\-]+$/', $value);
    }

    public function message()
    {
        return 'The :attribute field cannot contain special characters.';
    }
}
