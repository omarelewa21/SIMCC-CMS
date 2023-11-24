<?php

namespace App\Services;

use App\Helpers\SetParticipantsAwardsHelper;
use App\Models\CompetitionLevels;
use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use Illuminate\Support\Facades\DB;

class ComputeLevelService
{
    private $level;
    private $collectionInitialPoints;

    function __construct(CompetitionLevels $level)
    {
        $this->level = $level;
        $this->collectionInitialPoints = $level->collection()->value('initial_points');
    }

    public static function validateLevelForComputing(CompetitionLevels $level)
    {
        if($level->computing_status === 'In Progress'){
            throw new \Exception("Level {$level->id} is already under computing, please wait till finished", 409);
        }
        if( !(new MarkingService())->isLevelReadyToCompute($level) ){
            throw new \Exception("Level {$level->id} is not ready to compute, please check that all tasks in this level has answers and answers are uploaded to this level", 406);
        }
        if($level->rounds->competition->groups()->count() === 0){
            throw new \Exception("There is no marking groups added for this competition", 406);
        }
    }

    public function computeResutlsForSingleLevel()
    {
        $this->clearRecords();
        $this->computeParticipantAnswersScores();
        $this->setupCompetitionParticipantsResultsTable();
        $this->setParticipantsGroupRank();
        $this->setParticipantsCountryRank();
        $this->setParticipantsSchoolRank();
        $this->setParticipantsAwards();
        $this->setParticipantsAwardsRank();
        $this->setParticipantsGlobalRank();
        // $this->setParticipantsReportColumn();
        $this->level->updateStatus(CompetitionLevels::STATUS_FINISHED);
    }

    /**
     * Compute custom fields for single level based on request parameters
     * 
     * @param array $request
     */
    public function computeCustomFieldsForSingleLevelBasedOnRequest($request)
    {
        $request = collect($request);
        if($request->has('score')) $this->computeParticipantAnswersScores();
        if($request->has('groupRank')) $this->setParticipantsGroupRank();
        if($request->has('countryRank')) $this->setParticipantsCountryRank();
        if($request->has('schoolRank')) $this->setParticipantsSchoolRank();
        if($request->has('awards')) $this->setParticipantsAwards();
        if($request->has('awardRank')) $this->setParticipantsAwardsRank();
        if($request->has('globalRank')) $this->setParticipantsGlobalRank();
        if($request->has('reportColumn')) $this->setParticipantsReportColumn();
        $this->level->updateStatus(CompetitionLevels::STATUS_FINISHED);
    }

    public function computeParticipantAnswersScores()
    {
        DB::transaction(function(){
            ParticipantsAnswer::where('level_id', $this->level->id)
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

    public function setupCompetitionParticipantsResultsTable()
    {
        DB::transaction(function(){
            $attendeesIds = [];
            ParticipantsAnswer::where('level_id', $this->level->id)
                ->select('*', DB::raw('SUM(score) AS points'))->groupBy('participant_index')
                ->orderBy('points', 'DESC')
                ->get()
                ->each(function($participantAnswer) use(&$attendeesIds){
                    CompetitionParticipantsResults::create([
                        'level_id'              => $participantAnswer->level_id,
                        'participant_index'     => $participantAnswer->participant_index,
                        'points'                => ($participantAnswer->points ? $participantAnswer->points : 0) + $this->collectionInitialPoints,
                    ]);
                    $attendeesIds[] = $participantAnswer->participant->id;
                });
                $this->updateParticipantsAbsentees($attendeesIds);
                $this->updateComputeProgressPercentage(25);
            });
    }

    protected function updateComputeProgressPercentage(int $percentage)
    {
        if($percentage === 100){
            $this->level->updateStatus(CompetitionLevels::STATUS_FINISHED);
            return;
        }
        $this->level->setAttribute('compute_progress_percentage', $percentage);
        $this->level->save();
    }

    protected function setParticipantsGroupRank()
    {
        $this->level->rounds->competition->groups->each(function($group){
            $participantResults = CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->whereHas('participant', function($query)use($group){
                    $query->whereIn('country_id', $group->countries()->pluck('all_countries.id')->toArray());
                })->orderBy('points', 'DESC')->get();

            foreach($participantResults as $index=>$participantResult){
                $participantResult->setAttribute('group_id', $group->id);
                if($index === 0){
                    $participantResult->setAttribute('group_rank', $index+1);
                }elseif($participantResult->points === $participantResults[$index-1]->points){
                    $participantResult->setAttribute('group_rank', $participantResults[$index-1]->group_rank);
                }else{
                    $participantResult->setAttribute('group_rank', $index+1);
                }
                $participantResult->save();
            }
        });
        $this->updateComputeProgressPercentage(40);
    }

    protected function setParticipantsCountryRank()
    {
        $countryIds = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->join('participants', 'competition_participants_results.participant_index', 'participants.index_no')
            ->select('participants.country_id')->distinct()->pluck('country_id');

        $countryIds->each(function($countryId){
            $participantResults = CompetitionParticipantsResults::where('level_id', $this->level->id)
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
        });
        $this->updateComputeProgressPercentage(50);
    }

    protected function setParticipantsSchoolRank()
    {
        $schoolIds = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->join('participants', 'competition_participants_results.participant_index', 'participants.index_no')
            ->select('participants.school_id')->distinct()->pluck('school_id');

        $schoolIds->each(function($schoolId){
            $participantResults = CompetitionParticipantsResults::where('level_id', $this->level->id)
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
        });
        $this->updateComputeProgressPercentage(60);
    }

    protected function setParticipantsAwards()
    {
        $this->setPerfectScoreAward();
        (new SetParticipantsAwardsHelper($this->level))->setParticipantsAwards();
        $this->updateComputeProgressPercentage(70);
    }

    private function setPerfectScoreAward()
    {
        CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('points', $this->level->maxPoints())
            ->update([
                'award'     => 'PERFECT SCORE',
                'ref_award' => 'PERFECT SCORE',
                'percentile'=> '100.00'
            ]);
    }

    public function setParticipantsAwardsRank()
    {
        $awards = $this->level->rounds->roundsAwards;

        $awardsRankArray = collect(['PERFECT SCORE'])
            ->merge($awards->pluck('name'))
            ->push($this->level->rounds->default_award_name);

        $awardsRankArray->each(function($award, $key){
            CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->where('award', $award)
                ->update([
                    'award_rank' => $key+1
                ]);
        });
        $this->updateComputeProgressPercentage(80);
    }

    protected function setParticipantsGlobalRank()
    {
        $participantResults = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->orderBy('points', 'DESC')->get();

        foreach($participantResults as $index=>$participantResult){
            if($index === 0){
                $participantResult->setAttribute('global_rank', sprintf("%s %s", $participantResult->award, $index+1));
            }elseif($participantResult->points === $participantResults[$index-1]->points && $participantResults[$index-1]->group_id === $participantResult->group_id){
                $participantResult->setAttribute('global_rank', $participantResults[$index-1]->global_rank);
            }else{
                $participantResult->setAttribute('global_rank', sprintf("%s %s", $participantResult->award, $index+1));
            }
            $participantResult->save();
            $participantResult->participant->setAttribute('status', 'result computed');
            $participantResult->participant->save();
        }
        $this->updateComputeProgressPercentage(90);
    }

    protected function setParticipantsReportColumn()
    {
        $participantResults = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->orderBy('points', 'DESC')->get();
        $participantResults->each(function($participantResult){
            $report = new ParticipantReportService($participantResult->participant, $this->level);
            $participantResult->setAttribute('report', $report->getJsonReport());
            $participantResult->save();
        });
        $this->updateComputeProgressPercentage(100);
    }

    protected function updateParticipantsAbsentees(array $attendeesIds)
    {
        $this->level->rounds->competition->participants()->whereIn('participants.grade', $this->level->grades)
            ->whereNotIn('participants.id', $attendeesIds)->update(['participants.status' => 'absent']);
    }

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
