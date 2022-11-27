<?php

namespace App\Http\Controllers\Api;

use App\Custom\Compute;
use App\Custom\ComputeLevelCustom;
use App\Custom\Marking;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetitionMarkingGroupRequest;
use App\Models\CompetitionMarkingGroup;
use App\Models\Competition;
use App\Models\Countries;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\getActiveParticipantsByCountryRequest;
use App\Http\Requests\UpdateCompetitionMarkingGroupRequest;
use App\Jobs\ComputeLevel;
use App\Models\CompetitionLevels;
use Illuminate\Http\Request;

class MarkingController extends Controller
{
    /**
     * Marking overview page
     * 
     * @param App\Models\Competition $competition
     * 
     * @return Illuminate\Http\Response
     */
    public function markingList(Competition $competition) {
        try {
            $markingList = (new Marking())->markList($competition->load('rounds.levels.collection.sections'));
            return response()->json([
                "status"    => 200,
                "message"   => "Marking progress list retrieve successful",
                "data"      => $markingList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Marking progress list retrieve unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Competition Marking group overview
     * 
     * @param App\Models\Competition $competition
     * 
     * @return Illuminate\Http\Response
     */
    public function markingGroupsList(Competition $competition)
    {
        try {
            $headerData = Competition::whereId($competition->id)->select('id as competition_id', 'name', 'format')->first()->setAppends([]);

            $data = CompetitionMarkingGroup::where('competition_id', $competition->id)
                        ->with('countries:id,display_name as name')->get()->append('totalParticipantsCount');

            return response()->json([
                "status"        => 200,
                "message"       => "Marking preparation list retrieve successful",
                'header_data'   => $headerData,
                'data'          => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Marking preparation list retrieve unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    /**
     * add a new marking group
     * 
     * @param App\Models\Competition $competition
     * @param App\Http\Requests\StoreCompetitionMarkingGroupRequest $request
     * 
     * @return Illuminate\Http\Response
     */
    public function addMarkingGroups(Competition $competition, StoreCompetitionMarkingGroupRequest $request)
    {
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
                "status"    => 500,
                "message"   => "Add marking group unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status" => 200,
            "message" => "Add marking group successful"
        ]);
    }
    
    /**
     * Edit marking group
     * 
     * @param App\Models\CompetitionMarkingGroup $group
     * @param App\Http\Requests\UpdateCompetitionMarkingGroupRequest $request
     * 
     * @return Illuminate\Http\Response
     */
    public function editMarkingGroups(CompetitionMarkingGroup $group, UpdateCompetitionMarkingGroupRequest $request){
        $group->undoComputedResults('active');
        try {
            $group->update([
                'name'                  => $request->name,
                'last_modified_userid'  => auth()->user()->id
            ]);

            DB::table('competition_marking_group_country')->where('marking_group_id', $group->id)->delete();
            foreach($request->countries as $country_id){
                DB::table('competition_marking_group_country')->insert([
                    'marking_group_id'  => $group->id,
                    'country_id'        => $country_id,
                    'created_at'        => now(),
                    'updated_at'        => now()
                ]);
            }

            return response()->json([
                "status"    => 200,
                "message"   => "Edit marking group successful"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Edit marking group unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active participants per country per grade
     * 
     * @param App\Models\Competition $competition
     * @param App\Http\Requests\getActiveParticipantsByCountryRequest $request
     * 
     * @return Illuminate\Http\Response
     */
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
                "status"    => 500,
                "message"   => "Table retrieval was unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compute single level results
     * 
     * @param \App\Models\CompetitionLevels $level
     * 
     * @return response
     */
    public function computeResultsForSingleLevel(CompetitionLevels $level)
    {
        try {
            dispatch(new ComputeLevel($level));
            $level->updateStatus(CompetitionLevels::STATUS_In_PROGRESS);
            return response()->json([
                "status"    => 200,
                "message"   => "Level computing is in progress",
            ], 200);

        } catch (\Exception $e) {
            $level->updateStatus(CompetitionLevels::STATUS_BUG_DETECTED, $e->getMessage());
            return response()->json([
                "status"    => $e->getCode(),
                "message"   => "Level couldn't be computed",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compute results for the competition
     * 
     * @param \App\Models\Competition $competition
     * 
     * @return response
     */
    public function computeCompetitionResults(Competition $competition)
    {
        try {
            if(!(new Marking())->isCompetitionReadyForCompute($competition)){
                return response()->json([
                    "status"    => 406,
                    "message"   => "Some level are not ready to compute yet, please check that all tasks in all levels has answers and answers are uploaded correctly to all level",
                ], 406);
            }

            foreach($competition->rounds as $round){
                foreach($round->levels as $level){
                    ComputeLevelCustom::validateLevelForValidation($level);
                }
            }

            foreach($competition->rounds as $round){
                foreach($round->levels as $level){
                    dispatch(new ComputeLevel($level));
                    $level->updateStatus(CompetitionLevels::STATUS_In_PROGRESS);
                }
            }

            return response()->json([
                "status"    => 200,
                "message"   => "Competition computing is in progress",
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Compute results starting was unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }
}
