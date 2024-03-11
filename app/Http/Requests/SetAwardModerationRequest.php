<?php

namespace App\Http\Requests;

use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;

class SetAwardModerationRequest extends FormRequest
{
    private CompetitionLevels $level;
    private CompetitionMarkingGroup $group;

    function __construct(Route $route)
    {
        $this->level = $route->parameter('level');
        $this->group = $route->parameter('group');
    }
    

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->hasRole(['Admin', 'Super Admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "awards_moderated" => 'required|boolean'
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
            if ($this->levelGroupComputeDontExist()) {
                $validator->errors()->add('compute',"You haven't computed the results for this group and level yet");
            }

            if ($this->ComputeStatusIsNotFinished()) {
                $validator->errors()->add('compute', "You haven't finished computing the results for this group and level yet");
            }

            if($this->awardsIsNotComputed()) {
                $validator->errors()->add('awards', "Some students' award have not computed yet");
            }
        });
    }

    private function levelGroupComputeDontExist(): bool
    {
        return $this->group->levelGroupCompute($this->level->id)->doesntExist();
    }

    private function ComputeStatusIsNotFinished(): bool
    {
        return $this->group->levelGroupCompute($this->level->id)->where('computing_status', '!=', 'Finished')->exists();
    }

    private function awardsIsNotComputed(): bool
    {
        return CompetitionParticipantsResults::where('group_id', $this->group->id)
            ->where('level_id', $this->level->id)
            ->where('award', null)
            ->exists();
    }
}
