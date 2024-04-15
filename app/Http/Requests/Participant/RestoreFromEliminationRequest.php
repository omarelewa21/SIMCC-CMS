<?php

namespace App\Http\Requests\Participant;

use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;

class RestoreFromEliminationRequest extends FormRequest
{
    use \App\Traits\IntegrityTrait;

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
            'participants'      => "required|array|min:1",
            'participants.*'    => [
                'required',
                Rule::exists('participants', 'index_no')
                    ->where(function (Builder $query) {
                        $competitionOrganizations = $this->competition->competitionOrganization()
                            ->pluck('id')->toArray();
                        return $query->whereIn('competition_organization_id', $competitionOrganizations);
                    }),
            ],
            'mode'             => 'required|in:system,custom',
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
            $confirmedCountries = $this->hasParticipantBelongsToConfirmedCountry($this->competition, $this->participants);
            if ($confirmedCountries) {
                $validator->errors()->add(
                    'countries',
                    sprintf(
                        "You need to revoke IAC confirmation from these countries: %s, before you can remove IAC incidents from those countries."
                        , Arr::join($confirmedCountries, ', ', ' and ')
                    )
                );
            }
        });
    }
}
