<?php

namespace App\Http\Requests;

use App\Models\CompetitionLevels;
use App\Models\CompetitionParticipantsResults;
use Illuminate\Foundation\Http\FormRequest;

class ParticipantReportRequest extends FormRequest
{
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
        return [
            'participant_index'     => 'required|exists:competition_participants_results',
            'level_id'              => 'required|integer|exists:competition_levels,id'
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
        // $validator->after(function ($validator) {
        //     if(is_null(
        //         CompetitionParticipantsResults::where([
        //             ['level_id', $this->level_id], ['participant_index', $this->participant_index]
        //         ])->value('report')
        //     )){
        //         $levelName = CompetitionLevels::whereId($this->level_id)->value('name');
        //         $validator->errors()->add(
        //             'Report is not generated for this participant',
        //             "Report is not generated for this participant, please re-run the computing for level $levelName to generate report for this level participants"
        //         );
        //     }
        // });
    }
}
