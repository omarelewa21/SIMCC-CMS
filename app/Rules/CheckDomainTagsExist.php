<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use App\Models\DomainsTags;

class CheckDomainTagsExist implements Rule, DataAwareRule
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
        $row = explode(".",$attribute)[0];

        if(isset($this->data[$row]['domain_id'])) {
                $result = DomainsTags::where("domain_id",$this->data[$row]['domain_id'])
                    ->where("name",[$value])
                    ->exists();

                $this->message = 'The domain '.$attribute.' already exists';

                return !$result;
        }
        elseif ($this->data[$row]["is_tag"] == 1 )
        {
            $result = DomainsTags::whereNull("domain_id")
                ->where("name",$value)
                ->exists();

            $this->message = 'The tag '.$attribute.' already exists';

            return !$result;
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
