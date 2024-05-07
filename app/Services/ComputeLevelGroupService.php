<?php

namespace App\Services;

use App\Helpers\SetParticipantsAwardsHelper;
use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use App\Models\LevelGroupCompute;
use App\Models\MarkingLogs;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use App\Traits\ComputeOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComputeLevelGroupService
{
    use ComputeOptions;

    private int $collectionInitialPoints;
    private array $groupCountriesIds;
    private array $requestComputeOptions;

    public function __construct(private CompetitionLevels $level, private CompetitionMarkingGroup $group)
    {
        $this->collectionInitialPoints = $level->collection()->value('initial_points');
        $this->groupCountriesIds = $group->countries()->pluck('id')->toArray();
        $this->requestComputeOptions = $this->getComputeOptions();
    }

    public static function storeLevelGroupRecords(CompetitionLevels $level, CompetitionMarkingGroup $group, Request $request)
    {
        DB::beginTransaction();
        LevelGroupCompute::updateOrCreate(
            ['level_id' => $level->id, 'group_id' => $group->id],
            ['computing_status' => LevelGroupCompute::STATUS_IN_PROGRESS, 'compute_progress_percentage' => 1, 'compute_error_message' => null]
        );

        MarkingLogs::create([
            'level_id' => $level->id,
            'group_id' => $group->id,
        ]);
        DB::commit();
    }

    public function computeResutlsForGroupLevel(array $request)
    {
        if($this->firstTimeCompute($this->level, $this->group)) {
            $this->computeParticipantAnswersScores();
            $this->setupCompetitionParticipantsResultsTable();
        }

        $this->updateParticipantsStatus();
        $this->setupIACStudentResults();

        $this->computeResultsAccordingToRequest($request);

        $this->setParticipantsGroupRank();
        $this->updateComputeProgressPercentage(100);
    }

    private function updateComputeProgressPercentage(int $percentage)
    {
        $levelGroupCompute = $this->group->levelGroupCompute($this->level->id)
            ->firstOrCreate(
                ['level_id' => $this->level->id, 'group_id' => $this->group->id],
                ['computing_status' => LevelGroupCompute::STATUS_IN_PROGRESS]
            );

        if($percentage === 100){
            $levelGroupCompute->updateStatus(LevelGroupCompute::STATUS_FINISHED);
            return;
        }

        $levelGroupCompute->setAttribute('compute_progress_percentage', $percentage);
        $levelGroupCompute->save();
    }

    private function clearRecords()
    {
        DB::transaction(function () {
            CompetitionParticipantsResults
                ::filterByLevelAndGroup($this->level->id, $this->group->id)
                ->delete();

            Participants::join('competition_organization', 'competition_organization.id', 'participants.competition_organization_id')
                ->join('competition', 'competition.id', 'competition_organization.competition_id')
                ->whereIn('participants.country_id', $this->groupCountriesIds)
                ->whereIn('participants.grade', $this->level->grades)
                ->where('competition.id', $this->level->rounds->competition_id)
                ->where('participants.status', '<>', Participants::STATUS_CHEATING)
                ->update(['participants.status' => 'active']);
        });
    }

    private function computeParticipantAnswersScores()
    {
        DB::transaction(function(){
            ParticipantsAnswer::where('level_id', $this->level->id)
                ->whereHas('participant', function($query){
                    $query->whereIn('country_id', $this->groupCountriesIds);
                })
                ->chunkById(1000, function ($participantAnswers) {
                    foreach ($participantAnswers as $participantAnswer) {
                        $participantAnswer->is_correct = $participantAnswer->getIsCorrectAnswer();
                        $participantAnswer->score = $participantAnswer->getAnswerMark();
                        $participantAnswer->save();
                    }
                });
            });
        $this->updateComputeProgressPercentage(20);
    }

    private function setupCompetitionParticipantsResultsTable()
    {
        DB::transaction(function(){
            ParticipantsAnswer::where('level_id', $this->level->id)
                ->whereHas('participant', function($query){
                    $query->whereIn('country_id', $this->groupCountriesIds);
                })
                ->select('*', DB::raw('SUM(score) AS points'))
                ->groupBy('participant_index')
                ->orderBy('points', 'DESC')
                ->get()
                ->each(function($participantAnswer) {
                    CompetitionParticipantsResults::updateOrCreate([
                        'participant_index'     => $participantAnswer->participant_index,
                        'level_id'              => $participantAnswer->level_id,
                        'group_id'              => $this->group->id,
                    ], [
                        'points'                => ($participantAnswer->points ? $participantAnswer->points : 0) + $this->collectionInitialPoints,
                    ]);
                });

            $this->updateComputeProgressPercentage(25);
        });
    }

    private function computeResultsAccordingToRequest()
    {
        foreach($this>computeOptions as $option){
            $this->willCompute($option) && $this->compute($option);
        }
    }

    private function compute(string $option)
    {
        switch($option){
            case 'remark':
                $this->remark();
                break;
            case 'award':
                $this->setParticipantsAwards();
                $this->setParticipantsAwardsRank();
                break;
            case 'country_rank':
                $this->setParticipantsCountryRank();
                break;
            case 'school_rank':
                $this->setParticipantsSchoolRank();
                break;
            case 'global_rank':
                $this->setParticipantsGlobalRank();
                break;
        }
    }

    private function setParticipantsGroupRank()
    {
        DB::transaction(function(){
            $participantResults = CompetitionParticipantsResults
                ::filterByLevelAndGroup($this->level->id, $this->group->id)
                ->orderBy('points', 'DESC')
                ->get()
                ->groupBy('award');

            foreach($participantResults as $results) {
                foreach($results as $index => $participantResult){
                    if($index === 0){
                        $participantResult->setAttribute('group_rank', $index+1);
                    }elseif($participantResult->points === $results[$index-1]->points){
                        $participantResult->setAttribute('group_rank', $results[$index-1]->country_rank);
                    }else{
                        $participantResult->setAttribute('group_rank', $index+1);
                    }
                    $participantResult->save();
                }
            }
        });
    }

    private function setParticipantsCountryRank()
    {
        foreach($this->groupCountriesIds as $countryId) {
            $participantResults = CompetitionParticipantsResults
                ::filterByLevelAndGroup($this->level->id, $this->group->id)
                ->whereRelation('participant', 'country_id', $countryId)
                ->orderBy('points', 'DESC')
                ->get()
                ->groupBy('award');

            foreach($participantResults as $results) {
                $results =  $results->sortByDesc('points');
                foreach($results as $index => $participantResult){
                    if($index === 0){
                        $participantResult->setAttribute('country_rank', $index+1);
                    }elseif($participantResult->points === $results[$index-1]->points){
                        $participantResult->setAttribute('country_rank', $results[$index-1]->country_rank);
                    }else{
                        $participantResult->setAttribute('country_rank', $index+1);
                    }
                    $participantResult->save();
                }
            }
        }
    }

    private function setParticipantsSchoolRank()
    {
        $schoolIds = CompetitionParticipantsResults
            ::filterByLevelAndGroup($this->level->id, $this->group->id)
            ->join('participants', 'competition_participants_results.participant_index', 'participants.index_no')
            ->select('participants.school_id')
            ->distinct()
            ->pluck('school_id');

        foreach($schoolIds as $schoolId) {
            $participantResults = CompetitionParticipantsResults
                ::filterByLevelAndGroup($this->level->id, $this->group->id)
                ->whereRelation('participant', 'school_id', $schoolId)
                ->orderBy('points', 'DESC')
                ->get()
                ->groupBy('award');

            foreach($participantResults as $results) {
                $results =  $results->sortByDesc('points');
                foreach($results as $index => $participantResult){
                    if($index === 0){
                        $participantResult->setAttribute('school_rank', $index+1);
                    }elseif($participantResult->points === $results[$index-1]->points){
                        $participantResult->setAttribute('school_rank', $results[$index-1]->school_rank);
                    }else{
                        $participantResult->setAttribute('school_rank', $index+1);
                    }
                    $participantResult->save();
                }
            }
        }
        $this->updateComputeProgressPercentage(60);
    }

    private function setParticipantsAwards()
    {
        $this->clearAwardForParticipants();
        $this->group->levelGroupCompute($this->level->id)->update(['awards_moderated' => false]);
        $this->setPerfectScoreAward();
        (new SetParticipantsAwardsHelper($this->level, $this->group))->setParticipantsAwards();
        $this->updateComputeProgressPercentage(70);
    }

    private function setPerfectScoreAward()
    {
        CompetitionParticipantsResults
            ::filterByLevelAndGroup($this->level->id, $this->group->id)
            ->where('points', $this->level->maxPoints())
            ->update([
                'award'     => 'PERFECT SCORE',
                'ref_award' => 'PERFECT SCORE',
                'percentile'=> '100.00'
            ]);
    }

    private function setParticipantsAwardsRank()
    {
        $awards = $this->level->rounds->roundsAwards;

        $awardsRankArray = collect(['PERFECT SCORE'])
            ->merge($awards->pluck('name'))
            ->push($this->level->rounds->default_award_name);

        $awardsRankArray->each(function($award, $key){
            CompetitionParticipantsResults
                ::filterByLevelAndGroup($this->level->id, $this->group->id)
                ->where('award', $award)
                ->update([
                    'award_rank' => $key+1
                ]);
        });
        $this->updateComputeProgressPercentage(90);
    }

    private function setParticipantsGlobalRank()
    {
        $participantResults = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->onlyResultComputedParticipants()
            ->orderBy('points', 'DESC')
            ->get()
            ->groupBy('award');

        foreach($participantResults as $award => $results) {
            $results =  $results->sortByDesc('points');
            foreach($results as $index => $participantResult){
                if($index === 0){
                    $participantResult->setAttribute('global_rank', sprintf("%s %s", $award, $index+1));
                } elseif ($participantResult->points === $results[$index-1]->points){
                    $globalRankNumber = preg_replace('/[^0-9]/', '', $results[$index-1]->global_rank);
                    $participantResult->setAttribute('global_rank', sprintf("%s %s", $award, $globalRankNumber));
                } else {
                    $participantResult->setAttribute('global_rank', sprintf("%s %s", $award, $index+1));
                }
                $participantResult->save();
            }
        }

        $this->updateComputeProgressPercentage(80);
    }

    private function updateParticipantsStatus()
    {
        $this->updateAttendees();
        $this->updateAbsentees();
    }

    public static function firstTimeCompute(CompetitionLevels $level, CompetitionMarkingGroup $group): bool
    {
        return CompetitionParticipantsResults::filterByLevelAndGroup($level->id, $group->id)->doesntExist();
    }

    private function checkIfShouldClearPrevRecords($request): bool
    {
        if(!array_key_exists('clear_previous_results', $request)) return true; // The function is not implemented frontend yet

        return $request['clear_previous_results'] == true;
    }

    private function updateAttendees()
    {
        Participants::join('competition_participants_results', 'competition_participants_results.participant_index', 'participants.index_no')
            ->where('competition_participants_results.level_id', $this->level->id)
            ->where('competition_participants_results.group_id', $this->group->id)
            ->where('participants.status', '<>', Participants::STATUS_CHEATING)
            ->update(['participants.status' => 'result computed']);
    }

    private function updateAbsentees()
    {
        $this->level->participants()
            ->whereIn('participants.country_id', $this->groupCountriesIds)
            ->where('participants.status', 'active')
            ->update(['participants.status' => 'absent']);
    }

    private function setupIACStudentResults()
    {
        $round = $this->level->rounds()->with('roundsAwards')->first();
        $defaultAwardRank = $round->roundsAwards->count() + 2;

        $this->level->participants()
            ->whereIn('participants.country_id', $this->groupCountriesIds)
            ->where('participants.status', Participants::STATUS_CHEATING)
            ->pluck('participants.index_no')
            ->each( function($index) use($round, $defaultAwardRank){
                CompetitionParticipantsResults::updateOrCreate(
                [
                    'level_id'          => $this->level->id,
                    'participant_index' => $index,
                    'group_id'          => $this->group->id,
                ],
                [
                    'ref_award'         => $round->default_award_name,
                    'award'             => $round->default_award_name,
                    'award_rank'        => $defaultAwardRank,
                    'points'            => null,
                    'percentile'        => null,
                    'school_rank'       => null,
                    'country_rank'      => null,
                    'global_rank'       => null,
                    'group_rank'        => null,
                    'report'            => null,
                ]);
            });
    }

    private function remark()
    {
        $this->computeParticipantAnswersScores();
        $this->setupCompetitionParticipantsResultsTable();
    }

    private function clearAwardForParticipants()
    {
        CompetitionParticipantsResults::filterByLevelAndGroup($this->level->id, $this->group->id)
            ->update(['award' => null, 'ref_award' => null, 'percentile' => null]);
    }
}
