<?php

namespace App\Http\Requests;

use App\Models\ParticipantsAnswer;
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
            'index_no'  => 'required|exists:participants',
            'level_id'  => 'required|integer|exists:competition_levels,id'
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
            if(
                ParticipantsAnswer::where([
                    ['level_id', $this->level_id], ['participant_index', $this->index_no]
                ])->doesntExist()
            ){
                $validator->errors()->add('No Answers', 'No answers has been uploaded for this participant for this level');
            }

            if(
                ParticipantsAnswer::where(
                    [ ['level_id', $this->level_id], ['participant_index', $this->index_no]
                ])->whereNull('score')->exists()
            ){
                $validator->errors()->add('Answers Not Computed', 'Some participant answers are not computed yet');
            }
        });
    }
}
