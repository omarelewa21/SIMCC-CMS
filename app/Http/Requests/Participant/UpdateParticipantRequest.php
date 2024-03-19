<?php

namespace App\Http\Requests\participant;

use App\Models\Participants;
use App\Rules\CheckParticipantGrade;
use App\Rules\CheckSchoolStatus;
use App\Rules\CheckUniqueIdentifierWithCompetitionID;
use App\Rules\ParticipantIDOnUpdateRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateParticipantRequest extends FormRequest
{

    private Participants $participant;
    
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
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->participant = Participants::findOrFail($this->id);
        $this->merge([
            'school_type' => $this->participant->tuition_centre_id ? 1 : 0,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'id'                => ['required', 'integer', 'exists:participants,id', new ParticipantIDOnUpdateRule],
            'for_partner'       => 'required_if:school_type,1|exclude_if:school_type,0|boolean',
            'name'              => 'required|string|min:3|max:255',
            'class'             => "max:20",
            'grade'             => ['required', 'integer', 'min:1', 'max:99', new CheckParticipantGrade],
            'school_type'       => 'required|in:0,1',
            'email'             => ['sometimes', 'email', 'nullable'],
            "tuition_centre_id" => ['exclude_if:for_partner,1', 'exclude_if:school_type,0', 'integer', 'nullable', new CheckSchoolStatus(1, $this->participant->country_id)],
            "school_id"         => ['required_if:school_type,0', 'integer', 'nullable', new CheckSchoolStatus(0, $this->participant->country_id)],
            'password'          => ['confirmed', 'min:8', 'regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!$#%@]).*$/'],
            "identifier"        => [new CheckUniqueIdentifierWithCompetitionID($this->participant)],
        ];
    }
}
