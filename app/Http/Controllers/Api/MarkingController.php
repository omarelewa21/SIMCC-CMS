<?php

namespace App\Http\Controllers\Api;

use ResponseCache;
use App\Custom\ComputeLevelCustom;
use App\Custom\Marking;
use App\Http\Controllers\Controller;
use App\Http\Requests\EditParticipantAwardRequest;
use App\Http\Requests\StoreCompetitionMarkingGroupRequest;
use App\Models\CompetitionMarkingGroup;
use App\Models\Competition;
use App\Models\Countries;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\getActiveParticipantsByCountryRequest;
use App\Http\Requests\UpdateCompetitionMarkingGroupRequest;
use App\Jobs\ComputeLevel;
use App\Models\CompetitionLevels;
use App\Models\CompetitionParticipantsResults;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MarkingController extends Controller
{
    /**
     * Marking overview page
     *
     * @param App\Models\Competition $competition
     *
     * @return Illuminate\Http\Response
     */
    public function markingList(Competition $competition)
    {
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

            foreach ($request->countries as $country_id) {
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
    public function editMarkingGroup(CompetitionMarkingGroup $group, UpdateCompetitionMarkingGroupRequest $request)
    {
        $group->undoComputedResults('active');
        try {
            $group->update([
                'name'                  => $request->name,
                'last_modified_userid'  => auth()->user()->id
            ]);

            DB::table('competition_marking_group_country')->where('marking_group_id', $group->id)->delete();
            foreach ($request->countries as $country_id) {
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
     * Delete marking group
     *
     * @param App\Models\CompetitionMarkingGroup $group
     *
     * @return Illuminate\Http\Response
     */
    public function deleteMarkingGroup(CompetitionMarkingGroup $group)
    {
        try {
            DB::transaction(function () use ($group) {
                $grades = $group->countries()->join('participants', 'participants.country_id', 'all_countries.id')
                    ->select('participants.grade')->distinct()->pluck('participants.grade');

                $levelIds = $group->competition->rounds()
                    ->join('competition_levels', 'competition_levels.round_id', 'competition_rounds.id')
                    ->pluck('competition_levels.grades', 'competition_levels.id')
                    ->filter(function ($levelGrades) use ($grades) {
                        foreach (json_decode($levelGrades, true) as $grade) {
                            if ($grades->contains($grade)) {
                                return $levelGrades;
                            }
                        }
                    })->keys();

                $countryIds = $group->countries()->pluck('all_countries.id');
                CompetitionParticipantsResults::whereIn('level_id', $levelIds)
                    ->join('participants', 'participants.index_no', 'competition_participants_results.participant_index')
                    ->whereIn('participants.country_id', $countryIds)
                    ->delete();

                $group->delete();
            });

            return response()->json([
                "status"    => 200,
                "message"   => "Marking group deleted successful"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Deleting marking group unsuccessful",
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
        ini_set('max_execution_time', 0);

        try {
            $grades = DB::select(DB::raw("
                SELECT DISTINCT participants.grade
                FROM participants
                JOIN competition_organization AS co1 ON co1.id = participants.competition_organization_id
                WHERE co1.competition_id = :competition_id
                AND participants.country_id IN (" . implode(',', $request->countries) . ")
                AND participants.status = 'active'
            "), [
                'competition_id' => $competition->id,
            ]);

            $grades = array_column($grades, 'grade');

            $countries = [];
            $data = [];

            foreach ($request->countries as $country_id) {
                $country = Countries::find($country_id);
                $countries[] = $country->display_name;
                $countryData = [];

                foreach ($grades as $grade) {
                    $totalParticipants = DB::select(DB::raw("
                        SELECT COUNT(*) AS totalParticipants
                        FROM participants
                        JOIN competition_organization AS co2 ON co2.id = participants.competition_organization_id
                        WHERE co2.competition_id = :competition_id
                        AND participants.country_id = :country_id
                        AND participants.status = 'active'
                        AND participants.grade = :grade
                    "), [
                        'competition_id' => $competition->id,
                        'country_id' => $country_id,
                        'grade' => $grade,
                    ]);

                    $absentees = DB::select(DB::raw("
                        SELECT COUNT(*) AS absentees
                        FROM participants
                        JOIN competition_organization AS co2 ON co2.id = participants.competition_organization_id
                        WHERE co2.competition_id = :competition_id
                        AND participants.country_id = :country_id
                        AND participants.status = 'absent'
                        AND participants.grade = :grade
                    "), [
                        'competition_id' => $competition->id,
                        'country_id' => $country_id,
                        'grade' => $grade,
                    ]);

                    $participantsWithAnswersUploaded = DB::select(DB::raw("
                        SELECT COUNT(DISTINCT participants.id) AS participants_with_answers_uploaded
                        FROM participants
                        JOIN competition_organization AS co3 ON co3.id = participants.competition_organization_id
                        LEFT JOIN participant_answers ON participants.id = participant_answers.participant_index
                        WHERE co3.competition_id = :competition_id
                        AND participants.country_id = :country_id
                        AND participants.grade = :grade
                        AND participant_answers.answer IS NOT NULL
                    "), [
                        'competition_id' => $competition->id,
                        'country_id' => $country_id,
                        'grade' => $grade,
                    ]);

                    $countryData[] = [
                        'grade' => $grade,
                        'total_participants' => $totalParticipants[0]->totalParticipants,
                        'absentees' => $absentees[0]->absentees,
                        'participants_with_answers_uploaded' => $participantsWithAnswersUploaded[0]->participants_with_answers_uploaded,
                    ];
                }

                $data[] = [
                    'country' => $country->display_name,
                    'data' => $countryData,
                ];
            }

            return response()->json([
                'data' => $data,
                'grades' => $grades,
                'countries' => $countries,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
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
    public function computeResultsForSingleLevel(CompetitionLevels $level, Request|null $request)
    {
        try {
            ComputeLevelCustom::validateLevelForComputing($level);

            dispatch(new ComputeLevel($level, $request));
            $level->updateStatus(CompetitionLevels::STATUS_In_PROGRESS);

            //ResponseCache::forget('api/marking/'.$competition->id); <-- need get competition id first before enable
            ResponseCache::clear();

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
    public function computeCompetitionResults(Competition $competition, Request|null $request)
    {

        try {
            if ($competition->groups()->count() === 0) {
                return response()->json([
                    "status"    => 412,
                    "message"   => "Competition has no groups, please add some country groups first",
                ], 412);
            }

            foreach ($competition->rounds as $round) {
                foreach ($round->levels as $level) {
                    if (Marking::isLevelReadyToCompute($level) && $level->computing_status != CompetitionLevels::STATUS_In_PROGRESS) {
                        dispatch(new ComputeLevel($level, $request));
                        $level->updateStatus(CompetitionLevels::STATUS_In_PROGRESS);
                    }
                }
            }

            //ResponseCache::forget('api/marking/'.$competition->id);
            ResponseCache::clear(); // <--this clear all pages cache;

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

    /**
     * get moderate list for a level
     *
     * @param \App\Models\CompetitionLevels $level
     *
     * @return response
     */
    public function moderateList(CompetitionLevels $level, CompetitionMarkingGroup $group, Request $request)
    {
        $level->load('rounds.competition', 'participantResults');

        if ($request->has('is_for_report') && $request->is_for_report) {
            $data = CompetitionParticipantsResults::where('level_id', $level->id)
                ->whereHas('participant', function ($query) use ($group) {
                    $query->whereIn('country_id', $group->countries()->pluck('all_countries.id')->toArray());
                })
                ->leftJoin('competition_levels', 'competition_levels.id', 'competition_participants_results.level_id')
                ->leftJoin('competition_rounds', 'competition_levels.round_id', 'competition_rounds.id')
                ->leftJoin('competition', 'competition.id', 'competition_rounds.competition_id')
                ->leftJoin('participants', 'participants.index_no', 'competition_participants_results.participant_index')
                ->leftJoin('schools', 'participants.school_id', 'schools.id')
                ->leftJoin('all_countries', 'all_countries.id', 'participants.country_id')
                ->leftJoin('competition_organization', 'participants.competition_organization_id', 'competition_organization.id')
                ->leftJoin('organization', 'organization.id', 'competition_organization.organization_id')
                ->select(
                    DB::raw("CONCAT('\"', competition.name, '\"') AS competition"),
                    DB::raw("CONCAT('\"', organization.name, '\"') AS organization"),
                    DB::raw("CONCAT('\"', all_countries.display_name, '\"') AS country"),
                    DB::raw("CONCAT('\"', competition_levels.name, '\"') AS level"),
                    'participants.grade',
                    DB::raw("CONCAT('\"', schools.name, '\"') AS school"),
                    'participants.index_no as index',
                    DB::raw("CONCAT('\"', participants.name, '\"') AS participant"),
                    'competition_participants_results.points',
                    DB::raw("CONCAT('\"', competition_participants_results.award, '\"') AS award"),
                    'competition_participants_results.school_rank',
                    'competition_participants_results.country_rank',
                    'competition_participants_results.award_rank',
                    'competition_participants_results.percentile',
                    DB::raw("CONCAT('\"', competition_participants_results.global_rank, '\"') AS global_rank")
                )
                ->distinct('index')->orderBy('points', 'DESC')->orderBy('percentile', 'DESC')->get();

            return $data;
        } else {
            $data = $level->participantResults()
                ->join('participants', 'participants.index_no', 'competition_participants_results.participant_index')
                ->whereIn('participants.country_id', $group->countries()->pluck('all_countries.id'))
                ->with('participant.school:id,name', 'participant.country:id,display_name as name')
                ->orderBy('competition_participants_results.points', 'DESC')
                ->orderBy('competition_participants_results.percentile', 'DESC')
                ->get();
        }

        try {
            $headerData = [
                'competition'   => $level->rounds->competition->name,
                'round'         => $level->rounds->name,
                'level'         => $level->name,
                'award_type'    => $level->rounds->award_type,
                'cut_off_points' => (new Marking())->getCutOffPoints($data),
                'awards'        => $level->rounds->roundsAwards->pluck('name')->concat([$level->rounds->default_award_name])
            ];

            return response()->json([
                "status"        => 200,
                "message"       => "Participant results retrival successful",
                'header_data'   => $headerData,
                'data'          => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Participant results retrival unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    /**
     * edit participant award
     *
     * @param \App\Models\CompetitionLevels $level
     * @param \App\Http\Requests\EditParticipantAwardRequest $request
     *
     * @return response
     */
    public function editParticipantAward(CompetitionLevels $level, EditParticipantAwardRequest $request)
    {
        try {
            $awardsRankArray = collect(['PERFECT SCORER'])
                ->merge($level->rounds->roundsAwards->pluck('name'))
                ->push($level->rounds->default_award_name);

            foreach ($request->all() as $data) {
                $participantResults = $level->participantResults()->where('competition_participants_results.participant_index', $data['participant_index'])
                    ->firstOrFail();
                $globalRankNumber = explode(" ", $participantResults->global_rank);

                if ($participantResults->award != "PERFECT SCORER") {
                    $participantResults->update([
                        'award'         => $data['award'],
                        'award_rank'    => $awardsRankArray->search($data['award']) + 1,
                        'global_rank'   => sprintf("%s %s", $data['award'], Arr::last($globalRankNumber))
                    ]);
                }
            }
            return response()->json([
                "status"        => 200,
                "message"       => "Participant results update successful",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Participant results update unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }
}
