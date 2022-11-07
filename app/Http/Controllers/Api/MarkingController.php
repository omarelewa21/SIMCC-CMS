<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetitionMarkingGroupRequest;
use App\Models\CompetitionMarkingGroup;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class MarkingController extends Controller
{
    public function addMarkingGroups(StoreCompetitionMarkingGroupRequest $request)
    {
        $competition = Competition::find($request->id);

        if($competition->status === "closed") {
            throw ValidationException::withMessages(['competition' => 'The selected competition is close for edit']);
        }

        DB::beginTransaction();
        try {
            $markingGroup = CompetitionMarkingGroup::create([
                'competition_id'    => $competition->id,
                'name'              => $request->name,
                'created_by_userid' => auth()->user()->id
            ]);

            foreach($request->countries as $country_id){
                DB::table('competition_marking_group_country')->insert([
                    'marking_group_id'  => $markingGroup->id,
                    'country_id'        => $country_id,
                    'created_at'        => now(),
                    'updated_at'        => now()
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status" => 500,
                "message" => "add marking group unsuccessful"
            ]);
        }

        DB::commit();
        return response()->json([
            "status" => 200,
            "message" => "add marking group successful"
        ]);
    }


    public function markingGroupsList(Request $request)
    {
        $request->validate([
            "id" => ["required", Rule::exists("competition","id")->where('status','active')]
        ]);

        try {
            $headerData = Competition::whereId($request->id)->select('id as competition_id', 'name', 'format')->first()->setAppends([]);
            
            $data = CompetitionMarkingGroup::where('competition_id', $request->id)
                        ->with('countries:id,display_name')->get()->append('totalParticipantsCount');
            
            return response()->json([
                "status"        => 200,
                "message"       => "marking preparation list retrieve successful",
                'header_data'   => $headerData,
                'data'          => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "marking preparation list retrieve unsuccessful",
            ]);
        }
    }
}
