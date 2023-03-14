<?php

namespace App\Http\Requests;

use App\Rules\AddOrganizationDistinctIDRule;
use App\Rules\CheckOrganizationCountryPartnerExist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddOrganizationRequest extends FormRequest
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
            "competition_id"                        => 'required|integer|exists:competition,id',
            "organizations"                         => 'required|array',
            "organizations.*.organization_id"       => ["required", "integer", Rule::exists('organization',"id")->where(fn($query) => $query->where('status', 'active')), new AddOrganizationDistinctIDRule],
            "organizations.*.country_id"            => ['required', 'integer', new CheckOrganizationCountryPartnerExist],
            "organizations.*.translate"             => "json",
            "organizations.*.edit_sessions.*"       => 'boolean',
        ];
    }
}
