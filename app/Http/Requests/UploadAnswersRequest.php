<?php

namespace App\Http\Requests;

use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadAnswersRequest extends FormRequest
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
        $competition = Competition::findOrFail($this->competition_id);
        return [
            'competition_id'        => 'required|exists:competitions,id',
            'participants'          => 'required|array',
            'participants.*.grade'  => 'required|string',
            'participants.*.index_number' => Rule::exists('participants', 'index_no')
                ->where(function ($query) use ($competition) {
                    $query->join('competition_organization', 'participants.competition_organization_id', '=', 'competition_organization.id')
                        ->where('competition_organization.competition_id', $competition->id);
            }),
            'participants.*.answers' => 'required|array',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'participants.*.index_number.in' => 'Participant with index number :input is not registered for this competition.',
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
        return;
        $validator->after(function ($validator) {
            // check if participants provided in the request belongs to competition included in the request
            $competition = Competition::find($this->competition_id);
            $participants = $competition->participants()->pluck('index_no')->toArray();
            $indexes = [];
            foreach($this->participants as $participant){
                if(!in_array($participant['index_number'], $participants)){
                    $indexes[] = $participant['index_number'];
                }
            }
            if(count($indexes) > 0){
                $validator->errors()->add('participants', "Participants with index numbers: ". implode(', ', $indexes) ." are not registered for this competition.");
            }
        });
    }
}
