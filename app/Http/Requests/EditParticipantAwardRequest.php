<?php

namespace App\Http\Requests;

use App\Models\CompetitionParticipantsResults;
use App\Models\LevelGroupCompute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rule;

class EditParticipantAwardRequest extends FormRequest
{

    protected $level;

    function __construct(Route $route)
	{
		$this->level = $route->parameter('level')->load('rounds.roundsAwards');
	}
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $awards = $this->level->rounds->roundsAwards->pluck('name');
        $awards->push($this->level->rounds->default_award_name);
        $awards->push("PERFECT SCORE");
        return [
            '*.participant_index'   => ['required', Rule::exists('competition_participants_results', 'participant_index')->where('level_id', $this->level->id)],
            '*.award'               => ['required', Rule::in($awards->toArray())]
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if(!isset($this['0'])) return;

            $firstIndex = $this['0']['participant_index'];
            $groupId = CompetitionParticipantsResults::where('participant_index', $firstIndex)->value('group_id');
            $levelGroupModerationCompleted = LevelGroupCompute::where('level_id', $this->level->id)
            ->where('group_id', $groupId)->value('awards_moderated');

            if($levelGroupModerationCompleted){
                $validator->errors()->add('moderation', 'Moderation is completed, you need to revert moderation status to edit awards.');
            }
        });
    }
}
