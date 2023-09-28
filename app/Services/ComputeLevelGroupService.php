<?php

namespace App\Services;

use App\Helpers\SetParticipantsAwardsHelper;
use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use App\Models\LevelGroupCompute;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
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
        if( !$levelGroupCompute ) return true;

        if($levelGroupCompute->computing_status === 'In Progress'){
            if($throwError) throw new \Exception("Grades {$level->name} is already under computing for this group {$group->name}, please wait till finished", 409);
            return false;
        }
        if( ! MarkingService::isLevelReadyToCompute($level) ){
            if($throwError) throw new \Exception("Level {$level->name} is not ready to compute, please check that all tasks in this level has answers and student answers are uploaded to this level", 406);
            return false;
        }

        return true;
    }
    
    public function computeResutlsForGroupLevel(array $request)
    {
        $this->clearRecords();
        $this->computeParticipantAnswersScores();
        $this->setupCompetitionParticipantsResultsTable();
        $this->setParticipantsGroupRank();
        if(array_key_exists('not_to_compute', $request) && is_array($request['not_to_compute'])){
            in_array('country_rank', $request['not_to_compute']) ?: $this->setParticipantsCountryRank();
            in_array('school_rank', $request['not_to_compute']) ?: $this->setParticipantsSchoolRank();
            in_array('award', $request['not_to_compute']) ?: $this->setParticipantsAwards();
            in_array('global_rank', $request['not_to_compute']) ?: $this->setParticipantsGlobalRank();
        };
        $this->setParticipantsAwardsRank();
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
                        $participantAnswer->is_correct = $participantAnswer->getIsCorrectAnswer($this->level->id);
                        $participantAnswer->score = $participantAnswer->getAnswerMark($this->level->id);
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
                    CompetitionParticipantsResults::create([
                        'level_id'              => $participantAnswer->level_id,
                        'participant_index'     => $participantAnswer->participant_index,
                        'points'                => ($participantAnswer->points ? $participantAnswer->points : 0) + $this->collectionInitialPoints,
                        'group_id'              => $this->group->id,
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
                ->orderBy('points', 'DESC')->get();

            foreach($participantResults as $index=>$participantResult){
                if($index === 0){
                    $participantResult->setAttribute('country_rank', $index+1);
                }elseif($participantResult->points === $participantResults[$index-1]->points){
                    $participantResult->setAttribute('country_rank', $participantResults[$index-1]->country_rank);
                }else{
                    $participantResult->setAttribute('country_rank', $index+1);
                }
                $participantResult->save();
            }
        }
    }

    private function setParticipantsSchoolRank()
    {
        $schoolIds = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $this->group->id)
            ->join('participants', 'competition_participants_results.participant_index', 'participants.index_no')
            ->select('participants.school_id')
            ->distinct()->pluck('school_id');

        foreach($schoolIds as $schoolId) {
            $participantResults = CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->where('group_id', $this->group->id)
                ->whereRelation('participant', 'school_id', $schoolId)
                ->orderBy('points', 'DESC')->get();

            foreach($participantResults as $index=>$participantResult){
                if($index === 0){
                    $participantResult->setAttribute('school_rank', $index+1);
                }elseif($participantResult->points === $participantResults[$index-1]->points){
                    $participantResult->setAttribute('school_rank', $participantResults[$index-1]->school_rank);
                }else{
                    $participantResult->setAttribute('school_rank', $index+1);
                }
                $participantResult->save();
            }
        }
        $this->updateComputeProgressPercentage(60);
    }

    private function setParticipantsAwards()
    {
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
            ->where('group_id', $this->group->id)
            ->orderBy('points', 'DESC')->get();

        foreach($participantResults as $index => $participantResult){
            if($index === 0){
                $participantResult->setAttribute('global_rank', sprintf("%s %s", $participantResult->award, $index+1));
            } elseif ($participantResult->points === $participantResults[$index-1]->points && $participantResults[$index-1]->group_id === $participantResult->group_id){
                $participantResult->setAttribute('global_rank', $participantResults[$index-1]->global_rank);
            } else {
                $participantResult->setAttribute('global_rank', sprintf("%s %s", $participantResult->award, $index+1));
            }
            $participantResult->save();
            $participantResult->participant->setAttribute('status', 'result computed');
            $participantResult->participant->save();
        }
        $this->updateComputeProgressPercentage(80);
    }
}
