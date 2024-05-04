<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\ParticipantsAnswer;
use App\Models\LevelGroupCompute;
use App\Models\PossibleSimilarAnswer;
use Exception;

class ComputeLevelUnderModeration implements Rule
{
    /**
     * Perform validation.
     *
     * @param  string  $attribute Name of the attribute being validated.
     * @param  mixed  $value The value of the attribute.
     * @return bool
     */

    public function passes($attribute, $value)
    {
        $possibleKey = PossibleSimilarAnswer::findOrFail($value);
        $levelId = $possibleKey->level_id;
        $participantsAnswersIndices = $possibleKey->participants_answers_indices;
        foreach ($participantsAnswersIndices as $answerId) {
            $participantAnswer = ParticipantsAnswer::with('participant')
                ->find($answerId);

            $participantCountryId = $participantAnswer->participant->country->id;
            $markingGroupsCountries = $participantAnswer->participant->competition->groups->map(function ($group) use ($participantCountryId) {
                if ($group->countries->pluck('id')->contains($participantCountryId)) {
                    return $group->id;
                }
            });

            foreach ($markingGroupsCountries->filter() as $group) {
                if (LevelGroupCompute::where('group_id', $group)
                    ->where('level_id', $levelId)
                    ->where('awards_moderated', 1)
                    ->exists()
                ) {
                    throw new Exception($this->message());
                }
            }
            return true;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return "Attention!\nYou are attempting to modify the unique answer after the moderation process has been completed. To proceed with updates, please revert the moderation status to 'Moderation in-Progress'.\n\nNote that changing the unique answer after moderation process has completed will also require a complete re-mark and moderation for all associated countries link to this grade. Please ensure to review and re-moderate accordingly to maintain data accuracy and integrity.";
    }
}
