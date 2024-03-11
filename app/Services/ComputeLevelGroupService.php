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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComputeLevelGroupService
{
    private int $collectionInitialPoints;
    private array $groupCountriesIds;

    public function __construct(private CompetitionLevels $level, private CompetitionMarkingGroup $group)
    {
        $this->collectionInitialPoints = $level->collection()->value('initial_points');
        $this->groupCountriesIds = $group->countries()->pluck('id')->toArray();
    }

    public static function validateLevelGroupForComputing(
            CompetitionLevels $level,
            CompetitionMarkingGroup $group,
            $throwError = true
    ) {
        $levelGroupCompute = $group->levelGroupCompute($level->id)->first();

        if( $levelGroupCompute ) {
            if($levelGroupCompute->computing_status === 'In Progress'){
                if($throwError) throw new \Exception("Grades {$level->name} is already under computing for this group {$group->name}, please wait till finished", 409);
                return false;
            }
        };

        if( ! MarkingService::isLevelReadyToCompute($level) ){
            if($throwError) throw new \Exception("Level {$level->name} is not ready to compute, please check that all tasks in this level has answers and student answers are uploaded to this level", 400);
            return false;
        }

        if(static::checkIfAnyAnswerHasNotBeenComputed($level, $group)){
            if($throwError) throw new \Exception("Some of the answers have not been computed yet for this level {$level->name} and group {$group->name}, please select re-mark option to remark them", 400);
            return false;
        }

        if(static::checkIfShouldIncludeAwardsInRequest($level, $group)){
            if($throwError) throw new \Exception("Some of the awards have not been computed yet for this level {$level->name} and group {$group->name}, please select award option to compute award first", 400);
            return false;
        }

        if(static::checkIfAwardIsNullWhileComputingGlobalRanking($level, $group)) {
            if($throwError) throw new \Exception("Award is not computed for some of the countries inside this grade, you shall compute award for all countries inside this grade first", 400);
            return false;
        }

        if(static::awardAndGlobalRankWillBeComputedTogether()) {
            if($throwError) throw new \Exception("Award and Global Ranking will be computed together, please compute and moderate awards first", 400);
            return false;
        }

        if(static::awardsNotFullyModeratedToComputeGlobalRanking($level, $group)) {
            if($throwError) throw new \Exception("Awards are not fully moderated for this level {$level->name}, please moderate all awards for all group of countries first", 400);
            return false;
        }

        return true;
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
        $clearPreviousRecords = $this->firstTimeCompute($this->level, $this->group) || $this->checkIfShouldClearPrevRecords($request);

        if($clearPreviousRecords) {
            $this->clearRecords();
            $this->computeParticipantAnswersScores();
            $this->setupCompetitionParticipantsResultsTable();
            $this->setParticipantsGroupRank();
        }
        
        if(array_key_exists('not_to_compute', $request) && is_array($request['not_to_compute'])){
            in_array('remark', $request['not_to_compute']) ?: $this->remark();
            in_array('award', $request['not_to_compute']) ?: $this->setParticipantsAwards();
            in_array('country_rank', $request['not_to_compute']) ?: $this->setParticipantsCountryRank();
            in_array('school_rank', $request['not_to_compute']) ?: $this->setParticipantsSchoolRank();
            in_array('global_rank', $request['not_to_compute']) ?: $this->setParticipantsGlobalRank();
        };

        $this->setParticipantsAwardsRank();
        $this->updateParticipantsStatus();

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
            CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->where('group_id', $this->group->id)->delete();

            Participants::whereIn('participants.grade', $this->level->grades)
                ->join('competition_organization', 'competition_organization.id', 'participants.competition_organization_id')
                ->join('competition', 'competition.id', 'competition_organization.competition_id')
                ->where('competition.id', $this->level->rounds->competition_id)
                ->whereIn('participants.country_id', $this->groupCountriesIds)
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
            $attendeesIds = [];
            ParticipantsAnswer::where('level_id', $this->level->id)
                ->whereHas('participant', function($query){
                    $query->whereIn('country_id', $this->groupCountriesIds);
                })
                ->select('*', DB::raw('SUM(score) AS points'))->groupBy('participant_index')
                ->orderBy('points', 'DESC')
                ->get()
                ->each(function($participantAnswer) use(&$attendeesIds){
                    CompetitionParticipantsResults::updateOrCreate([
                        'participant_index'     => $participantAnswer->participant_index,
                        'level_id'              => $participantAnswer->level_id,
                        'group_id'              => $this->group->id,
                    ], [
                        'points'                => ($participantAnswer->points ? $participantAnswer->points : 0) + $this->collectionInitialPoints,
                    ]);
                    $attendeesIds[] = $participantAnswer->participant->id;
                });

            $this->updateParticipantsAbsentees($attendeesIds);
            $this->updateComputeProgressPercentage(25);
        });
    }

    private function updateParticipantsAbsentees(array $attendeesIds)
    {
        $this->level->participants()
            ->whereNotIn('participants.id', $attendeesIds)
            ->whereIn('participants.country_id', $this->groupCountriesIds)
            ->update(['participants.status' => 'absent']);
    }

    private function setParticipantsGroupRank()
    {
        DB::transaction(function(){
            $participantResults = CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->where('group_id', $this->group->id)
                ->orderBy('points', 'DESC')->get();

            foreach($participantResults as $index => $participantResult){
                if($index === 0){
                    $participantResult->setAttribute('group_rank', $index+1);
                } elseif ($participantResult->points === $participantResults[$index-1]->points){
                    $participantResult->setAttribute('group_rank', $participantResults[$index-1]->group_rank);
                } else {
                    $participantResult->setAttribute('group_rank', $index+1);
                }
                $participantResult->save();
            }
        });
    }

    private function setParticipantsCountryRank()
    {
        foreach($this->groupCountriesIds as $countryId) {
            $participantResults = CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->where('group_id', $this->group->id)
                ->whereRelation('participant', 'country_id', $countryId)
                ->orderBy('points', 'DESC')
                ->get()
                ->groupBy('award');

            foreach($participantResults as $results) {
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
        $schoolIds = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $this->group->id)
            ->join('participants', 'competition_participants_results.participant_index', 'participants.index_no')
            ->select('participants.school_id')
            ->distinct()
            ->pluck('school_id');

        foreach($schoolIds as $schoolId) {
            $participantResults = CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->where('group_id', $this->group->id)
                ->whereRelation('participant', 'school_id', $schoolId)
                ->orderBy('points', 'DESC')
                ->get()
                ->groupBy('award');

            foreach($participantResults as $results) {
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
        CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $this->group->id)
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
            CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->where('group_id', $this->group->id)
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
            ->orderBy('points', 'DESC')
            ->get()
            ->groupBy('award');

        foreach($participantResults as $award => $results) {
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
        // update attendees
        Participants::join('competition_participants_results', 'competition_participants_results.participant_index', 'participants.index_no')
            ->where('competition_participants_results.level_id', $this->level->id)
            ->where('competition_participants_results.group_id', $this->group->id)
            ->update(['participants.status' => 'result computed']);

        // update absentees
        $this->level->participants()
            ->whereIn('participants.country_id', $this->groupCountriesIds)
            ->where('participants.status', 'active')
            ->update(['participants.status' => 'absent']);
    }

    private static function firstTimeCompute(CompetitionLevels $level, CompetitionMarkingGroup $group): bool
    {
        return CompetitionParticipantsResults::where('level_id', $level->id)
            ->where('group_id', $group->id)->doesntExist();
    }

    private function checkIfShouldClearPrevRecords($request): bool
    {
        if(!array_key_exists('clear_previous_results', $request)) return true; // The function is not implemented frontend yet

        return $request['clear_previous_results'] == true;
    }

    private function remark()
    {
        $this->computeParticipantAnswersScores();
        $this->setupCompetitionParticipantsResultsTable();
    }

    private static function checkIfShouldIncludeAwardsInRequest(CompetitionLevels $level, CompetitionMarkingGroup $group): bool
    {
        return in_array('award', request('not_to_compute'))
            && static::isRankingIncludedInRequest()
            && static::checkIfAwardIsNotSet($level, $group);
    }

    private static function isRankingIncludedInRequest(): bool
    {
        
        return count(
            array_intersect(request('not_to_compute'), ['country_rank', 'school_rank', 'global_rank'])
        ) < 3;
    }

    private static function checkIfAwardIsNotSet(CompetitionLevels $level, CompetitionMarkingGroup $group): bool
    {
        if(request('clear_previous_results')) return false;

        return CompetitionParticipantsResults::where('level_id', $level->id)
            ->where('group_id', $group->id)
            ->whereNull('award')
            ->exists();
    }

    private static function checkIfAnyAnswerHasNotBeenComputed(CompetitionLevels $level, CompetitionMarkingGroup $group): bool
    {
        return request()->has('not_to_compute')
            && is_array(request('not_to_compute'))
            && in_array('remark', request('not_to_compute'))
            && !static::firstTimeCompute($level, $group)
            && static::checkIfAnyAnswerHasANullScore($level, $group);
    }

    private static function checkIfAnyAnswerHasANullScore(CompetitionLevels $level, CompetitionMarkingGroup $group): bool
    {
        return ParticipantsAnswer::where('level_id', $level->id)
            ->whereHas('participant', function($query) use($group){
                $query->whereIn('country_id', $group->countries()->pluck('id')->toArray());
            })
            ->whereNull('score')
            ->exists();
    }

    private static function checkIfAwardIsNullWhileComputingGlobalRanking(CompetitionLevels $level, CompetitionMarkingGroup $group)
    {
        if(in_array('global_rank', request('not_to_compute'))) return false;

        if(static::checkIfNewStudentsAdded($level)) return true;

        if(in_array('award', request('not_to_compute'))) {
            // award will not be computed
            return CompetitionParticipantsResults::where('level_id', $level->id)
                ->whereNull('award')
                ->exists();
        };

        // award will computed for this level and group, need to check for other groups
        return CompetitionParticipantsResults::where('level_id', $level->id)
            ->where('group_id', '<>', $group->id)
            ->whereNull('award')
            ->exists();
    }

    private static function checkIfNewStudentsAdded(CompetitionLevels $level)
    {
        return ParticipantsAnswer::where('level_id', $level->id)
            ->whereNull('score')
            ->exists();
    }

    private function clearAwardForParticipants()
    {
        CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $this->group->id)
            ->update(['award' => null, 'ref_award' => null, 'percentile' => null]);
    }

    private static function awardAndGlobalRankWillBeComputedTogether()
    {
        return !in_array('award', request('not_to_compute'))
            && !in_array('global_rank', request('not_to_compute'));
    }

    private static function awardsNotFullyModeratedToComputeGlobalRanking(CompetitionLevels $level, CompetitionMarkingGroup $group)
    {
        if(in_array('global_rank', request('not_to_compute'))) return false;

        return LevelGroupCompute::where('level_id', $level->id)
            ->where('awards_moderated', false)
            ->exists();
    }
}
