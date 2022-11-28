<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rule;

class EditParticipantAwardRequest extends FormRequest
{

    protected $level;

    function __construct(Route $route)
	{
		$this->level = $route->parameter('level');
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
            '*.participant_index'   => 'required|exists:participants,index_no',
            '*.award'               => ['required', Rule::exists('competition_rounds_awards', 'name')->where('round_id', $this->level->round_id)]
        ];
    }
}
