<?php

namespace App\Http\Requests\Competition;

use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rule;

class ConfirmCountryForIntegrityRequest extends FormRequest
{

    private Competition $competition;

    function __construct(Route $route)
    {
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
            'countries'     => "required|array",
            'countries.*'   => "required|array",
            'countries.*.id' => ["required", Rule::exists('competition_countries_for_integrity_check', 'country_id')->where('competition_id', $this->competition->id)],
            'countries.*.is_confirmed' => "required|boolean"
        ];
    }
}
