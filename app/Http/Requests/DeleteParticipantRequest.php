<?php

namespace App\Http\Requests;

use App\Rules\CheckCompetitionEnded;
use App\Rules\CheckDeleteParticipant;
use App\Rules\CheckParticipantDeleteExpire;
use Illuminate\Foundation\Http\FormRequest;

class DeleteParticipantRequest extends FormRequest
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
            "id"    => "array",
            "id.*"  => [
                "required", "integer", 'bail',
                new CheckDeleteParticipant(auth()->user()->role_id),
                new CheckCompetitionEnded('delete'),
                new CheckParticipantDeleteExpire(7)
            ],
        ];
    }
}
