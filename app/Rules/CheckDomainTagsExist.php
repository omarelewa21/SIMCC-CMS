<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use App\Models\DomainsTags;

class CheckDomainTagsExist implements Rule, DataAwareRule
{
    protected $data = [];
    protected $message;

    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function __construct()
    {
        // Constructor left empty since it doesn't need any initialization.
    }

    public function passes($attribute, $value)
    {
        $row = explode(".", $attribute)[0];
        $col = explode(".", $attribute)[2];

        if ($this->data[$row]["is_tag"] == 1) {
            $result = DomainsTags::whereNull("domain_id")
                ->where("name", $value)
                ->exists();
            $this->message = 'The tag ' . $value . ' already exists';
        } elseif (isset($this->data[$row]['domain_id'])) {
            $result = DomainsTags::where("name", $value)
                ->exists();
            $this->message = 'The topic ' . $value . ' already exists';
        } else {
            if ($col === '0') {
                $result = DomainsTags::whereNull('domain_id')->where('name', $value)->exists();
                $this->message = 'The domain ' . $value . ' already exists';
            } elseif ($col === '1') {
                $result = DomainsTags::whereNotNull('domain_id')->where('name', $value)->exists();
                $this->message = 'The topic ' . $value . ' already exists';
            }
        }

        return !$result;
    }

    public function message()
    {
        return $this->message;
    }
}
