<?php

namespace App\Http\Requests;

use App\Models\CompetitionParticipantsResults;
use App\Models\Roles;
use App\Models\School;
use App\Models\User;
use App\Rules\CheckSchoolUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolRequest extends FormRequest
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
            "id"        => "required|integer|exists:schools,id",
            "name"      => ["sometimes", "required", "distinct", "string", new CheckSchoolUnique, Rule::notIn(['Organization School','ORGANIZATION SCHOOL','organization school'])],
            "address"   => "max:255",
            "postal"    => "max:255",
            "phone"     => "required|regex:/^[0-9]*$/",
            "email"     => "required|email",
            "province"  => "sometimes|required|max:255",
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
        $school = School::where('id', $this->id)->where('status', '!=', 'deleted')->firstOrFail();
        $user = auth()->user();

        $validator->after(function ($validator) use($school, $user){
            if ($user->hasRole(['Country Partner', 'Country Partner Assistant'])){
                if(in_array($school->status, ['pending', 'rejected'])){
                    $allowedIds = User::where([
                        'organization_id' => $user->organization_id,
                        'country_id' => $user->country_id
                    ])
                    ->whereIn('role_id', [Roles::COUNTRY_PARTNER_ID, Roles::COUNTRY_PARTNER_ASSISTANT_ID])
                    ->pluck('id');

                    if (!$allowedIds->contains($school->created_by_userid))  {
                        $validator->errors()->add('Permission Denied', "Only allowed to edit pending school created by you or your assistances");
                    };
                }

                if($school->country_id != $user->country_id) {
                    $validator->errors()->add('Permission Denied', "Only allowed to edit school's from current country");
                }

                if($this->schoolIsInvolvedInComputedComptitions()) {
                    $validator->errors()->add('Permission Denied', "School is involved in computed competition, Please contact adminstrator to change its name");
                
                }
            }
            if ($user->hasRole(['Teacher', 'School Manager']) && $user->school_id != $school->id) {
                $validator->errors()->add('Permission Denied', "Only allowed to edit own school");
            }
        });
    }

    /**
     * Check if school is involved in computed competition
     *
     * @return bool
     */
    private function schoolIsInvolvedInComputedComptitions(): bool
    {
        return $this->filled('name')
            && CompetitionParticipantsResults::whereRelation('participant', 'school_id', $this->id)
                ->whereNotNull('global_rank')
                ->exists();
    }
}
