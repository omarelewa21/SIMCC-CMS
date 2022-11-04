<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetitionMarkingGroupRequest;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionLevels;
use Illuminate\Http\Request;


class MarkingController extends Controller
{
    public function addMarkingGroups (StoreCompetitionMarkingGroupRequest $request) {
        return true;

        $competition = CompetitionLevels::find($level_id)->rounds->competition;
        $competitionStatus = $competition->status;

        if($competitionStatus === "closed") {
            throw ValidationException::withMessages(['competition' => 'The selected competition is close for edit']);
        }

        $countries = Arr::flatten($request->validate([
            "countries" => "required|array",
            "countries.*" => ["required","integer","distinct",Rule::exists("all_countries","id"),Rule::notIn($levelCountries)]
        ]));

        foreach($countries as $country) {
            if($competition->participants->where('country_id',$country)->count() === 0) {
                throw ValidationException::withMessages(['Country' => 'The selected country id have no participants']);
            }
        }

        try {
            CompetitionMarkingGroup::create([
                'competition_level_id' => $level_id,
                'country_group' => $countries,
                'created_by_userid' => auth()->user()->id
            ]);

            return response()->json([
                "status" => 200,
                "message" => "add marking group successful"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "add marking group unsuccessful"
            ]);
        }
    }
}
