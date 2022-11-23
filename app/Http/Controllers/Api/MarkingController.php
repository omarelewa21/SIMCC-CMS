<?php

namespace App\Http\Controllers\Api;

use App\Custom\Marking;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetitionMarkingGroupRequest;
use App\Models\CompetitionMarkingGroup;
use App\Models\Competition;
use App\Models\Countries;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\getActiveParticipantsByCountryRequest;
use App\Http\Requests\UpdateCompetitionMarkingGroupRequest;
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
     * @param App\Models\CompetitionMarkingGroup $competition
     * 
     * @return Illuminate\Http\Response
     */
    public function changeComputeStatus(Competition $competition) {
        $validForComputing = (new Marking())->checkIfShouldChangeMarkingGroupStatus($competition->load('rounds.levels.collection.sections'));
        if($validForComputing) {
            foreach($competition->groups as $group){
                $group->undoComputedResults('computing');
            }
            return response()->json([
                "status" => 200,
                "message" => "marking in progress"
            ]);
        } else {
            return response()->json([
                "status"    => 405,
                "message"   => "Unable to mark, make competition is configured"
            ], 405);
        }
    }

    /**
     * currently only support single mcq structure
     * 
     * @param App\Models\Competition $competition
     * 
     * @return array
     */
    public function computeGroupResults(Competition $competition){
        try {
            $mark = (new Marking())->computingResults($competition->load('groups'));
        } catch (\Exception $e) {
            //throw $th;
        }
    }

    /**
     * 
     * 
     * @param App\Models\CompetitionMarkingGroup $group
     * 
     * @return array
     */
    public function editGroupComputedList(CompetitionMarkingGroup $group) {
        try {
            $level =  $group->level;
            $level_id = $group->competition_level_id;
            $grades = $level->grades;
            $default_award =  $level->rounds->default_award_name;
            $competition_id = $level->rounds->competition->id;
            $schools = School::all()->mapWithKeys(function($items,$keys) {
                return [$items['id'] => ['name'=>$items['name'],'private' => $items['private']]];
            })->toArray();
            $countries = Countries::all()->mapWithKeys(function($items,$keys) {
                return [$items['id'] => $items['display_name']];
            });

            $participantIndex = CompetitionOrganization::with(['participants' => function($query) use($grades) {
                $query->whereIn('grade',$grades);
            }])->where('competition_id',$competition_id)->whereIn('country_id',$group->country_group)->get()->pluck('participants')->collapse()->pluck('index_no')->toArray();

            $level = CompetitionLevels::with(['rounds:id,name,competition_id,default_award_name','rounds.roundsAwards' => function ($query) {
                $query->Select(['round_id','id','name'])->orderBy('id');
            },'rounds.competition' => function ($query) {
                $query->setEagerLoads([])->select(['id','name']);
            }])->find($level_id,['id','round_id','name'])->toArray();

            $participantResults = CompetitionParticipantsResults::with(['participants:index_no,name,school_id,grade,country_id'])->whereIn('participant_index',$participantIndex)->orderBy('points','desc')->orderBy('ref_award_id')->get()->map( function ($item) use($schools,$countries) {
                return [
                    'participant_index' => $item['participant_index'],
                    'participant' => $item['participants']['name'],
                    'ref_award_id' => $item['ref_award_id'],
                    'award_id' => $item['award_id'],
                    'points' => $item['points'],
                    'private' => $schools[$item['participants']['school_id']]['private'],
                    'school' => $schools[$item['participants']['school_id']]['name'] . ' ' . ($schools[$item['participants']['school_id']]['private'] == 1 ? " (Tuition)" : ''),
                    'grade' => $item['participants']['grade'],
                    'country' => $countries[$item['participants']['country_id']]
                ];
            });

            return [
                'status' => 200,
                'message' => 'get group computed results list successful',
                'data' => [
                    "competition_name" => $level['rounds']['competition']['name'],
                    "round" => $level['rounds']['name'],
                    "default_award" => $default_award,
                    "level" => $level['name'],
                    "level_id" => $level['id'],
                    "award_type" => $level['rounds']['competition']['award_type_name'],
                    "awards" => $level['rounds']['rounds_awards'],
                    "results" => $participantResults,
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status'    => 500,
                'message'   => 'Get group computed results list unsuccessful',
                "error"     => $e->getMessage()
            ];
        }
    }

    public function editGroupComputedResult(Request $request) {
        $results = $this->editGroupComputedList($request)['data'];
        $participantIndex = Arr::pluck($results['results'],'participant_index');
        $awards_id = Arr::pluck($results['awards'],'id');

        $validated = $request->validate([
            'set_awards.*.index_no' => ['required','distinct',Rule::in($participantIndex)],
            'set_awards.*.award_id' => ['required',Rule::in($awards_id)],
        ]);

        try {
            DB::beginTransaction();
            foreach ($validated['set_awards'] as $row) {
                $participant = CompetitionParticipantsResults::where('participant_index', $row['index_no'])->first();
                $participant->award_id = $row['award_id'];
                $participant->save();
            }
            DB::commit();

            return [
                'status' => 200,
                'message' => "Edit competition group results successful"
            ];
        }
        catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => "Edit competition group results unsuccessful"
            ];

        }
    }
}
