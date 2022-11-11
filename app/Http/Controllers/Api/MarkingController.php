<?php

namespace App\Http\Controllers\Api;

use App\Custom\Marking;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetitionMarkingGroupRequest;
use App\Models\CompetitionMarkingGroup;
use App\Models\Competition;
use App\Models\Countries;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\getActiveParticipantsByCountryRequest;
use Illuminate\Http\Request;

class MarkingController extends Controller
{
    public function addMarkingGroups(StoreCompetitionMarkingGroupRequest $request, Competition $competition)
    {
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
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status" => 200,
            "message" => "add marking group successful"
        ]);
    }


    public function markingGroupsList(Competition $competition)
    {
        try {
            $headerData = Competition::whereId($competition->id)->select('id as competition_id', 'name', 'format')->first()->setAppends([]);
            
            $data = CompetitionMarkingGroup::where('competition_id', $competition->id)
                        ->with('countries:id,display_name as name')->get()->append('totalParticipantsCount');
            
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
            ], 500);
        }
    }

    public function getActiveParticipantsByCountryByGrade(Competition $competition, getActiveParticipantsByCountryRequest $request)
    {
        try {
            $grades = $competition->participants()->whereIn('participants.country_id', $request->countries)
                    ->where('participants.status', 'active')->distinct()->pluck('grade')->toArray();
            
            $countries = [];
            $data = [];
            
            foreach($request->countries as $country_id){
                $country = Countries::find($country_id);
                $countries[] = $country->display_name;
                foreach($grades as $grade){
                    $data[$country->display_name][$grade] = 
                        $competition->participants()->where('participants.country_id', $country_id)
                        ->where('participants.status', 'active')->where('participants.grade', $grade)->count();
                }
            }

            return response()->json([
                "status"        => 200,
                "message"       => "Table retrieval was successful",
                'grades'        => $grades,
                'countries'     => $countries,
                'data'          => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Table retrieval was unsuccessful",
            ], 500);
        }
    }

    public function markingList (Competition $competition, Request $request) {
        try {
            $markingList = (new Marking())->markList($competition);
            return response()->json([
                "status"    => 200,
                "message"   => "marking progress list retrieve successful",
                "data"      => $markingList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "marking progress list retrieve unsuccessful",
                "error"     => $e->getMessage()
            ]);
        }
    }
}
