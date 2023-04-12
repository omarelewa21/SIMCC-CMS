<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\CollectionSections;
use App\Models\CompetitionLevels;
use App\Models\CompetitionOrganization;
use App\Models\CompetitionOverallAwards;
use App\Models\CompetitionOverallAwardsGroups;
use App\Models\CompetitionParticipantsResults;
use App\Models\CompetitionRounds;
use App\Models\CompetitionRoundsAwards;
use App\Models\CompetitionTasksMark;
use App\Models\ParticipantsAnswer;
use App\Models\Tasks;
use App\Rules\CheckCompetitionAvailGrades;
use App\Rules\CheckExistinglevelCollection;
use App\Rules\CheckLevelUsedGrades;
use App\Rules\CheckOrgAvailCompetitionMode;
use App\Rules\CheckRoundAwards;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Competition;
use App\Models\CompetitionOrganizationDate;
use App\Helpers\General\CollectionHelper;
use App\Http\Requests\AddOrganizationRequest;
use App\Http\Requests\CompetitionListRequest;
use App\Http\Requests\CreateCompetitionRequest;
use App\Http\Requests\DeleteCompetitionRequest;
use App\Http\Requests\UpdateCompetitionRequest;
use App\Rules\CheckLocalRegistrationDateAvail;
use App\Services\CompetitionService;

//update participant session once competition mode change, add this changes once participant session done

class CompetitionController extends Controller
{
    /**
     * Show the form for creating a new competition.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(CreateCompetitionRequest $request)
    {
        DB::beginTransaction();
        try{
            $request->merge([
                'created_by_userid' => auth()->user()->id,
                'allowed_grades'    => collect($request->allowed_grades)->unique()->values()->toArray()
            ]);
            $competition = Competition::create($request->all());
            CompetitionService::addOrganizations($request->organizations, $competition->id);
            $this->addRounds($request->rounds, $competition);

        }catch(\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"    => 500,
                "message"   => "Competition create unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 201,
            "message"   => "Competition create successful",
            "id"        => $competition->id
        ]);
    }

    public function update(Competition $competition, UpdateCompetitionRequest $request) {
        try {
            $earliestOrganizationCompetitionDate = CompetitionOrganization::where('competition_id', $competition->id)
                ->join('competition_organization_date', 'competition_organization_date.competition_organization_id', 'competition_organization.id')
                ->select('competition_organization_date.competition_date')
                ->orderBy('competition_date')
                ->first();

            !$request->has('alias')  ?: $competition->alias = $request->alias;
            !$request->has('name')   ?: $competition->name = $request->name;

            $competition->global_registration_date = $request->global_registration_date;
            if($request->has('global_registration_end_date')){
                $competition->global_registration_end_date = $request->global_registration_end_date;
            }
            $competition->competition_start_date = $request->competition_start_date;
            $competition->competition_end_date = $request->competition_end_date;
            $competition->competition_mode = $request->competition_mode;
            $competition->difficulty_group_id = $request->difficulty_group_id;

            switch($request->competition_mode) {
                //update organization competition mode based on the competition's level competition mode
                case 0:
                    CompetitionOrganization::where('competition_id', $competition->id)
                        ->whereIn('competition_mode',[1,2])
                        ->update([
                            'competition_mode'  => 0,
                            'updated_at'        => now()
                        ]);
                    break;
                case 1:
                    CompetitionOrganization::where('competition_id', $competition->id)
                        ->whereIn('competition_mode',[0,2])
                        ->update([
                            'competition_mode'  => 1,
                            'updated_at'        => now()
                        ]);
                    break;
            }

            switch ($competition->format) {
                case Competition::LOCAL:
                    if (is_null($earliestOrganizationCompetitionDate) || now()->lt($earliestOrganizationCompetitionDate->competition_date)) {
                        // allow to edit grades if registration is still open
                        $competition->allowed_grades = $request->allowed_grades;
                    }
                    break;
                case Competition::GOLBAL:
                    if ( now()->lte($competition->global_registration_end_date) ) {
                        // allow to edit grades if registration is still open
                        $competition->allowed_grades = $request->allowed_grades;
                    }
                    break;
            }
            $competition->save();

            return response()->json([
                "status"    => 200,
                "message"   => "Competition update successful",
            ]);

        }
        catch(ModelNotFoundException $e){
            // do task when error
            return response()->json([
                "status"    => 500,
                "message"   => "Competition create unsuccessful",
            ], 500);
        }
    }

    public function list (CompetitionListRequest $request)
    {
        try {
            if($request->has('id') && Competition::whereId($request->id)->exists()){
                return $this->show(Competition::find($request->id));
            }
            $limits = $request->limits ? $request->limits : 10;
            $searchKey = isset($request->search) ? $request->search : null;

            $competitionModel = Competition::AcceptRequest(['id','status', 'format', 'name']);

//            return ($competitionModel)->get();

            switch(auth()->user()->role_id) {
                case 0:
                case 1:
                    $competitionModel->with(['competitionOrganization','overallAwardsGroups.overallAwards','rounds.roundsAwards','rounds.levels' => function($query) {
                        $query->orderBy('id');
                    }]);
                    break;
                case 2:
                case 4:
                case 3:
                case 5:
                    $competitionModel->with(['competitionOrganization' => function ($query)  {
                        $query->where(['organization_id' => auth()->user()->organization_id])
                            ->where('country_id', auth()->user()->country_id);
                    }])->where('status','active');
                    break;
            }

            $competitionCollection = $competitionModel->get()->filter(function ($row) {
                return count($row['competitionOrganization']) > 0;
            });

            /**
             * Lists of availabe filters
             */
            $availUserStatus = $competitionCollection->map(function ($item) {
                return $item['status'];
            })->unique()->values();
            $availFormat = $competitionCollection->map(function ($item) {
                return $item['format'];
            })->unique()->values();

            /**
             * EOL Lists of availabe filters
             */

            $availForSearch = array("name");
            $competitionList = CollectionHelper::searchCollection($searchKey, $competitionCollection, $availForSearch, $limits);
            $data = array("filterOptions" => ['status' => $availUserStatus, 'format' => $availFormat], "competitionList" => $competitionList);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        } catch (QueryException $e) {
            return response()->json([
                "status" => 500,
                "message" => "competition list retrieve unsuccessful"
            ]);
        } catch (ModelNotFoundException $e) {
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "competition list retrieve unsuccessful"
            ]);
        }
    }

    public function show(Competition $competition)
    {
        $data = $competition->load(['rounds.levels', 'rounds.roundsAwards', 'competitionOrganization' => function ($query) {
            if(!is_null(auth()->user()->organization_id)) {
                $query->where(['organization_id' => auth()->user()->organization_id])
                    ->where('country_id', auth()->user()->country_id);
            }
        } , 'taskDifficultyGroup', 'taskDifficulty']);

        return response()->json([
            "status"    => 200,
            "message"   => "Competition retrieved successfully",
            "data"      => $data
        ]);
    }

    public function delete(DeleteCompetitionRequest $request)
    {
        try{
            if(Competition::destroy($request->id)) {
                return response()->json([
                    "status"    => 200,
                    "message"   => "delete competition successful"
                ]);
            }
        }catch(\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Invalid record",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function addRoundsRoute(Request $request)
    {
        try {
            $request->validate([
                "competition_id"                        => 'required|exists:competition,id',
                "rounds"                                => "array|required",
                "rounds.*.name"                         => "required|regex:/^[\.\,\s\(\)\[\]\w-]*$/",
                "rounds.*.round_type"                   => ["required", "integer", Rule::in([0, 1])],
                "rounds.*.team_setting"                 => ["exclude_if:rounds.*.round_type,0", "required_if:rounds.*.round_type,1", "integer", Rule::in([0, 1])],
                "rounds.*.individual_points"            => ["exclude_if:rounds.*.round_type,0", "required_if:rounds.*.round_type,1", "integer", Rule::in([0, 1])],
                "rounds.*.award_type"                   => "required|integer|boolean",
                "rounds.*.assign_award_points"          => "required|integer|boolean",
                "rounds.*.default_award_name"           => "required|string",
                "rounds.*.default_award_points"         => "integer|nullable",
            ]);

            $this->addRounds($request->rounds, Competition::find($request->competition_id));
            return [
                "status"    => 200,
                "message"   => "Add rounds is successfull",
            ];

        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Add rounds is not successfull",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    private function addRounds(array $rounds, Competition $competition)
    {
        $rounds = collect($rounds)->map(function($round) use(&$levels) {
            $levels[] = Arr::pull($round,'levels');
            return [
                ...$round,
                'created_by_userid' => auth()->user()->id
            ];
        })->toArray();

        $competition->rounds()->createMany($rounds)->pluck('id')
            ->each(function($round_id, $index) use($levels){
                collect($levels[$index])->map(function($level) use($round_id){
                    $level = [
                        ...$level,
                        'round_id'          => $round_id,
                        'grades'            => $level['grades'],
                        'created_by_userid' => auth()->user()->id
                    ];
                    $competition_level = CompetitionLevels::create($level);
                    if(isset($level['collection_id']) && $level['collection_id'] !== null) {
                        $this->addTaskMark($level['collection_id'], $competition_level);
                        $this->addDifficultyGroup($level['collection_id'], $competition_level);
                    }
                });
        });
    }

    public function editRounds (Request $request) {

        $round_id = $request->validate([
            "id" => "integer|exists:competition_rounds,id",
        ])['id'];


        $round = CompetitionRounds::find($round_id);
        $competition_id = $round->competition->id;

        if($round->competition->status !== 'active') {
            return response()->json([
                "status" => 500,
                "message" => "The selected competition is closed, no edit is allowed."
            ]);
        }

        $roundLevels = $round->levels->pluck('id')->toArray();

        $competition_date = $round->competition->CompetitionOrganization->first()->competition_date_earliest === null ? null : $round->competition->CompetitionOrganization->first()->competition_date_earliest->competition_date;

        $todayDate = date('Y-m-d', strtotime('now'));

        if($competition_date == null || $todayDate < $competition_date) {
            $request['allow_all_changes'] = 1;
        }
        else
        {
            $request['allow_all_changes'] = 0;
        }

        $request['allow_all_changes'] = 1;

        $validated = $request->validate([
            "allow_all_changes" => "boolean|required",
            "name" => "required|distinct|regex:/^[\.\,\s\(\)\[\]\w-]*$/",
            "round_type" => ["exclude_if:allow_all_changes,0","required","integer",Rule::in([0, 1])],
            "team_setting" => ["exclude_if:allow_all_changes,0","exclude_if:rounds.*.round_type,0","required_if:rounds.*.round_type,1","integer",Rule::in([0, 1])],
            "individual_points" => ["exclude_if:allow_all_changes,0","exclude_if:rounds.*.round_type,0","required_if:rounds.*.round_type,1","integer",Rule::in([0, 1])],
            "award_type" => "required|integer|boolean",
            "assign_award_points" => "required|integer|boolean",
            "default_award_name" => "required|string",
            "default_award_points" => "integer",
            "delete" => "array",
            "delete.*" => ["required_if:allow_all_changes,0","integer","distinct",Rule::in($roundLevels)],
            "levels" => "array|required",
            "levels.*.id" => ["required_if:allow_all_changes,0","integer","distinct",Rule::in($roundLevels)],
            "levels.*.name" => "required|regex:/^[\.\,\s\(\)\[\]\w-]*$/",
            "levels.*.collection_id" => ["exclude_if:allow_all_changes,0","required_if:levels.*.id,null","integer","distinct",Rule::exists('collection','id')->where('status','active'), new CheckExistinglevelCollection],
            "levels.*.grades" => "exclude_if:allow_all_changes,0|array|required",
            "levels.*.grades.*" => ["required","integer",new CheckCompetitionAvailGrades, new CheckLevelUsedGrades]
        ]);

        try {
            DB::beginTransaction();

            $levels = $validated['levels'];

            $round->name = $validated['name'];
            $round->round_type = $validated['round_type'];
            $round->team_setting = $validated['team_setting'];
            $round->award_type = $validated['award_type'];
            $round->assign_award_points = $validated['assign_award_points'];
            $round->default_award_name = $validated['default_award_name'];
            $round->default_award_points = $validated['default_award_points'];
            $round->individual_points = $validated['individual_points'];
            $round->save();

            if(isset($validated['delete']) && count($validated['delete']) > 0) {
                CompetitionLevels::whereIn('id',$validated['delete'])->delete();
            }

            foreach($levels as $row) {
//                if($c==1){dd(isset($row['id'] ));}
                if(isset($row['id'] )) {

                    $level = CompetitionLevels::findOrFail($row['id']);
                    $level->name = $row['name'];
                    $level->grades = $row['grades'];

                    if($level->collection_id != $row['collection_id']) {

                        if($level->collection_id != null) {
                            CompetitionTasksMark::where('level_id',$level->id)->delete();
                        }

                        $level->collection_id = $row['collection_id'];
                        $this->addTaskMark($row['collection_id'],$level);
                        $this->addDifficultyGroup($row['collection_id'],$level);
                    }

                    $level->save($row);
                } else {

                    $level = CompetitionLevels::create([
                        'round_id' => $round_id,
                        'name' => $row['name'],
                        'collection_id' => $row['collection_id'],
                        'grades' => $row['grades'],
                        'created_by_userid' => auth()->user()->id
                    ]);

                    $this->addTaskMark($row['collection_id'], $level);
                    $this->addDifficultyGroup($row['collection_id'], $level);
                }

            }

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "Update rounds successful"
            ]);
        } catch(\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Update rounds unsuccessful" .$e
            ]);
        }

    }

    public function deleteRounds (Request $request) {

        try{
            $round_id = implode("",$request->validate(["id" => ["required", "integer", Rule::exists("competition_rounds", "id")]]));
            CompetitionRounds::find($round_id)->delete();
        }
        catch(\Exception $e) {
            return response()->json([
                "status" => 200,
                "message" => "delete round unsuccessful"
            ]);
        }

        return response()->json([
            "status" => 200,
            "message" => "delete round successful"
        ]);
    }

    public function getRoundAwards(CompetitionRounds $round)
    {
        try {
            return response()->json([
                "status"    => 200,
                "message"   => "Round awards retreival successful",
                "data"      => $round->roundsAwards->pluck('name')->concat([$round->default_award_name])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Round awards retreival unsuccessful"
            ], 500);
        }

    }

    public function addRoundAwards (Request $request) {

        $rounds_id = implode("",Arr::flatten($request->validate([
            "round_id" => "required|integer|exists:competition_rounds,id",
        ])));

        $round = CompetitionRounds::find($rounds_id);
        $request['assign_award_points'] = $round->assign_award_points;
        $request['award_type'] = $round->award_type;

        $validated = $request->validate([
            "assign_award_points" => "required",
            "award_type" => "required",
            "award" => "required|array",
            "award.*.name" => "required|string|min:3|max:255",
            "award.*.min_marks" => "integer|min:1",
            "award.*.percentage" => "required_if:award_type,0|exclude_if:award_type,1|integer|min:1|max:100",
            "award.*.award_points" => "required_if:assign_award_points,1|exclude_if:assign_award_points,0|integer|min:1",
        ]);

        try {
            $insert = collect($validated['award'])->map(function ($row) {

                if(isset($row['min_points'])) { // to remove after hassan change the attribute name from min_points to min_marks
                    $row['min_marks'] = $row['min_points'];
                    unset($row['min_points']);
                }

                $row['created_by_userid'] = auth()->user()->id;
                return $row;
            })->toArray();

            $results = $round->roundsAwards()->createMany($insert);

            return response()->json([
                "status" => 200,
                "message" => "add awards successful",
                "data" => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "add awards unsuccessful"
            ]);
        }

    }

    public function editRoundAwards (Request $request) {

        $round_id = implode("",Arr::flatten($request->validate([
            "round_id" => "required|integer|exists:competition_rounds,id",
        ])));

        $competitionRounds = CompetitionRounds::find($round_id);
        $request['assign_award_points'] = $competitionRounds->assign_award_points;
        $request['award_type'] = $competitionRounds->award_type;

        $validated = $request->validate([
            "assign_award_points" => "required",
            "award_type" => "required",
            "award" => "required|array",
            "award.*.id" => "required|integer|exists:competition_rounds_awards,id",
            "award.*.name" => "required|string|min:3|max:255",
            "award.*.min_marks" => "integer|min:1",
            "award.*.percentage" => "required_if:award_type,0|exclude_if:award_type,1|integer|min:1|max:100",
            "award.*.award_points" => "required_if:assign_award_points,1|exclude_if:assign_award_points,0|integer|nullable",
        ]);

        try {

            DB::beginTransaction();
            $results = collect($validated['award'])->map(function ($row) {
                $row['last_modified_userid'] = auth()->user()->id;

                $roundsAwards = CompetitionRoundsAwards::find($row['id']);
                $roundsAwards->name = $row['name'];
//                $roundsAwards->min_marks = isset($row['min_marks']) ? $row['min_marks'] : null;
                $roundsAwards->min_marks = isset($row['min_points']) ? $row['min_points'] : null; // to remove after hassan change the attribute name from min_points to min_marks
                $roundsAwards->percentage = isset($row['percentage']) ? $row['percentage'] : null;
                $roundsAwards->award_points = isset($row['award_points']) ? $row['award_points'] : null;
                $roundsAwards->save();

                return $roundsAwards;
            });

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "edit awards successful",
                "data" => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "edit awards unsuccessful"
            ]);
        }

    }

    public function deleteRoundAwards (Request $request) {

        $id = implode("",Arr::flatten($request->validate([
            "id" => "required|integer|exists:competition_rounds_awards,id",
        ])));

        try {

            CompetitionRoundsAwards::find($id)->delete();

            return response()->json([
                "status" => 200,
                "message" => "delete round awards successful",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "delete round awards unsuccessful"
            ]);
        }
    }

    public function addOverallAwards (Request $request) {

        $competition_id = implode("",Arr::flatten($request->validate([
            "competition_id" => ["required","integer",Rule::exists("competition","id")->where("status","active")],
        ])));

        $competition = Competition::find($competition_id);
        $roundsCount = $competition->rounds->count();

        if($competition->rounds->count() <= 1 ) {
            return response()->json([
                "status" => 500,
                "message" => "need a minimun of 2 rounds to configure overall awards"
            ]);
        }

        $validated = $request->validate([
            "award_type" => "required|integer|min:0|max:1",
            "min_marks" => "required|integer|min:0",
            "default_award_name" => "required|string|max:65535",
            "award" => "required|array",
            "award.*.name" => "required|string|min:3|max:255",
            "award.*.percentage" => "required_if:award_type,0,exclude_if:award_type,1|integer|min:1|max:100",
            "award.*.round_criteria" => "required|array|size:" . $roundsCount,
            "award.*.round_criteria.*" => ["required","integer",new CheckRoundAwards($competition_id)],
        ]);

        try {
            DB::beginTransaction();

            $award = Arr::pull($validated,'award');

            $results = collect($award)->map(function ($row) use($competition_id) {
                $row['competition_id'] = $competition_id;
                $row['created_by_userid'] = auth()->user()->id;

                $awards = Arr::pull($row,'round_criteria');
                $competition_overall_awards_groups  = CompetitionOverallAwardsGroups::create($row);
                $competition_overall_awards_groups_id = $competition_overall_awards_groups->id;

                $competition_overall_awards_groups['awards'] = collect($awards)->map(function ($award) use($competition_overall_awards_groups_id,&$roundAwards) {

                    $temp = [
                        'competition_overall_awards_groups_id' => $competition_overall_awards_groups_id,
                        'competition_rounds_awards_id' => $award
                    ];

                    return CompetitionOverallAwards::create($temp);
                })->toArray();

                return $competition_overall_awards_groups;

            })->toArray();

            $competition->award_type = $validated['award_type'];
            $competition->min_marks = $validated['min_marks'];
            $competition->default_award_name = $validated['default_award_name'];
            $competition->save();

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "add overall awards successful",
                "data" => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "add overall awards unsuccessful"
            ]);
        }
    }

    public function editOverallAwards (Request $request) {

        $id = implode("",Arr::flatten($request->validate([
            "id" => ["required","integer",Rule::exists("competition_overall_awards_groups","id")],
        ])));

        $competition = CompetitionOverallAwardsGroups::find($id)->competition;
        $competition_id = CompetitionOverallAwardsGroups::find($id)->competition->id;
        $competition_status = CompetitionOverallAwardsGroups::find($id)->competition->status;

        if($competition_status !== 'active') {
            return response()->json([
                "status" => 500,
                "message" => "The selected competition is closed for edit."
            ]);
        }

        $roundsCount = $competition->rounds->count();

        $validated = $request->validate([
            "award_type" => "required|integer|min:0|max:1",
            "min_marks" => "required|integer|min:0",
            "default_award_name" => "required|string|max:65535",
            "award" => "required|array",
            "award.*.name" => "required|string|min:3|max:255",
            "award.*.percentage" => "required_if:award_type,0,exclude_if:award_type,1|integer|min:1|max:100",
            "award.*.round_criteria" => "required|array|size:" . $roundsCount,
            "award.*.round_criteria.*" => ["required","integer",new CheckRoundAwards($competition_id)],
        ]);

        try {
            DB::beginTransaction();

            $award = Arr::pull($validated,'award');

            $results = collect($award)->map(function ($row) use($competition_id,$id) {

                $overallAwardGroup = CompetitionOverallAwardsGroups::find($id);
                $overallAwardGroup->name = $row['name'];
                $overallAwardGroup->percentage = $row['percentage'];
                $overallAwardGroup->last_modified_userid = auth()->user()->id;
                $overallAwardGroup->save();

                $awards = Arr::pull($row,'round_criteria');

                $allOverallAwards = CompetitionOverallAwards::where('competition_overall_awards_groups_id',$overallAwardGroup->id)->get()->toArray();

                collect($awards)->each(function ($award,$index) use($allOverallAwards) {

                    $update = CompetitionOverallAwards::find($allOverallAwards[$index]['id']);
                    $update->competition_rounds_awards_id = $award;
                    $update->save();

                })->toArray();
            })->toArray();

            $competition->award_type = $validated['award_type'];
            $competition->min_marks = $validated['min_marks'];
            $competition->default_award_name = $validated['default_award_name'];
            $competition->save();

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "update overall awards successful",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "update overall awards unsuccessful"
            ]);
        }
    }

    public function deleteOverallAwardsGroups (Request $request) {

        $id = implode("",Arr::flatten($request->validate([
            "id" => "required|integer|exists:competition_overall_awards_groups,id",
        ])));

        try {

            CompetitionOverallAwardsGroups::find($id)->delete();

            return response()->json([
                "status" => 200,
                "message" => "delete overall awards group successful",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "delete overall awards group unsuccessful"
            ]);
        }
    }

    public function addOrganizations(AddOrganizationRequest $request)
    {
        try {
            CompetitionService::addOrganizations($request->organizations, $request->competition_id);
            return [
                "status"    => 200,
                "message"   => "add new organization is successfull",
            ];

        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "add new organization is not successful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function updateOrganizationDate (Request $request) {

        $organizationDate = CompetitionOrganization::with(['competition','competition_date_earliest','all_competition_dates' => function ($query) {
            $query->whereDate('competition_date', '<=', date('Y-m-d', strtotime("now")))->orderBy('competition_date');
        }])->where('id',$request->id);

        switch (auth()->user()->role_id) {
            case 0:
            case 1:
                $organization_id = $request['organization_id'];
                $organizationDate = $organizationDate->where('organization_id', $organization_id)->firstOrFail();
                $vaildate[] = ['status' => Rule::in(['active', 'ready', 'lock'])];
                $vaildate[] = ['competition_mode' => ['integer','nullable',Rule::in([0,1,2])]];
                break;
            case 2:
            case 4:

                $organization_id = auth()->user()->organization_id;

                $request['organization_id'] = $organization_id;

                $vaildate[] = ['competition_mode' => ["exclude_if:AllowEditCompetitionMode,0",'required_if:AllowEditCompetitionMode,1','integer','nullable',Rule::in([0,1,2])]];

                $organizationDate = $organizationDate->where('organization_id', $organization_id)->firstOrFail();

                if($organizationDate->status != 'lock') {
                    $vaildate[] = ['status' => Rule::in(['active', 'ready'])];
                }
                break;
        }

        if(isset($organizationDate->competition_date_earliest) && auth()->user()->role_id != 0 && auth()->user()->role_id != 1){
            $request['AllowEditCompetitionMode'] = date('Y-m-d', strtotime("now")) <  date('Y-m-d', strtotime($organizationDate->competition_date_earliest->competition_date)) ? 1 : 0;
        }
        else
        {
            $request['AllowEditCompetitionMode'] = 1;
        }

        $request['competition_format'] = $organizationDate["competition"]->format;
        $request['global_registration_date'] = $organizationDate["competition"]->global_registration_date;
        $request['global_registration_end_date'] = $organizationDate["competition"]->global_registration_end_date;
        $request['competition_start_date'] = $organizationDate["competition"]->competition_start_date;
        $request['competition_end_date'] = $organizationDate["competition"]->competition_end_date;

        $vaildate[] = ([
            "AllowEditCompetitionMode" => 'required',
            "organization_id" => ["required","nullable","integer",Rule::exists('organization',"id")->where(function ($query) {
                $query->whereIn('status',['active','added']);
            })],
            "competition_format" => 'integer|min:0|max:1',
            "competition_mode" => ['required',new CheckOrgAvailCompetitionMode],
            "edit_sessions.*" => 'boolean',
            "competition_dates.*" => 'required|date|distinct|after_or_equal:competition_start_date|after_or_equal:today|before_or_equal:competition_end_date',
            "registration_open_date" => ["required","date",new CheckLocalRegistrationDateAvail],
            "translate" => "json"
        ]);

        $validated = $request->validate(Arr::collapse($vaildate));

        try {
            if(isset($validated['competition_dates']) && count($validated['competition_dates']) > 0 ) {

                $InsertCompetitionOrganizationDates =[];

                for($k=0;$k<count($validated['competition_dates']);$k++) {
                    array_push($InsertCompetitionOrganizationDates, [
                        'competition_organization_id' => $request->id,
                        'competition_date' => $validated['competition_dates'][$k],
                        'created_by_userid' => auth()->user()->id,
                        'created_at' => Carbon::today()->format('Y-m-d h:i:s'),
                    ]);
                }


                if(count($organizationDate['all_competition_dates']->toArray()) > 0){
                    $temp = [];
                    $organizationDate['all_competition_dates']->pluck('id');
                    $pastDates = $organizationDate['all_competition_dates']->map(function ($item) {
                        unset($item['id'],$item['updated_at']);
                        return $item;
                    })->toArray();


                    $InsertCompetitionOrganizationDates = collect(array_merge($pastDates,$InsertCompetitionOrganizationDates))->map(function ($item) use(&$temp) {

                        $date =  str_replace('-', '/', $item['competition_date']);
                        if(!in_array($date,$temp))
                        {
                            $temp[] = $date;

                            return [
                                'competition_organization_id' => $item['competition_organization_id'],
                                'created_by_userid' => $item['created_by_userid'],
                                'competition_date' => $date,
                                'created_at' => $item['created_at']
                            ];
                        } else {
                            return [];
                        }
                    })->filter()->toArray();
                }
            }

            DB::beginTransaction();

            $organizationDate->registration_open_date = $validated['registration_open_date'];
            $organizationDate->last_modified_userid = auth()->user()->id;

            if($validated['AllowEditCompetitionMode']) {
                $organizationDate->competition_mode = $validated['competition_mode'];
            }
            $organizationDate->translate = $validated['translate'];

            if($organizationDate->status !== 'lock' || auth()->user()->role_id == 0 || auth()->user()->role_id == 1) {
                $organizationDate->status = $validated['status'];
            }

            if((auth()->user()->role_id == 0 || auth()->user()->role_id == 1) && isset($validated['edit_sessions'])) {
                $organizationDate->edit_sessions = $validated['edit_sessions'];
            }

            $organizationDate->save();

            if($validated['competition_format'] == 0) {
                CompetitionOrganizationDate::where('competition_organization_id', $request->id)->delete();
                CompetitionOrganizationDate::insert($InsertCompetitionOrganizationDates);
            }

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "partner registration date update successful"
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                "status" => 404,
                "message" => "partner not found in competition"
            ]);
        }
    }

    public function deleteOrganization (Request $request) {
        //set in role permission country partner cannot delete
        try{
            $partnerDate = CompetitionOrganization::findorfail($request->id);
            $deletePartnerDate = $partnerDate->delete();

            if($deletePartnerDate) {
                return response()->json([
                    "status" => 200,
                    "message" => "remove partner from competition successful"
                ]);
            }
        }
        catch(\Exception $e) {
            return response()->json([
                "status"    => 404,
                "message"   => "Invalid record",
                "error"     => $e
            ], 404);
        }
    }

    public function upload_answers (Request $request) {

        $level_id = implode("",$request->validate(['level_id' => 'required|integer|exists:competition_levels,id']));
        $level = CompetitionLevels::find($level_id);
        $levelGrades = $level->grades;

        $competitionId = $level->rounds->competition->id;
        $request['competition_id'] = $competitionId;
        $competition_id = implode("",$request->validate(['competition_id' => ["required","integer",Rule::exists("competition","id")->where("status","active")],]));
        $competitionLevelIndexNo = Competition::find($competition_id)->participants->whereIn('grade',$levelGrades)->pluck('index_no')->toArray();

        $tasks = Arr::flatten($level->collection->sections->pluck('tasks')->toArray());
        $tasksCount = count($tasks);

        $validate = $request->validate(
            [
                'participants.*' => ['required','array'],
                'participants.*.index_number' => ['required','string',"size:12",Rule::in($competitionLevelIndexNo)],
                'participants.*.answers' => ['required','array','size:' . $tasksCount]
            ],
            [
                'participants.*.answers.size' => 'Incorrect answers length, check for missing answer.'
            ]
        );


        $insert = collect($validate['participants'])->map(function ($participant)  use($level_id,$tasks){
            $participant_index = $participant['index_number'];

            return collect($participant['answers'])->map(function ($answers,$index)  use($level_id,$tasks,$participant_index) {

                return [
                    'level_id' => $level_id,
                    'task_id' => $tasks[$index],
                    'participant_index' => $participant_index,
                    'answer' => $answers,
                    'created_by_userid' => auth()->user()->id,
                    'created_at' => date("Y-m-d",strtotime(('now')))
                ];
            });
        })->collapse()->toArray();

        $submittedParticipantIndex = Arr::pluck($validate['participants'],'index_number');

        try {
            DB::beginTransaction();
            ParticipantsAnswer::whereIn('participant_index',$submittedParticipantIndex)->delete();
            foreach (array_chunk($insert,100) as $t) {
              ParticipantsAnswer::insert($t);
            }

            DB::commit();

            return response()->json([
                "status" =>  201,
                "message" => 'students answers uploaded successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" =>  500,
                "message" => 'students answers uploaded unsuccessful' . $e->getMessage()
            ]);
        }
    }

    public function report(Competition $competition, Request $request)
    {
        try {
            $header = [
                'participant','index','certificate number','competition','organization','country',
                'level','grade','school','tuition','points','award','school_rank','country_rank','global rank'
            ];
            $competitionService = new CompetitionService($competition);
            $data = $competitionService->applyFilterToReport(
                $competitionService->getReportQuery($request->mode ?? 'all'),
                $request
            )->get()->toArray();

            if(count($data) === 0) return [];

            $competitionService->setReportSchoolRanking($data, $participants, $currentLevel, $currentSchool, $currentPoints, $counter);
            $competitionService->setReportCountryRanking($participants, $currentLevel, $currentCountry, $currentPoints, $counter);
            $competitionService->setReportAwards($data, $noAwards, $awards, $output, $header, $participants, $currentLevel, $currentAward, $currentPoints, $globalRank, $counter);
        
            DB::beginTransaction();
            foreach ($participants as $participant) {
                $participantResult = CompetitionParticipantsResults::where('participant_index',$participant['index_no'])->first();
                $participantResult->school_rank = $participant['school_rank'];
                $participantResult->country_rank = $participant['country_rank'];
                $participantResult->global_rank = $participant['global_rank'] ?: null;
                $participantResult->save();
            }
            DB::commit();

            if($request->mode === 'csv') return Arr::prepend($output, $header);

            $filterOptions = $competitionService->getReportFilterOptions($output);
            $data = CollectionHelper::searchCollection(
                $request->search,
                collect($output),
                array("competition", "organization", "country", "level", "school", "name", "index_no", "certificate_no", "award", "global_rank"),
                $request->limits ?? 10
            );
            return [
                'filterOptions'     => $filterOptions,
                'header'            => $header,
                'data'              => $data,
            ];

        } catch (\Exception $e) {
            return response()->json([
                'status'    => 500,
                'message'   => "Failed to fetch report",
                'error'     => $e->getMessage()
            ], 500);
        }
    }

    public function old_report (Competition $competition) {

        $competition_id = $competition->id;
  /**        $competition_id = $request->validate([
              'competition_id' => ['required','integer',Rule::exists('competition','id')->where('status','active')]
          ])['competition_id'];
  
          $query = DB::select( DB::raw("SELECT competition.name as competition, organization.name as organization,all_countries.display_name as country, competition_levels.name as level, competition_levels.id as level_id, participants.grade, schools.name as school, participants.index_no,participants.name, competition_participants_results.points, competition_rounds_awards.name as award FROM `competition_participants_results`
                 ON competition_rounds.id = competition_levels.round_id
                   LEFT JOIN competition
                   ON competition.id = competition_rounds.competition_id
                   LEFT JOIN participants
                   ON participants.index_no = competition_participants_results.participant_index
                   LEFT JOIN competition_organization
                   ON competition_organization.id = participants.competition_organization_id
                   LEFT JOIN organization
                   ON organization.id = competition_organization.organization_id
                   LEFT JOIN schools
                   ON participants.school_id = schools.id
                   LEFT JOIN all_countries
                   ON all_countries.id = schools.country_id
                   LEFT JOIN competition_rounds_awards
                   ON competition_rounds_awards.id = competition_participants_results.award_id
                   WHERE competition.id = " . $competition_id . "
                   ORDER BY `competition_levels`.`id`, `competition_participants_results`.`award_id`, `competition_participants_results`.`points` DESC"));
  **/
          $query = DB::select( DB::raw("
             SELECT CONCAT('\"',competition.name,'\"') as competition, CONCAT('\"',organization.name,'\"') as organization,CONCAT('\"',all_countries.display_name,'\"') as country, CONCAT('\"',competition_levels.name,'\"') as level, competition_levels.id as level_id, participants.grade, CONCAT('\"',schools.name,'\"') as school, CONCAT('\"',tuition_school.name,'\"') as tuition_centre, participants.index_no, CONCAT('\"',participants.name,'\"') as name, participants.certificate_no, competition_participants_results.points, CONCAT('\"',competition_participants_results.award,'\"') as award FROM `competition_participants_results`
                   LEFT JOIN competition_levels
                   ON competition_levels.id = competition_participants_results.level_id
                   LEFT JOIN competition_rounds
                   ON competition_rounds.id = competition_levels.round_id
                   LEFT JOIN competition
                   ON competition.id = competition_rounds.competition_id
                   LEFT JOIN participants
                   ON participants.index_no = competition_participants_results.participant_index
                   LEFT JOIN competition_organization
                   ON competition_organization.id = participants.competition_organization_id
                   LEFT JOIN organization
                   ON organization.id = competition_organization.organization_id
                   LEFT JOIN schools
                   ON participants.school_id = schools.id
                   LEFT JOIN schools as tuition_school
                   ON participants.tuition_centre_id = tuition_school.id
                   LEFT JOIN all_countries
                   ON all_countries.id = schools.country_id
                   WHERE competition.id = ".$competition_id."
                   ORDER BY `competition_levels`.`id`, FIELD(`competition_participants_results`.`award`,'PERFECT SCORER','GOLD','SILVER','BRONZE','HONORABLE MENTION','Participation'), `competition_participants_results`.`points` desc;
          "));
  
          $filename = 'report.csv';
          $output = [];
          $fp = fopen(public_path().'/'.$filename, 'w');
          $header = ['competition','organization','country','level','grade','school','tuition','index','participant','certificate number','points','award','school_rank','country_rank','global rank'];
          $array = json_decode(json_encode($query), true);
          $participants = [];
  
          collect($array)->sortBy([ // school ranking
              ['level_id','asc'],
              ['school','asc'],
              ['points','desc']
          ])->each(function ($row,$index) use(&$participants,&$currentLevel,&$currentSchool,&$currentPoints,&$counter){
              if($index == 0) {
                  $currentLevel = $row['level_id'];
                  $currentSchool = $row['school'];
                  $currentPoints = $row['points'];
                  $counter = 1;
              }
  
              if($currentPoints !== $row['points']) {
                  $counter++;
                  $currentPoints = $row['points'];
              }
  
              if($currentLevel !== $row['level_id'] || $currentSchool !== $row['school']){
                  $currentLevel = $row['level_id'];
                  $currentSchool = $row['school'];
                  $counter = 1;
              }
  
              $participants[$row['index_no']] = [
                  ...$row,
                  'school_rank' => $counter
              ];
          });
  
          collect($participants)->sortBy([ // country ranking
              ['level_id','asc'],
              ['country','asc'],
              ['points','desc']
          ])->each(function ($row,$index) use(&$participants,&$currentLevel,&$currentCountry,&$currentPoints,&$counter){
              if($index == 0) {
                  $currentLevel = $row['level_id'];
                  $currentCountry = $row['country'];
                  $currentPoints = $row['points'];
                  $counter = 1;
              }
  
              if($currentPoints !== $row['points']) {
                  $counter++;
                  $currentPoints = $row['points'];
              }
  
              if($currentLevel !== $row['level_id'] || $currentCountry !== $row['country']){
                  $currentLevel = $row['level_id'];
                  $currentCountry = $row['country'];
                  $counter = 1;
              }
  
              $participants[$row['index_no']] = [
                  ...$row,
                  'country_rank' => $counter
              ];
  
          });
  
          collect($array)->each(function ($row) use(&$noAwards,&$awards) { // seperate participant with/without award
              if($row['award'] !== 'NULL') {
                  $awards[] = $row;
              } else {
                  $noAwards[] = $row;
              }
          });
  
          collect($awards)->each(function ($fields,$index) use($fp,&$output,$header,$array,&$participants,&$currentLevel,&$noAwards,&$currentAward,&$currentPoints,&$globalRank,&$counter) {
  
              if($index == 0) {
                  $output[] = $header;
                  fputcsv($fp, $header);
                  $globalRank = 1;
                  $counter = 1;
                  $currentAward = $fields['award'];
                  $currentPoints = $fields['points'];
                  $currentLevel = $fields['level_id'];
              }
  
              if($currentLevel != $fields['level_id']){
                  $globalRank = 1;
                  $counter = 1;
              }
  
              if($currentAward === $fields['award'] && $currentPoints !== $fields['points']) {
                  $globalRank = $counter;
                  $currentPoints = $fields['points'];
              } elseif ($currentAward !== $fields['award'] ) {
                  $currentAward = $fields['award'];
                  $currentPoints = $fields['points'];
                  $globalRank = 1;
                  $counter = 1;
              }
  
              $currentLevel = $fields['level_id'];
              $participants[$fields['index_no']]['global_rank'] = $fields['award'] .' '.$globalRank;
              unset($participants[$fields['index_no']]['level_id']);
              $output[] = $participants[$fields['index_no']];
              fputcsv($fp, $participants[$fields['index_no']]);
              $counter++;
          });
  
          if(isset($noAwards)) {
             foreach ($noAwards as $row) {
                unset($participants[$row['index_no']]['level_id']);
                $participants[$row['index_no']]['global_rank'] = '';
                $output[] = $participants[$row['index_no']];
                fputcsv($fp, $participants[$row['index_no']]);
            }
          }
  
          fclose($fp);
  
          DB::beginTransaction();
          foreach ($participants as $row) {
              $participantResult = CompetitionParticipantsResults::where('participant_index',$row['index_no'])->first();
              $participantResult->school_rank = $row['school_rank'];
              $participantResult->country_rank = $row['country_rank'];
              $participantResult->global_rank = $row['global_rank'] ?: null ;
              $participantResult->save();
          }
  
          DB::commit();
  
          /**if (file_exists(public_path().'/'.$filename)) {
              header('Content-Description: File Transfer');
              header('Content-Type: application/octet-stream');
              header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
              header('Expires: 0');
              header('Cache-Control: must-revalidate');
              header('Pragma: public');
              header('Content-Length: ' . filesize($filename));
              readfile($filename);
              exit;
          }**/
  
        return $output;
      }

    private function addDifficultyGroup ($collection_id, $competition_level) {
        $insert = Arr::collapse(
            CollectionSections::where('collection_id', $collection_id)
            ->get()->pluck('tasks')->flatten()
            ->map(function ($item) {
                $mapped = Arr::map(Arr::flatten($item->toArray()), function ($value) {
                    return ['task_id' => $value];
                });
                return $mapped;
            })
            ->toArray()
        );
        return $competition_level->taskDifficultyGroup()->createMany($insert);
    }

    private function addTaskMark ($collection_id,$competition_level)
    {
        $taskIds = Arr::flatten(CollectionSections::where('collection_id',$collection_id)
                ->pluck('tasks')->toArray());

        $insert = Tasks::with(['taskAnswers' => function($query) {
            return $query->whereNotNull('answer');
        }])->whereIn('id', $taskIds)->orderBy('id')->get()
        ->map(function ($items) {
            if(in_array($items->answer_structure, ['group','sequence'])) {
                return [["task_answers_id" => $items->taskAnswers->sortBy('position')->pluck('id')->toJson()]];
            }else{
                $items->taskAnswers->sortBy('position')->each(function ($row) use (&$temp){
                    $temp[] = ["task_answers_id" => $row->id];
                });
                return $temp;
            }
        })->filter()->collapse()->toArray();

        return $competition_level->taskMarks()->createMany($insert);
    }

    public function competitionCountries(Competition $competition, Request $request)
    {
        $countries = $competition->participants()
            ->join('all_countries as ac', 'ac.id', 'participants.country_id');

        switch ($request->mode) {
            case "not_grouped":
                $competitionGroupCountriesIdsList = $competition->groups()
                    ->join('competition_marking_group_country as cmgc', 'competition_marking_group.id', 'cmgc.marking_group_id')
                    ->join('all_countries as ac', 'ac.id', 'cmgc.country_id')
                    ->pluck('ac.id');

                $countries->whereNotIn('ac.id', $competitionGroupCountriesIdsList);
                return response()->json([
                    "status"    => 200,
                    "countries" => $countries->pluck('ac.display_name', 'ac.id')
                ], 200);

            break;
            default:
                return response()->json([
                    "status"    => 200,
                    "countries" => $countries->pluck('ac.display_name', 'ac.id')
                ], 200);
            break;
        }
    }
}
