<?php

namespace App\Http\Requests;

use App\Rules\CheckCompetitionAvailGrades;
use App\Rules\CheckCompetitionEnded;
use App\Rules\CheckOrganizationCompetitionValid;
use App\Rules\CheckParticipantRegistrationOpen;
use App\Rules\CheckSchoolStatus;
use Illuminate\Foundation\Http\FormRequest;

class CreateParticipantRequest extends FormRequest
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
            "role_id"                           => "nullable",
            "participant.*.competition_id"      => ["required","integer","exists:competition,id", new CheckOrganizationCompetitionValid, new CheckCompetitionEnded('create'), new CheckParticipantRegistrationOpen],
            "participant.*.country_id"          => 'exclude_if:role_id,2,3,4,5|required_if:role_id,0,1|integer|exists:all_countries,id',
            "participant.*.organization_id"     => 'exclude_if:role_id,2,3,4,5|required_if:role_id,0,1|integer|exists:organization,id',
            "participant.*.name"                => "required|string|max:255",
            "participant.*.class"               => "required|max:255|nullable",
            "participant.*.grade"               => ["required","integer",new CheckCompetitionAvailGrades],
            "participant.*.for_partner"         => "required|boolean",
            "participant.*.partner_userid"      => "exclude_if:*.for_partner,0|required_if:*.for_partner,1|integer|exists:users,id",
            "participant.*.tuition_centre_id"   => ['exclude_if:*.for_partner,1','required_if:*.school_id,null','integer','nullable',new CheckSchoolStatus(1)],
            "participant.*.school_id"           => ['exclude_if:role_id,3,5','required_if:*.tuition_centre_id,null','nullable','integer',new CheckSchoolStatus],
            "participant.*.email"               => ['sometimes', 'email','nullable']
        ];
    }
}
