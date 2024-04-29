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
            'global_rank'   => 'integer|min:1',
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
            $result = CompetitionParticipantsResults::where('participant_index', $this->participant->index_no)
                ->first();

            if($result && $result->award) return;
            if($this->filled('award')) return;

            $validator->errors()->add('award', 'The award field is required.');
        });
    }
}
