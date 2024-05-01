<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EditParticipantAwardRequest;
use App\Http\Requests\StoreCompetitionMarkingGroupRequest;
use App\Models\CompetitionMarkingGroup;
use App\Models\Competition;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\getActiveParticipantsByCountryRequest;
use App\Http\Requests\SetAwardModerationRequest;
use App\Http\Requests\UpdateCompetitionMarkingGroupRequest;
use App\Jobs\ComputeLevel;
use App\Jobs\ComputeLevelGroupJob;
use App\Models\CompetitionLevels;
use App\Models\CompetitionParticipantsResults;
use App\Models\LevelGroupCompute;
use App\Models\Participants;
use App\Services\ComputeAwardStatsService;
use App\Services\ComputeLevelGroupService;
use App\Services\ComputeLevelService;
use App\Services\MarkingService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Helpers\ValiateLevelGroupForComputingHelper;

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
            $markingList = (new MarkingService())->markList($competition->load('rounds.levels.collection.sections'));
            return response()->json([
                "status"    => 200,
                "message"   => "Marking progress list retrieve successful",
                "data"      => $markingList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Marking progress list retrieve unsuccessful" . $e->getMessage(),
                "error"     => strval($e)
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
            $headerData->isComputed = $competition->isComputed();

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
    public function editMarkingGroup(CompetitionMarkingGroup $group, UpdateCompetitionMarkingGroupRequest $request){
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
     * Delete marking group
     *
     * @param App\Models\CompetitionMarkingGroup $group
     *
     * @return Illuminate\Http\Response
     */
    public function deleteMarkingGroup(CompetitionMarkingGroup $group)
    {
        try {
            DB::transaction(function () use($group){
                $grades = $group->countries()->join('participants', 'participants.country_id', 'all_countries.id')
                    ->select('participants.grade')->distinct()->pluck('participants.grade');

                $levelIds = $group->competition->rounds()
                    ->join('competition_levels', 'competition_levels.round_id', 'competition_rounds.id')
                    ->pluck('competition_levels.grades', 'competition_levels.id')
                    ->filter(function ($levelGrades) use($grades){
                        foreach(json_decode($levelGrades, true) as $grade){
                            if($grades->contains($grade)){
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
     * @param \App\Models\Competition $competition
     * @param \App\Http\Requests\getActiveParticipantsByCountryRequest $request
     *
     * @return Illuminate\Http\Response
     */
    public function getActiveParticipantsByCountryByGrade(Competition $competition, getActiveParticipantsByCountryRequest $request)
    {
        try {
            [$countries, $totalParticipants, $totalParticipantsWithAnswer]
                = MarkingService::getActiveParticipantsByCountryByGradeData($competition, $request);

            $data = [];
            MarkingService::setTotalParticipantsByCountryByGrade($data, $countries, $totalParticipants);
            MarkingService::setTotalParticipantsWithAnswersAndAbsentees($data, $countries, $totalParticipantsWithAnswer);
            MarkingService::adjustDataTotalToIncludeAllCountries($data, $countries);

            return response()->json([
                "status"        => 200,
                "message"       => "Table retrieval was successful",
                'grades'        => $totalParticipants->pluck('grade')->unique()->values(),
                'countries'     => $countries->values(),
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
    public function computeResultsForSingleLevel(CompetitionLevels $level, Request|null $request)
    {
        try {
            ComputeLevelService::validateLevelForComputing($level);

            dispatch(new ComputeLevel($level, $request));
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
                "error"     => strval($e)
            ], 500);
        }
    }

    /**
     * Compute single level group results
     * @param \App\Models\CompetitionLevels $level
     * @param \App\Models\CompetitionMarkingGroup $group
     *
     * @return response
     */
    public function computeResultsForSingleLevelGroup(CompetitionLevels $level, CompetitionMarkingGroup $group, Request $request)
    {
        try {

            (new ValiateLevelGroupForComputingHelper($level, $group))->validate();
            dispatch(new ComputeLevelGroupJob($level, $group, $request->all()));
            ComputeLevelGroupService::storeLevelGroupRecords($level, $group, $request);

            \ResponseCache::clear();

            return response()->json([
                "status"    => 200,
                "message"   => "Level computing is in progress",
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                "status"    => $e->getCode() ?? 500,
                "message"   => $e->getMessage() ?? "Level couldn't be computed",
            ], $e->getCode() ?? 500);
        }
    }

    /**
     * Compute results for the competition
     *
     * @param \App\Models\Competition $competition
     *
     * @return response
     */
    public function computeCompetitionResults(Competition $competition, Request $request)
    {
        $competition->load('rounds.levels', 'groups');

        try {
            if($competition->groups->count() === 0){
                return response()->json([
                    "status"    => 412,
                    "message"   => "Competition has no groups, please add some country groups first",
                ], 412);
            }

            foreach($competition->rounds as $round){
                foreach($round->levels as $level){
                    foreach($competition->groups as $group){
                        if((new ValiateLevelGroupForComputingHelper($level, $group))->validate(throwException: false)) {
                            dispatch(new ComputeLevelGroupJob($level, $group, $request->all()));
                            ComputeLevelGroupService::storeLevelGroupRecords($level, $group, $request);
                        }
                    }
                }
            }

            \ResponseCache::clear();

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

        if($request->has('is_for_report') && $request->is_for_report){
            $data = CompetitionParticipantsResults::where('level_id', $level->id)
                ->whereHas('participant', function($query)use($group){
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
        }

        else{
            $data = $level->participantResults()
                ->where('competition_participants_results.group_id', $group->id)
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
                'cut_off_points'=> (new MarkingService())->getCutOffPoints($data),
                'awards'        => $level->rounds->roundsAwards->pluck('name')->concat([$level->rounds->default_award_name]),
                'awards_moderated' => $group->levelGroupCompute($level->id)->value('awards_moderated')
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
                "error"     => strval($e)
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
            $awardsRankArray = collect(['PERFECT SCORE'])
                    ->merge($level->rounds->roundsAwards->pluck('name'))
                    ->push($level->rounds->default_award_name);

            foreach($request->all() as $data){
                $participantResults = $level->participantResults()
                    ->where('competition_participants_results.participant_index', $data['participant_index'])
                    ->firstOrFail();

                $globalRankNumber = explode(" ", $participantResults->global_rank);
                if(Str::upper($participantResults->award) != "PERFECT SCORE"){
                    $participantResults->update([
                        'award'         => $data['award'],
                        'award_rank'    => $awardsRankArray->search($data['award']) + 1,
                        'global_rank'   => $participantResults->global_rank
                            ? sprintf("%s %s", $data['award'], Arr::last($globalRankNumber))
                            : Null
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

    /**
     * get participant results
     *
     * @param \App\Models\CompetitionMarkingGroup $group
     */
    public function getAwardsStats(CompetitionMarkingGroup $group)
    {
        try {
            [$headers, $data] = (new ComputeAwardStatsService($group))->getAwardsStats();
            return response()->json([
                "status"    => 200,
                "message"   => "Awards stats retrival successful",
                "headers"   => $headers,
                "data"      => $data
            ], 200);

        } catch(ValidationException $e){
            return response()->json([
                "status"    => $e->status,
                "message"   => $e->getMessage(),
            ], $e->status);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Awards stats retrival unsuccessful - " . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }
    }

    public function refreshMarkingList(Competition $competition)
    {
        \ResponseCache::clear();
        return response()->json([
            'status' => 'success',
        ]);
    }

    public function setAwardsModerated(CompetitionLevels $level, CompetitionMarkingGroup $group, SetAwardModerationRequest $request)
    {
        try {
            LevelGroupCompute::where('level_id', $level->id)
                ->where('group_id', $group->id)
                ->update(['awards_moderated' => $request->awards_moderated]);

            return response()->json([
                "status"    => 200,
                "message"   => "Moderation status updated successful",
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Moderation status update unsuccessful {$e->getMessage()}",
            ]);
        }
    }

    public function markSingleParticipant(Participants $participant)
    {
        try {
            DB::beginTransaction();

            $participant->markAnswers();
            $participantLevelId = $participant->competition->levels()->whereJsonContains('competition_levels.grades', $participant->grade)->value('competition_levels.id');

            CompetitionParticipantsResults::updateOrCreate([
                'participant_index' => $participant->index_no
            ], [
                'level_id'  => $participantLevelId,
                'points'    => $participant->answers->sum('score')
            ]);

            $participant->integrityCases()->delete();
            $participant->status = Participants::STATUS_RESULT_COMPUTED;
            $participant->save();

            DB::commit();
            return response()->json([
                'status'    => 200,
                'message'   => 'Participant Marks Updated Successfully'
            ], 200);
        }

        catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }
    }
}
