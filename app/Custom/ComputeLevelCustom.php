<?php

namespace App\Custom;

use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use App\Models\ParticipantsAnswer;
use Illuminate\Support\Facades\DB;

class ComputeLevelCustom
{
    private $progressCheck = 0;
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
        $this->awards = $this->level->rounds->roundsAwards->map(function ($award) use(&$percentageSum){
            $percentageSum += $award->percentage;
            return $award->setAttribute('percentage', $percentageSum);
        });
    }

    /**
     * validate competition level doing computation
     * 
     * @param \App\Models\CompetitionLevels $level
     */
    public static function validateLevelForValidation(CompetitionLevels $level)
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
        $this->computeParticipantAnswersScores();
        foreach($this->participantsAnswersGrouped as $index=>$participantAnswer){                
            CompetitionParticipantsResults::where('level_id', $participantAnswer->level_id)
                ->where('participant_index', $participantAnswer->participant_index)->delete();

            $this->setNecessaryAttirbutes($participantAnswer);
            CompetitionParticipantsResults::create([
                'level_id'              => $participantAnswer->level_id,
                'participant_index'     => $participantAnswer->participant_index,
                'ref_award'             => $participantAnswer->award,
                'award'                 => $participantAnswer->award,
                'points'                => $participantAnswer->points,
                'school_rank'           => $participantAnswer->school_rank,
                'country_rank'          => $participantAnswer->country_rank,
                'group_rank'            => $participantAnswer->group_rank,
                'global_rank'           => $index+1
            ]);
            $participantAnswer->participant->update(['status'   => 'result computed']);

            $this->updateComputeProgressPercentage($index);
        };

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
        if($progress >= 25 && $progress < 50 && $this->progressCheck != 25){
            $this->progressCheck = 25;
            $this->level->compute_progress_percentage = 40;
            $this->level->save();
        }
        elseif($progress >= 50 && $progress < 75 && $this->progressCheck != 50){
            $this->progressCheck = 5;
            $this->level->compute_progress_percentage = 60;
            $this->level->save();
        }
        elseif($progress >= 75 && $progress < 100 && $this->progressCheck != 75){
            $this->progressCheck = 75;
            $this->level->compute_progress_percentage = 80;
            $this->level->save();
        }elseif($progress == 100){
            $this->progressCheck = 0;
            $this->level->compute_progress_percentage = 100;
            $this->level->save();
        }
    }

    /**
     * Set necessary attributes in answer model to use it in storing results
     * 
     * @param int $index
     * @param \App\Models\ParticipantsAnswer $answer
     * 
     * @return void
     */
    public function setNecessaryAttirbutes(ParticipantsAnswer $answer)
    {
        $this->setParticipantAwardAndGroupRankAttribute($answer);
        $this->setSchoolRankAttribute($answer);
        $this->setCountryRankAttribute($answer);
    }

    /**
     * get reference award id
     * 
     * @param \App\Models\ParticipantsAnswer $answer
     * 
     * @return string
     */
    protected function setParticipantAwardAndGroupRankAttribute(ParticipantsAnswer $answer)
    {
        $countriesIds = CompetitionMarkingGroup::where('competition_id', $this->level->rounds->competition_id)
                        ->whereRelation('countries', 'id', $answer->participant->country_id)
                        ->join('competition_marking_group_country as cmgc', 'cmgc.marking_group_id', 'competition_marking_group.id')
                        ->pluck('cmgc.country_id');
        
        $allParticipants = ParticipantsAnswer::where('level_id', $this->level->id)
            ->whereHas('participant', function($query)use($countriesIds){
                $query->whereIn('country_id', $countriesIds);
            })
            ->select('*', DB::raw('SUM(score) AS points'))->groupBy('participant_index')
            ->orderBy('points', 'DESC')->get();

        // set group rank attribute 
        foreach($allParticipants as $index=>$participant){
            if($participant->participant_index === $answer->participant_index){
                $answer->setAttribute('group_rank', $index+1);
            }
        }

        // Set award attribute
        $isAwardSet = false;
        if($answer->points === $this->level->maxPoints()){
            $answer->setAttribute('award', 'PERFECT SCORER');
            $isAwardSet = true;
        }
        if(!$isAwardSet){
            $participantPercentageRank = round( ($answer->group_rank/count($allParticipants)) * 100 );
            foreach($this->awards as $award){
                if(!$isAwardSet && $participantPercentageRank <= $award->percentage && $answer->points > $award->min_marks){
                    $answer->setAttribute('award', $award->name);
                    $isAwardSet = true;
                }
            }
            if(!$isAwardSet){
                $answer->setAttribute('award', $this->level->rounds->default_award_name);
            }
        }
    }

    /**
     * get participant school rank
     * 
     * @param \App\Models\ParticipantsAnswer $answer
     * 
     * @return int
     */
    protected function setSchoolRankAttribute(ParticipantsAnswer $answer)
    {
        $allParticipants = ParticipantsAnswer::where('level_id', $this->level->id)
            ->whereHas('participant', function($query)use($answer){
                $query->where('school_id', $answer->participant->school_id);
            })
            ->select('*', DB::raw('SUM(score) AS points'))->groupBy('participant_index')
            ->orderBy('points', 'DESC')->get();
        
        foreach($allParticipants as $index=>$participant){
            if($participant->participant_index === $answer->participant_index){
                $answer->setAttribute('school_rank', $index+1);
            }
        }
    }

    /**
     * get participant country rank
     * 
     * @param \App\Models\ParticipantsAnswer $answer
     * 
     * @return int
     */
    protected function setCountryRankAttribute(ParticipantsAnswer $answer)
    {
        $allParticipants = ParticipantsAnswer::where('level_id', $this->level->id)
            ->whereHas('participant', function($query)use($answer){
                $query->where('country_id', $answer->participant->country_id);
            })
            ->select('*', DB::raw('SUM(score) AS points'))->groupBy('participant_index')
            ->orderBy('points', 'DESC')->get();
        
        foreach($allParticipants as $index=>$participant){
            if($participant->participant_index === $answer->participant_index){
                $answer->setAttribute('country_rank', $index+1);
            }
        }
    }
}