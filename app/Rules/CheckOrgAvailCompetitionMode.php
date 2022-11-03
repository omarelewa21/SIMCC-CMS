<?php

namespace App\Rules;

use App\Models\CompetitionOrganization;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class CheckOrgAvailCompetitionMode implements Rule, DataAwareRule
{
    protected $message;
    protected $data;

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
          $competitionMode = CompetitionOrganization::findOrFail($this->data['id'])->competition->competition_mode;
          $this->message = 'The selected competition mode is not allowed.';
          switch($competitionMode) {
              case 0 :
              case 1 :
                  if($value !== $competitionMode) return false;
                  break;
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
