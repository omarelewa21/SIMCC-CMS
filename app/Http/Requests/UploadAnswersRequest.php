<?php

namespace App\Http\Requests;

use App\Models\Competition;
use App\Models\Participants;
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
        return [
            'competition_id'        => 'required|exists:competition,id',
            'participants'          => 'required|array',
            'participants.*.grade'  => 'required|string|in:'. implode(',', array_map(fn($grade) => "Grade $grade", Participants::ALLOWED_GRADES)),
            'participants.*.index_number' => [
                'required', Rule::exists('participants', 'index_no')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'participants.*.answers' => 'required|array',
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
