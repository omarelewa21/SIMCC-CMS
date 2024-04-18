<?php

namespace App\Http\Requests\Participant;

use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;

class EditResultRequest extends FormRequest
{
    private $awards;
    private Participants $participant;

    function __construct(Route $route)
    {
        $this->participant = $route->parameter('participant');
        CompetitionParticipantsResults::where('participant_index', $this->participant->index_no)->firstOrFail();
        $competitionRound = $this->participant->competition()->with('rounds.roundsAwards')->first()
            ->rounds->first();

        $this->awards = collect(['PERFECT SCORE'])
            ->merge($competitionRound->roundsAwards->pluck('name'))
            ->push($competitionRound->default_award_name);
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'award'         => 'in:' . $this->awards->implode(','),
            'country_rank'  => 'integer|min:1',
            'school_rank'   => 'integer|min:1',
            'global_rank'   => 'string',
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
            if($this->hasNoResultRecord()) {
                $validator->errors()->add('participant', 'Participant has no result record');
            }
            if ($this->has('global_rank')) {
                if($this->formatIsNotValid()) {
                    $validator->errors()->add('global_rank', "Global rank format is not valid, it should be like this 'GOLD 1' and award should be in: {$this->awards->implode(', ')}");
                    return;
                }
                if($this->awardIsNotValid()) {
                    $validator->errors()->add('global_rank', 'Global rank award part and participant award should be the same');
                }
                if(!$this->isCorrectGlobalRankNumber()) {
                    $validator->errors()->add('global_rank', 'Global rank number should be at least 1');
                }
            }
        });
    }

    private function formatIsNotValid()
    {
        return !preg_match("/^({$this->awards->implode('|')}) \d+$/", $this->global_rank);
    }

    private function awardIsNotValid()
    {
        $globalRankAwardPart = trim(preg_replace('/[0-9]/', '', $this->global_rank));

        if($this->has('award'))
            return $globalRankAwardPart !== $this->award;

        return CompetitionParticipantsResults::where('participant_index', $this->route('participant')->index_no)
            ->value('award') !== $globalRankAwardPart;
    }

    private function hasNoResultRecord()
    {
        return CompetitionParticipantsResults::where('participant_index', $this->route('participant')->index_no)->doesntExist();
    }

    private function isCorrectGlobalRankNumber()
    {
        $globalRankNumber = last(explode(" ", $this->global_rank));
        return is_numeric($globalRankNumber) && $globalRankNumber >= 1;
    }
}
