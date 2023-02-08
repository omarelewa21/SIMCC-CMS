<?php

namespace App\Http\Requests;

use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use Illuminate\Foundation\Http\FormRequest;

class ParticipantReportWithCertificateRequest extends FormRequest
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
            'index_no'          => 'required|exists:participants',
            'certificate_no'    => 'required|exists:participants'
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
            if(Participants::where('index_no', $this->index_no)->where('certificate_no', $this->certificate_no)->doesntExist()){
                $validator->errors()->add(
                    "Parameters doesn't match",
                    "This certificate number is not for this participant index"
                );
            }else{
                $status = Participants::where('index_no', $this->index_no)->value('status');
                if($status === 'absent'){
                    $validator->errors()->add(
                        "absent",
                        "This participant was absent in this competition"
                    );
                }
                elseif($status !== 'result computed'){
                    $validator->errors()->add(
                        "results not computed",
                        "Results are not computed for this participant"
                    );
                }else{
                    if(CompetitionParticipantsResults::where('participant_index', $this->index_no)->doesntExist()){
                        $validator->errors()->add(
                            'Report is not generated for this participant',
                            "Report is not generated for this participant, please re-compute the results"
                        );
                    }
                }
            }
        });
    }
}
