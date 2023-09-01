<?php

namespace App\Rules;

use App\Models\School;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class CheckSchoolUnique implements Rule,DataAwareRule
{
    protected $data;
    protected $message;

    public function setData($data)
    {
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
        $query = School::where('name', $value);

        if(isset($this->data['id'])) {
            $query->where('id','!=',$this->data['id']);
            $school = School::find($this->data['id']);
            $country_id = $school->country_id;
            $province = $this->data['province'] ?? $school->province;
        } else {
            $rowNum = explode(".",$attribute)[1];
            $country_id = auth()->user()->role_id > 1 ? auth()->user()->country_id : $this->data['school'][$rowNum]['country_id'];
            $province = $this->data['school'][$rowNum]['province'];
        }

        $school = $query->where([
            'country_id'    => $country_id,
            'province'      => $province
        ])->first();

        if($school) {
            $this->message = "School name '$school->name' already exists with status $school->status";
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
