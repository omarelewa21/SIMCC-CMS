<?php

namespace App\Rules;

use App\Models\Collections;
use Illuminate\Contracts\Validation\Rule;

class CheckCollectionUse implements Rule
{
    protected string $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $collection = Collections::find($value);
        if( !$collection ){
            $this->message = "Collection '{$collection->name}' is invalid.";
            return false;
        }

        if( $collection->collectionIsInUseByLevel() ){
            $this->message = "Collection '{$collection->name}' is in use by a competition level, you cannot delete it.";
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
