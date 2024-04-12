<?php

namespace App\Http\Controllers\api;

use App\Helpers\AnswerUploadHelper;
use App\Http\Controllers\Controller;
use App\Models\CollectionSections;
use App\Models\CompetitionLevels;
use App\Models\CompetitionOrganization;
use App\Models\CompetitionOverallAwards;
use App\Models\CompetitionOverallAwardsGroups;
use App\Models\CompetitionRounds;
use App\Models\CompetitionRoundsAwards;
use App\Models\CompetitionTaskDifficulty;
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
use App\Http\Requests\CompetitionListRequest;
use App\Http\Requests\CreateCompetitionRequest;
use App\Http\Requests\DeleteCompetitionRequest;
use App\Http\Requests\UpdateCompetitionRequest;
use App\Http\Requests\UploadAnswersRequest;
use App\Models\Participants;
use App\Rules\AddOrganizationDistinctIDRule;
use App\Rules\CheckLocalRegistrationDateAvail;
use App\Rules\CheckOrganizationCountryPartnerExist;
use App\Services\CompetitionService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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
        try {
            $request->merge([
                'created_by_userid' => auth()->user()->id,
                'allowed_grades'    => collect($request->allowed_grades)->unique()->values()->toArray()
            ]);
            $competition = Competition::create($request->all());
            $this->addOrganization($request->organizations, $competition->id);
            $this->addRounds($request->rounds, $competition);
            $this->addTags($competition, $request->tags ?? []);

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

    public function update(Competition $competition, UpdateCompetitionRequest $request)
    {
        try {
            $earliestOrganizationCompetitionDate = CompetitionOrganization::where('competition_id', $competition->id)
                ->join('competition_organization_date', 'competition_organization_date.competition_organization_id', 'competition_organization.id')
                ->select('competition_organization_date.competition_date')
                ->orderBy('competition_date')
                ->first();

            !$request->has('alias')  ?: $competition->alias = $request->alias;
            !$request->has('name')   ?: $competition->name = $request->name;

            $competition->global_registration_date = $request->global_registration_date;
            if ($request->has('global_registration_end_date')) {
                $competition->global_registration_end_date = $request->global_registration_end_date;
            }
            $competition->competition_start_date = $request->competition_start_date;
            $competition->competition_end_date = $request->competition_end_date;
            $competition->competition_mode = $request->competition_mode;
            $competition->difficulty_group_id = $request->difficulty_group_id;

            switch ($request->competition_mode) {
                    //update organization competition mode based on the competition's level competition mode
                case 0:
                    CompetitionOrganization::where('competition_id', $competition->id)
                        ->whereIn('competition_mode', [1, 2])
                        ->update([
                            'competition_mode'  => 0,
                            'updated_at'        => now()
                        ]);
                    break;
                case 1:
                    CompetitionOrganization::where('competition_id', $competition->id)
                        ->whereIn('competition_mode', [0, 2])
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
                    if (now()->lte($competition->global_registration_end_date)) {
                        // allow to edit grades if registration is still open
                        $competition->allowed_grades = $request->allowed_grades;
                    }
                    break;
            }

            $this->addTags($competition, $request->tags ?? []);

            $competition->save();
            if ($request->has('competition_end_date')) {
                $newCompeitionEndDate = date('Y-m-d', strtotime($request->competition_end_date));
                foreach ($competition->competitionOrganization as $organization) {
                    // Update competition organization extended date to null case the date before the new global end date
                    if ($organization->extended_end_date <= $newCompeitionEndDate) {
                        $organization->update(['extended_end_date' => null]);
                    }
                    // Discard competition dates that exceed the new end date
                    CompetitionOrganizationDate::where('competition_organization_id', $organization->id)
                        ->whereDate('competition_date', '>', $newCompeitionEndDate)
                        ->delete();
                }
            }
            return response()->json([
                "status"    => 200,
                "message"   => "Competition update successful",
            ]);
        } catch (ModelNotFoundException $e) {
            // do task when error
            return response()->json([
                "status"    => 500,
                "message"   => "Competition create unsuccessful",
            ], 500);
        }
    }

    public function list(CompetitionListRequest $request)
    {
        try {
            if ($request->has('id') && Competition::whereId($request->id)->exists()) {
                return $this->show(Competition::find($request->id));
            }
            $limits = $request->limits ? $request->limits : 10;
            $searchKey = isset($request->search) ? $request->search : null;

            $competitionModel = Competition::AcceptRequest(['id', 'status', 'format', 'name']);

            //            return ($competitionModel)->get();

            switch (auth()->user()->role_id) {
                case 0:
                case 1:
                    $competitionModel->with(['competitionOrganization', 'overallAwardsGroups.overallAwards', 'rounds.roundsAwards', 'rounds.levels' => function ($query) {
                        $query->orderBy('id');
                    }, "tags:id,name"]);
                    break;
                case 2:
                case 4:
                case 3:
                case 5:
                    $competitionModel->with(['competitionOrganization' => function ($query) {
                        $query->where(['organization_id' => auth()->user()->organization_id])
                            ->where('country_id', auth()->user()->country_id);
                    }])->where('status', 'active');
                    break;
            }

            $competitionCollection = $competitionModel
                ->applyFilter($request)
                ->orderBy('updated_at', 'DESC')
                ->get()
                ->filter(fn ($row) => count($row['competitionOrganization']) > 0);

            /**
             * Lists of availabe filters
             */
            $availUserStatus = $competitionCollection->map(function ($item) {
                return $item['status'];
            })->unique()->values();
            $availFormat = $competitionCollection->map(function ($item) {
                return $item['format'];
            })->unique()->values();
            $tags = $competitionCollection->map(function ($item) {
                return $item['tags'];
            })->flatten(1)->unique('id')->values();

            /**
             * EOL Lists of availabe filters
             */

            $availForSearch = array("name", "alias");
            $competitionList = CollectionHelper::searchCollection($searchKey, $competitionCollection, $availForSearch, $limits);
            $data = array("filterOptions" => ['status' => $availUserStatus, 'format' => $availFormat, "tags" => $tags], "competitionList" => $competitionList);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        } catch (QueryException $e) {
            return response()->json([
                "status" => 500,
                "message" => "competition list retrieve unsuccessful"
            ],500);
        } catch (ModelNotFoundException $e) {
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "competition list retrieve unsuccessful"
            ],500);
        }
    }

    public function show(Competition $competition)
    {
        $data = $competition->load(['rounds.levels', 'rounds.roundsAwards', 'competitionOrganization' => function ($query) {
            if (!is_null(auth()->user()->organization_id)) {
                $query->where(['organization_id' => auth()->user()->organization_id])
                    ->where('country_id', auth()->user()->country_id);
            }
        } , 'taskDifficultyGroup', 'taskDifficulty', "tags:id,name"]);

        return response()->json([
            "status"    => 200,
            "message"   => "Competition retrieved successfully",
            "data"      => $data
        ]);
    }

    public function delete(DeleteCompetitionRequest $request)
    {
        try {
            if (Competition::destroy($request->id)) {
                return response()->json([
                    "status"    => 200,
                    "message"   => "delete competition successful"
                ]);
            }
        } catch (\Exception $e) {
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
        $rounds = collect($rounds)->map(function ($round) use (&$levels) {
            $levels[] = Arr::pull($round, 'levels');
            return [
                ...$round,
                'created_by_userid' => auth()->user()->id
            ];
        })->toArray();

        $competition->rounds()->createMany($rounds)->pluck('id')
            ->each(function ($round_id, $index) use ($levels) {
                collect($levels[$index])->map(function ($level) use ($round_id) {
                    $level = [
                        ...$level,
                        'round_id'          => $round_id,
                        'grades'            => $level['grades'],
                        'created_by_userid' => auth()->user()->id
                    ];
                    $competition_level = CompetitionLevels::create($level);
                    if (isset($level['collection_id']) && $level['collection_id'] !== null) {
                        $this->addTaskMark($level['collection_id'], $competition_level);
                        $this->addDifficultyGroup($level['collection_id'], $competition_level);
                    }
                });
            });
    }

    public function editRounds(Request $request)
    {

        $round_id = $request->validate([
            "id" => "integer|exists:competition_rounds,id",
        ])['id'];


        $round = CompetitionRounds::find($round_id);
        $competition_id = $round->competition->id;

        if ($round->competition->status !== 'active') {
            return response()->json([
                "status" => 500,
                "message" => "The selected competition is closed, no edit is allowed."
            ]);
        }

        $roundLevels = $round->levels->pluck('id')->toArray();

        $competition_date = $round->competition->CompetitionOrganization->first()->competition_date_earliest === null ? null : $round->competition->CompetitionOrganization->first()->competition_date_earliest->competition_date;

        $todayDate = date('Y-m-d', strtotime('now'));

        if ($competition_date == null || $todayDate < $competition_date) {
            $request['allow_all_changes'] = 1;
        } else {
            $request['allow_all_changes'] = 0;
        }

        $request['allow_all_changes'] = 1;

        $validated = $request->validate([
            "allow_all_changes" => "boolean|required",
            "name" => "required|distinct|regex:/^[\.\,\s\(\)\[\]\w-]*$/",
            "round_type" => ["exclude_if:allow_all_changes,0", "required", "integer", Rule::in([0, 1])],
            "team_setting" => ["exclude_if:allow_all_changes,0", "exclude_if:rounds.*.round_type,0", "required_if:rounds.*.round_type,1", "integer", Rule::in([0, 1])],
            "individual_points" => ["exclude_if:allow_all_changes,0", "exclude_if:rounds.*.round_type,0", "required_if:rounds.*.round_type,1", "integer", Rule::in([0, 1])],
            "award_type" => "required|integer|boolean",
            "assign_award_points" => "required|integer|boolean",
            "default_award_name" => "required|string",
            "default_award_points" => "integer",
            "delete" => "array",
            "delete.*" => ["required_if:allow_all_changes,0", "integer", "distinct", Rule::in($roundLevels)],
            "levels" => "array|required",
            "levels.*.id" => ["required_if:allow_all_changes,0", "integer", "distinct", Rule::in($roundLevels)],
            "levels.*.name" => "required|regex:/^[\.\,\s\(\)\[\]\w-]*$/",
            "levels.*.collection_id" => [
                "exclude_if:allow_all_changes,0",
                "required_if:levels.*.id,null",
                "integer",
                "distinct",
                Rule::exists('collection', 'id')->where(function ($query) {
                    $query->where('status', 'active')->orWhere('status', 'verified');
                }),
                new CheckExistinglevelCollection
            ],
            "levels.*.grades" => "exclude_if:allow_all_changes,0|array|required",
            "levels.*.grades.*" => ["required", "integer", new CheckCompetitionAvailGrades, new CheckLevelUsedGrades]
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

            if (isset($validated['delete']) && count($validated['delete']) > 0) {
                CompetitionLevels::whereIn('id', $validated['delete'])->delete();
            }

            foreach ($levels as $row) {
                //                if($c==1){dd(isset($row['id'] ));}
                if (isset($row['id'])) {

                    $level = CompetitionLevels::findOrFail($row['id']);
                    $level->name = $row['name'];
                    $level->grades = $row['grades'];

                    if ($level->collection_id != $row['collection_id']) {

                        if ($level->collection_id != null) {
                            CompetitionTaskDifficulty::where('level_id', $level->id)->delete();
                            CompetitionTasksMark::where('level_id', $level->id)->delete();


                        }

                        $level->collection_id = $row['collection_id'];
                        $this->addTaskMark($row['collection_id'], $level);
                        $this->addDifficultyGroup($row['collection_id'], $level);
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
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Update rounds unsuccessful" . $e
            ]);
        }
    }

    public function deleteRounds(Request $request)
    {

        try {
            $round_id = implode("", $request->validate(["id" => ["required", "integer", Rule::exists("competition_rounds", "id")]]));
            CompetitionRounds::find($round_id)->delete();
        } catch (\Exception $e) {
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

    public function addRoundAwards(Request $request)
    {

        $rounds_id = implode("", Arr::flatten($request->validate([
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

                if (isset($row['min_points'])) { // to remove after hassan change the attribute name from min_points to min_marks
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
            ],500);
        }
    }

    public function editRoundAwards(Request $request)
    {

        $round_id = implode("", Arr::flatten($request->validate([
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
            "award.*.min_marks" => "integer|min:0",
            "award.*.percentage" => "required_if:award_type,0|exclude_if:award_type,1|integer|min:1|max:100",
            "award.*.award_points" => "required_if:assign_award_points,1|exclude_if:assign_award_points,0|integer|nullable",
        ]);

        try {

            DB::beginTransaction();
            $results = collect($validated['award'])->map(function ($row) {
                $row['last_modified_userid'] = auth()->user()->id;

                $roundsAwards = CompetitionRoundsAwards::find($row['id']);
                $roundsAwards->name = $row['name'];
                $roundsAwards->min_marks = isset($row['min_marks']) ? $row['min_marks'] : null;
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

    public function deleteRoundAwards(Request $request)
    {

        $id = implode("", Arr::flatten($request->validate([
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

    public function addOverallAwards(Request $request)
    {

        $competition_id = implode("", Arr::flatten($request->validate([
            "competition_id" => ["required", "integer", Rule::exists("competition", "id")->where("status", "active")],
        ])));

        $competition = Competition::find($competition_id);
        $roundsCount = $competition->rounds->count();

        if ($competition->rounds->count() <= 1) {
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
            "award.*.round_criteria.*" => ["required", "integer", new CheckRoundAwards($competition_id)],
        ]);

        try {
            DB::beginTransaction();

            $award = Arr::pull($validated, 'award');

            $results = collect($award)->map(function ($row) use ($competition_id) {
                $row['competition_id'] = $competition_id;
                $row['created_by_userid'] = auth()->user()->id;

                $awards = Arr::pull($row, 'round_criteria');
                $competition_overall_awards_groups  = CompetitionOverallAwardsGroups::create($row);
                $competition_overall_awards_groups_id = $competition_overall_awards_groups->id;

                $competition_overall_awards_groups['awards'] = collect($awards)->map(function ($award) use ($competition_overall_awards_groups_id, &$roundAwards) {

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

    public function editOverallAwards(Request $request)
    {

        $id = implode("", Arr::flatten($request->validate([
            "id" => ["required", "integer", Rule::exists("competition_overall_awards_groups", "id")],
        ])));

        $competition = CompetitionOverallAwardsGroups::find($id)->competition;
        $competition_id = CompetitionOverallAwardsGroups::find($id)->competition->id;
        $competition_status = CompetitionOverallAwardsGroups::find($id)->competition->status;

        if ($competition_status !== 'active') {
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
            "award.*.round_criteria.*" => ["required", "integer", new CheckRoundAwards($competition_id)],
        ]);

        try {
            DB::beginTransaction();

            $award = Arr::pull($validated, 'award');

            $results = collect($award)->map(function ($row) use ($competition_id, $id) {

                $overallAwardGroup = CompetitionOverallAwardsGroups::find($id);
                $overallAwardGroup->name = $row['name'];
                $overallAwardGroup->percentage = $row['percentage'];
                $overallAwardGroup->last_modified_userid = auth()->user()->id;
                $overallAwardGroup->save();

                $awards = Arr::pull($row, 'round_criteria');

                $allOverallAwards = CompetitionOverallAwards::where('competition_overall_awards_groups_id', $overallAwardGroup->id)->get()->toArray();

                collect($awards)->each(function ($award, $index) use ($allOverallAwards) {

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

    public function deleteOverallAwardsGroups(Request $request)
    {

        $id = implode("", Arr::flatten($request->validate([
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

    public function addOrganizationRoute(Request $request)
    {
        try {
            $request->validate([
                "organizations"                         => 'required|array',
                "organizations.*.organization_id"       => ["required", "integer", Rule::exists('organization', "id")->where(fn ($query) => $query->where('status', 'active')), new AddOrganizationDistinctIDRule],
                "organizations.*.country_id"            => ['required', 'integer', new CheckOrganizationCountryPartnerExist],
                "organizations.*.translate"             => "json",
                "organizations.*.edit_sessions.*"       => 'boolean',
            ]);

            $this->addOrganization($request->organizations, $request->competition_id);
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

    private function addOrganization(array $organizations, int $competition_id)
    {
        foreach($organizations as $organization){
            if(CompetitionOrganization::where('competition_id', $competition_id)->where('organization_id', $organization['organization_id'])->where('country_id', $organization['country_id'])->doesntExist()){
                CompetitionOrganization::create(
                    array_merge($organization, [
                        'competition_id'    => $competition_id,
                        'created_by_userid' => auth()->user()->id,
                    ])
                );
            }
        }
    }

    public function addTags(Competition $competition, array|null $tags)
    {
        $competition->tags()->detach();
        foreach($tags as $tagId){
            $competition->tags()->attach($tagId);
        }
    }

    public function updateOrganizationDate (Request $request) {

        $organizationDate = CompetitionOrganization::with(['competition','competition_date_earliest','all_competition_dates' => function ($query) {
            $query->whereDate('competition_date', '<=', date('Y-m-d', strtotime("now")))->orderBy('competition_date');
        }])->where('id', $request->id);
        switch (auth()->user()->role_id) {
            case 0:
            case 1:
                $organization_id = $request['organization_id'];
                $organizationDate = $organizationDate->where('organization_id', $organization_id)->firstOrFail();
                $vaildate[] = ['status' => Rule::in(['active', 'ready', 'lock'])];
                $vaildate[] = ['competition_mode' => ['integer', 'nullable', Rule::in([0, 1, 2])]];
                break;
            case 2:
            case 4:

                $organization_id = auth()->user()->organization_id;

                $request['organization_id'] = $organization_id;

                $vaildate[] = ['competition_mode' => ["exclude_if:AllowEditCompetitionMode,0", 'required_if:AllowEditCompetitionMode,1', 'integer', 'nullable', Rule::in([0, 1, 2])]];

                $organizationDate = $organizationDate->where('organization_id', $organization_id)->firstOrFail();

                if ($organizationDate->status != 'lock') {
                    $vaildate[] = ['status' => Rule::in(['active', 'ready'])];
                }
                break;
        }

        if (isset($organizationDate->competition_date_earliest) && auth()->user()->role_id != 0 && auth()->user()->role_id != 1) {
            $request['AllowEditCompetitionMode'] = date('Y-m-d', strtotime("now")) <  date('Y-m-d', strtotime($organizationDate->competition_date_earliest->competition_date)) ? 1 : 0;
        } else {
            $request['AllowEditCompetitionMode'] = 1;
        }

        $request['competition_format'] = $organizationDate["competition"]->format;
        $request['global_registration_date'] = $organizationDate["competition"]->global_registration_date;
        $request['global_registration_end_date'] = $organizationDate["competition"]->global_registration_end_date;
        $request['competition_start_date'] = $organizationDate["competition"]->competition_start_date;
        $request['competition_end_date'] = $organizationDate['extended_end_date'] ?? $organizationDate["competition"]->competition_end_date;
        $vaildate[] = [
            "AllowEditCompetitionMode" => 'required',
            "organization_id" => ["required", "nullable", "integer", Rule::exists('organization', "id")->where(function ($query) {
                $query->whereIn('status', ['active', 'added']);
            })],
            "competition_format" => 'integer|min:0|max:1',
            "competition_mode" => ['required', new CheckOrgAvailCompetitionMode],
            "edit_sessions.*" => 'boolean',
            "competition_dates.*" => [
                'required',
                'date',
                'distinct',
                'after_or_equal:competition_start_date',
                'after_or_equal:today',
                function ($attribute, $value, $fail) use ($request) {
                    $competitionEndDate = $request['competition_end_date'];
                    if (strtotime($value) > strtotime($competitionEndDate)) {
                        $fail("The $attribute must be a date before or equal to the competition end date and extended competition organization date.");
                    }
                },
            ],
            "registration_open_date" => ["required", "date", new CheckLocalRegistrationDateAvail],
            "translate" => "json",
        ];

        $validated = $request->validate(Arr::collapse($vaildate));

        try {
            if (isset($validated['competition_dates']) && count($validated['competition_dates']) > 0) {

                $InsertCompetitionOrganizationDates = [];

                for ($k = 0; $k < count($validated['competition_dates']); $k++) {
                    array_push($InsertCompetitionOrganizationDates, [
                        'competition_organization_id' => $request->id,
                        'competition_date' => $validated['competition_dates'][$k],
                        'created_by_userid' => auth()->user()->id,
                        'created_at' => Carbon::today()->format('Y-m-d h:i:s'),
                    ]);
                }


                if (count($organizationDate['all_competition_dates']->toArray()) > 0) {
                    $temp = [];
                    $organizationDate['all_competition_dates']->pluck('id');
                    $pastDates = $organizationDate['all_competition_dates']->map(function ($item) {
                        unset($item['id'], $item['updated_at']);
                        return $item;
                    })->toArray();


                    $InsertCompetitionOrganizationDates = collect(array_merge($pastDates, $InsertCompetitionOrganizationDates))->map(function ($item) use (&$temp) {

                        $date =  str_replace('-', '/', $item['competition_date']);
                        if (!in_array($date, $temp)) {
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

            if ($validated['AllowEditCompetitionMode']) {
                $organizationDate->competition_mode = $validated['competition_mode'];
            }
            $organizationDate->translate = $validated['translate'];

            if ($organizationDate->status !== 'lock' || auth()->user()->role_id == 0 || auth()->user()->role_id == 1) {
                $organizationDate->status = $validated['status'];
            }

            if ((auth()->user()->role_id == 0 || auth()->user()->role_id == 1) && isset($validated['edit_sessions'])) {
                $organizationDate->edit_sessions = $validated['edit_sessions'];
            }

            $organizationDate->save();

            if ($validated['competition_format'] == 0) {
                CompetitionOrganizationDate::where('competition_organization_id', $request->id)->delete();
                CompetitionOrganizationDate::insert($InsertCompetitionOrganizationDates);
            }

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "partner registration date update successful"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 404,
                "message" => "partner not found in competition"
            ]);
        }
    }

    public function updateExtendedEndDate(Request $request)
    {
        if (!(auth()->user()->role_id == 0 || auth()->user()->role_id == 1)) {
            return response()->json([
                "status" => 403,
                "message" => 'Unauthorized Access'
            ], 403);
        }

        $organizationDate = CompetitionOrganization::with([
            'competition',
            'competition_date_earliest',
            'all_competition_dates' => function ($query) {
                $query->whereDate('competition_date', '<=', date('Y-m-d', strtotime("now")))->orderBy('competition_date');
            }
        ])->where('id', $request->id)->firstOrFail();

        $validationRules['extended_end_date'] = [
            'required',
            'date',
            'after_or_equal:' . $organizationDate["competition"]->competition_end_date,
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                "status" => 500,
                "message" => $validator->errors()->first()
            ], 500);
        }

        try {
            DB::beginTransaction();

            $organizationDate->extended_end_date = $request->input('extended_end_date');
            $organizationDate->save();

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "Extended end date updated successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Competition organization not found"
            ], 500);
        }
    }

    public function deleteOrganization(Request $request)
    {
        //set in role permission country partner cannot delete
        try {
            $partnerDate = CompetitionOrganization::findorfail($request->id);
            $deletePartnerDate = $partnerDate->delete();

            if ($deletePartnerDate) {
                return response()->json([
                    "status" => 200,
                    "message" => "remove partner from competition successful"
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 404,
                "message"   => "Invalid record",
                "error"     => $e
            ], 404);
        }
    }

    public function uploadAnswers(UploadAnswersRequest $request)
    {
        DB::beginTransaction();
        try {
            $competition = Competition::find($request->competition_id);
            $levels = AnswerUploadHelper::getLevelsForGradeSet(
                $competition,
                array_unique(Arr::pluck($request->participants, 'grade')),
                true
            );
            $participants =  Participants::whereIn('index_no', Arr::pluck($request->participants, 'index_number'))
                ->pluck('grade', 'index_no');

            $createdBy = auth()->id();
            $createdAt = now();

            foreach ($request->participants as $participantData) {
                // if($levels[$participantData['grade']]['grade'] != $participants[$participantData['index_number']]) {
                //     throw ValidationException::withMessages(["Grade for participant with index {$participantData['index_number']} does not match the grade in the database"]);
                // }

                $level = $levels[$participantData['grade']]['level'];
                $levelTaskCount = $level->tasks->count();
                if ($levelTaskCount > count($participantData['answers'])) {
                    throw ValidationException::withMessages(["Answers count for participant with index {$participantData['index_number']} does not match the number of tasks in his grade level"]);
                }

                DB::table('participant_answers')->where('participant_index', $participantData['index_number'])->delete();
                // ParticipantsAnswer::where('participant_index', $participantData['index_number'])->delete();
                for($i = 0; $i < $levelTaskCount; $i++) {
                    ParticipantsAnswer::create([
                        'level_id'  => $level->id,
                        'task_id'   => $level->tasks[$i],
                        'participant_index' => $participantData['index_number'],
                        'answer'    => $participantData['answers'][$i],
                        'created_by_userid' => $createdBy,
                        'created_at'    => $createdAt
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                "status"    =>  201,
                "message"   => 'students answers uploaded successful'
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                "status" =>  $e->status,
                "message" => $e->getMessage()
            ], $e->status);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status" =>  500,
                "message" => 'students answers uploaded unsuccessful ' . $e->getMessage()
            ], 500);
        }
    }

    public function report(Competition $competition, Request $request)
    {
        try {
            $header = [
                'participant', 'index', 'certificate number', 'status', 'competition', 'organization', 'country',
                'level', 'grade', 'school', 'tuition', 'points', 'award', 'school_rank', 'country_rank', 'global rank'
            ];
            $competitionService = new CompetitionService($competition);
            $data = $competitionService->applyFilterToReport(
                $competitionService->getReportQuery($request->mode ?? 'all'),
                $request
            )->get();

            if ($data->count() === 0) return [];

            if ($request->mode === 'csv') return $data->prepend($header);

            $filterOptions = $competitionService->getReportFilterOptions($data->toArray());
            $data = CollectionHelper::searchCollection(
                $request->search,
                $data,
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

    private function addDifficultyGroup($collection_id, $competition_level)
    {
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

    private function addTaskMark($collection_id, $competition_level)
    {
        $taskIds = Arr::flatten(CollectionSections::where('collection_id', $collection_id)
            ->pluck('tasks')->toArray());

        $insert = Tasks::with(['taskAnswers' => function ($query) {
            return $query->whereNotNull('answer');
        }])->whereIn('id', $taskIds)->orderBy('id')->get()
            ->map(function ($items) {
                if (in_array($items->answer_structure, ['group', 'sequence'])) {
                    return [["task_answers_id" => $items->taskAnswers->sortBy('position')->pluck('id')->toJson()]];
                } else {
                    $items->taskAnswers->sortBy('position')->each(function ($row) use (&$temp) {
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
