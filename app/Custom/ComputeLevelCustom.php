<?php

namespace App\Custom;

use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ComputeLevelCustom
{
    private $progressPreviousVal = 20;
    private $level;
    private $participantsAnswersGrouped;
    private $awards;
    private $totalParticipantsAnswersGrouped;


    /**
     * @param \App\Models\CompetitionLevels $level
     */
    function __construct(CompetitionLevels $level)
    {
        $this->level = $level->load('participantsAnswersUploaded');
        $this->participantsAnswersGrouped = ParticipantsAnswer::where('level_id', $level->id)
                ->select('*', DB::raw('SUM(score) AS points'))->groupBy('participant_index')
                ->orderBy('points', 'DESC')->get();
        $this->totalParticipantsAnswersGrouped = count($this->participantsAnswersGrouped);
        
        $percentageSum = 0;
        $this->awards = $this->level->rounds->roundsAwards->sortBy('id')
            ->map(function ($award) use(&$percentageSum){
                $percentageSum += $award->percentage;
                return $award->setAttribute('percentage', $percentageSum);
            });
    }

    /**
     * validate competition level doing computation
     * 
     * @param \App\Models\CompetitionLevels $level
     */
    public static function validateLevelForComputing(CompetitionLevels $level)
    {
        if($level->computing_status === 'In Progress'){
            throw new \Exception("Level {$level->id} is already under computing, please wait till finished", 409);
        }

        if( !(new Marking())->isLevelReadyToCompute($level) ){
            throw new \Exception("Level {$level->id} is not ready to compute, please check that all tasks in this level has answers and answers are uploaded to this level", 406);
        }

        if($level->rounds->competition->groups()->count() === 0){
            throw new \Exception("There is no marking groups added for this competition", 406);
        }
    }


    /**
     * compute results for single level
     * 
     * @return void
     */
    public function computeResutlsForSingleLevel()
    {
        $this->clearRecords();
        $this->computeParticipantAnswersScores();
        $attendeesIds = [];
        foreach($this->participantsAnswersGrouped as $index=>$participantAnswer){                
            $this->setNecessaryAttirbutes($participantAnswer);
            CompetitionParticipantsResults::create([
                'level_id'              => $participantAnswer->level_id,
                'participant_index'     => $participantAnswer->participant_index,
                'ref_award'             => $participantAnswer->award,
                'award'                 => $participantAnswer->award,
                'points'                => $participantAnswer->points ? $participantAnswer->points : 0,         // avoid null values
                'school_rank'           => $participantAnswer->school_rank,
                'country_rank'          => $participantAnswer->country_rank,
                'group_rank'            => $participantAnswer->group_rank,
                'all_participants'      => $participantAnswer->all_participants,
                'global_rank'           => sprintf("%s %s", $participantAnswer->award, $participantAnswer->group_rank) 
            ]);
            $participantAnswer->participant->update(['status' => 'result computed']);
            $attendeesIds[] = $participantAnswer->participant->id;
            $this->updateComputeProgressPercentage($index);
        };
        $this->updateParticipantsAbsentees($attendeesIds);
        $this->level->updateStatus(CompetitionLevels::STATUS_FINISHED);
    }


    /**
     * Compute answers scores and store to competition_participants_results table
     * 
     * @return void
     */
    public function computeParticipantAnswersScores()
    {
        DB::transaction(function () {
            foreach($this->level->participantsAnswersUploaded as $participantAnswer){
                $participantAnswer->score = $participantAnswer->getAnswerMark();
                $participantAnswer->save();
            }
            $this->level->compute_progress_percentage = 20;
            $this->level->save();
        });
    }

    /**
     * update level compute progress percentage
     * 
     * @param int $index
     * 
     * @return void
     */
    private function updateComputeProgressPercentage(int $index)
    {
        $progress = (($index+1)/$this->totalParticipantsAnswersGrouped) * 100;
        if($progress > $this->progressPreviousVal) {
            $this->progressPreviousVal = $progress;
            $this->level->compute_progress_percentage = $progress;
            $this->level->save();
        }
    }

    /**
     * Set necessary attributes in answer model to use it in storing results
     * 
     * @param int $index
     * @param \App\Models\ParticipantsAnswer $participantsAnswer
     * 
     * @return void
     */
    public function setNecessaryAttirbutes(ParticipantsAnswer $participantsAnswer)
    {
        $this->setParticipantAwardAndGroupRankAttribute($participantsAnswer);
        $this->setSchoolRankAttribute($participantsAnswer);
        $this->setCountryRankAttribute($participantsAnswer);
    }

    /**
     * set reference award id attribute
     * 
     * @param \App\Models\ParticipantsAnswer $participantAnswer
     * 
     * @return void
     */
    protected function setParticipantAwardAndGroupRankAttribute(ParticipantsAnswer $participantAnswer)
    {
        $countriesIds = CompetitionMarkingGroup::where('competition_id', $this->level->rounds->competition_id)
                        ->whereRelation('countries', 'id', $participantAnswer->participant->country_id)
                        ->join('competition_marking_group_country as cmgc', 'cmgc.marking_group_id', 'competition_marking_group.id')
                        ->pluck('cmgc.country_id');

        $allParticipants = ParticipantsAnswer::where('level_id', $this->level->id)
            ->whereHas('participant', function($query)use($countriesIds){
                $query->whereIn('country_id', $countriesIds);
            })
            ->select('*', DB::raw('SUM(score) AS points'))->groupBy('participant_index')
            ->orderBy('points', 'DESC')->get();

        $this->setParticipantGroupRank($allParticipants, $participantAnswer);
        $this->setParticipantAward($allParticipants, $participantAnswer);
    }

    /**
     * Set participant award
     * 
     * @param Illuminate\Database\Eloquent\Collection $allParticipants
     * @param \App\Models\ParticipantsAnswer $participantAnswer
     * 
     * @return void
     */
    private function setParticipantGroupRank(Collection $allParticipants, ParticipantsAnswer $participantAnswer){
        foreach($allParticipants as $index=>$participant){
            if($participant->participant_index === $participantAnswer->participant_index){
                if($index > 0 && $participantAnswer->points === $allParticipants[$index-1]->points){
                    $groupRank = CompetitionParticipantsResults::where('level_id', $this->level->id)
                        ->where('participant_index', $allParticipants[$index-1]->participant_index)->value('group_rank');
                    $participantAnswer->setAttribute('group_rank', $groupRank);
                }else{
                    $participantAnswer->setAttribute('group_rank', $index+1);
                }
            }
        }
    }

    /**
     * Set participant group rank
     * 
     * @param Illuminate\Database\Eloquent\Collection $allParticipants
     * @param \App\Models\ParticipantsAnswer $participantAnswer
     * 
     * @return void
     */
    private function setParticipantAward(Collection $allParticipants, ParticipantsAnswer $participantAnswer){
        $isAwardSet = false;
        if($participantAnswer->points === $this->level->maxPoints()){
            $participantAnswer->setAttribute('award', 'PERFECT SCORER');
            $isAwardSet = true;
        }
        if(!$isAwardSet){
            $participantPercentageRank = round( ($participantAnswer->group_rank/count($allParticipants)) * 100 );
            $participantAnswer->setAttribute('all_participants', count($allParticipants));
            foreach($this->awards as $award){
                if(!$isAwardSet && $participantPercentageRank <= $award->percentage && $participantAnswer->points > $award->min_marks){
                    $participantAnswer->setAttribute('award', $award->name);
                    $isAwardSet = true;
                }
            }
            if(!$isAwardSet){
                $participantAnswer->setAttribute('award', $this->level->rounds->default_award_name);
            }
        }
    }

    /**
     * get participant school rank
     * 
     * @param \App\Models\ParticipantsAnswer participantAnswer
     * 
     * @return void
     */
    protected function setSchoolRankAttribute(ParticipantsAnswer $participantAnswer)
    {
        $allParticipants = ParticipantsAnswer::where('level_id', $this->level->id)
            ->whereHas('participant', function($query)use($participantAnswer){
                $query->where('school_id', $participantAnswer->participant->school_id);
            })
            ->select('*', DB::raw('SUM(score) AS points'))->groupBy('participant_index')
            ->orderBy('points', 'DESC')->get();
        
        foreach($allParticipants as $index=>$participant){
            if($participant->participant_index === $participantAnswer->participant_index){
                if($index > 0 && $participantAnswer->points === $allParticipants[$index-1]->points){
                    $participantAnswer->setAttribute('school_rank', $index);
                }else{
                    $participantAnswer->setAttribute('school_rank', $index+1);
                }
            }
        }
    }

    /**
     * set participant country rank attribute
     * 
     * @param \App\Models\ParticipantsAnswer $participantAnswer
     * 
     * @return void
     */
    protected function setCountryRankAttribute(ParticipantsAnswer $participantAnswer)
    {
        $allParticipants = ParticipantsAnswer::where('level_id', $this->level->id)
            ->whereHas('participant', function($query)use($participantAnswer){
                $query->where('country_id', $participantAnswer->participant->country_id);
            })
            ->select('*', DB::raw('SUM(score) AS points'))->groupBy('participant_index')
            ->orderBy('points', 'DESC')->get();
        
        foreach($allParticipants as $index=>$participant){
            if($participant->participant_index === $participantAnswer->participant_index){
                if($index > 0 && $participantAnswer->points === $allParticipants[$index-1]->points){
                    $participantAnswer->setAttribute('country_rank', $index);
                }else{
                    $participantAnswer->setAttribute('country_rank', $index+1);
                }
            }
        }
    }

    /**
     * update participants status for absentees
     * 
     * @param array $attendeesIds
     * 
     * @return void
     */
    protected function updateParticipantsAbsentees($attendeesIds)
    {
        $this->level->rounds->competition->participants()->whereIn('participants.grade', $this->level->grades)
            ->whereNotIn('participants.id', $attendeesIds)->update(['participants.status' => 'absent']);
    }

    /**
     * clear all results for this level and update participants status for this level to active status
     */
    protected function clearRecords(): void
    {
        CompetitionParticipantsResults::where('level_id', $this->level->id)->delete();
        Participants::whereIn('grade', $this->level->grades)
            ->join('competition_organization', 'competition_organization.id', 'participants.competition_organization_id')
            ->join('competition', 'competition.id', 'competition_organization.competition_id')
            ->where('competition.id', $this->level->rounds->competition_id)
            ->update(['participants.status' => 'active']);
    }
}