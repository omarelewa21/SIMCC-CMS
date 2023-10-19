<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\School;

class CheckSchoolName implements Rule
{
    public function __construct()
    {
        //
    }

    public function passes($attribute, $value)
    {
        return School::where('name', 'like', '%' . $value . '%')->exists();
    }

    public function message()
    {
        return ':attribute is invalid. A school with a similar name does not exist.';
    }
}
