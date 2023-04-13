<?php

namespace App\Http\Requests;

use App\Models\CompetitionOrganization;
use App\Models\Participants;
use App\Rules\CheckParticipantGrade;
use App\Rules\CheckSchoolStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;


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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $this->participant = Participants::findOrFail($this->id);
        $rules = [
            'for_partner'       => 'required_if:school_type,1|exclude_if:school_type,0|boolean',
            'name'              => 'required|string|min:3|max:255',
            'class'             => "max:20",
            'grade'             => ['required','integer','min:1','max:99',new CheckParticipantGrade],
            'email'             => ['sometimes','email','nullable'],
            "tuition_centre_id" => ['exclude_if:for_partner,1','exclude_if:school_type,0','integer','nullable',new CheckSchoolStatus(1, $this->participant->country_id)],
            "school_id"         => ['required_if:school_type,0','integer','nullable',new CheckSchoolStatus(0, $this->participant->country_id)],
            'password'          => ['confirmed','min:8','regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!$#%@]).*$/']
        ];

        $user = auth()->user();
        switch($user->role_id) {
            case 0:
            case 1:
                $rules['id'] = "required|integer|exists:participants,id";
                break;
            case 2:
            case 4:
                $organizationId = $user->organization_id;
                $countryId = $user->country_id;
                // $activeCompetitionOrganizationIds = CompetitionOrganization::where(['organization_id'=> $organizationId, 'status' => 'active'])->pluck('id')->toArray();
                // $rules['id'] = ["required","integer", Rule::exists('participants','id')->where("country_id", $countryId)->whereIn("competition_organization_id", $activeCompetitionOrganizationIds)];
                $rules['id'] = ["required","integer", Rule::exists('participants','id')->where("country_id", $countryId)];
                break;
            case 3:
            case 5:
                $schoolId = $user->school_id;
                $rules['id'] = ["required","integer",Rule::exists('participants','id')->where("school_id", $schoolId)];
                break;
        }
        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if($this->participant->status != 'active'){
                $validator->errors()->add('id', 'You can not update a participant that is not active.');
            }
        });
    }
}
