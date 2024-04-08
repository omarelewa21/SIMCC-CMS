<?php

namespace App\Http\Requests\Participant;

use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rule;
use Illuminate\Database\Query\Builder;

class EliminateFromComputeRequest extends FormRequest
{
    private Competition $competition;

    function __construct(Route $route) {
        $this->competition = $route->parameter('competition');
    }

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
            'participants'      => [
                'array',
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($this->has('group_ids')) {
                        $fail('The :attribute must not be present when group_ids is provided.');
                    }
                }
            ],
            'participants.*'    => [
                'required',
                Rule::exists('participants', 'index_no')
                    ->where(function (Builder $query) {
                        $competitionOrganizations = $this->competition->competitionOrganization()
                            ->pluck('id')->toArray();
                        return $query->whereIn('competition_organization_id', $competitionOrganizations);
                    }),
            ],
            'group_ids'        => [
                'array',
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($this->has('participants')) {
                        $fail('The :attribute must not be present when participants is provided.');
                    }
                }
            ],
            'group_ids.*'      => [
                'required',
                Rule::exists('cheating_participants', 'group_id')
                    ->where(fn (Builder $query) => $query->where('competition_id', $this->competition->id)),
            ],
            'mode'             => 'required|in:system,custom',
            'reason'           => 'string',
        ];
    }
}
