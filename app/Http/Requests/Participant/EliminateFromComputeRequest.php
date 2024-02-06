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
        $this->competition = Competition::find($route->parameter('competition'));
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
            'participants'      => 'required|array',
            'participants.*'    => [
                'required',
                Rule::exists('participants', 'index_no')
                    ->where(function (Builder $query) {
                        $competitionOrganizations = $this->competition->competitionOrganization()
                            ->pluck('id')->toArray();
                        return $query->whereIn('competition_organization_id', $competitionOrganizations);
                    }),
            ]
        ];
    }
}
