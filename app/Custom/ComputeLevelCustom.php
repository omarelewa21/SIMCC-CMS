<?php

namespace App\Custom;

use App\Models\CompetitionLevels;
use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComputeLevelCustom
{
    private $level;
    private $awards;

    function __construct(CompetitionLevels $level)
    {
        $this->level  = $level;
        $this->awards = $level->rounds->roundsAwards;
    }

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
     * @param Request $request
     */
    public function computeCustomFieldsForSingleLevelBasedOnRequest(Request $request)
    {
        if($request->score) $this->computeParticipantAnswersScores();
        if($request->groupRank) $this->setParticipantsGroupRank();
        if($request->countryRank) $this->setParticipantsCountryRank();
        if($request->schoolRank) $this->setParticipantsSchoolRank();
        if($request->awards) $this->setParticipantsAwards();
        if($request->awardsRank) $this->setParticipantsAwardsRank();
        if($request->globalRank) $this->setParticipantsGlobalRank();
        if($request->reportColumn) $this->setParticipantsReportColumn();
    }

    public function computeParticipantAnswersScores()
    {
        DB::transaction(function(){
            ParticipantsAnswer::where('level_id', $this->level->id)
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
                        'points'                => $participantAnswer->points ? $participantAnswer->points : 0,
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
        // Set Perfect Scorer
        CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('points', $this->level->maxPoints())
            ->update([
                'award'     => 'PERFECT SCORER',
                'ref_award' => 'PERFECT SCORER'
            ]);

        // Set participants awards
        $groupIds = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->select('group_id')->distinct()->pluck('group_id')->toArray();
        foreach($groupIds as $group_id){
            $count = CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->where('group_id', $group_id)->whereNull('award')->count();

            $totalCount = $count;
            $awardPercentage = 0;

            $this->awards->each(function($award) use($group_id,$totalCount,&$count,&$awardPercentage){
                $awardPercentage += $award->percentage;
                $percentileCutoff = 100 - $awardPercentage;

                for($count;$count>0;$count--) {
                    $positionPercentile = number_format(($count / $totalCount) * 100, 2, '.', '');
                    if($positionPercentile >= $percentileCutoff) {
                        CompetitionParticipantsResults::where('level_id', $this->level->id)
                            ->where('group_id', $group_id)
                            ->whereNull('award')
                            ->orderBy('points', 'DESC')
                            ->limit(1)
                            ->update([
                                'award'     => $award->name,
                                'ref_award' => $award->name,
                                'percentile' => $positionPercentile,
                            ]);
                    }
                    else
                    {
                        $updatedCount = $this->updateParticipantsWhoShareSamePointsAsLastParticipant($group_id, $award->name, $totalCount, $count);
                        $count = $updatedCount;
                        break;
                    }
                }

            });

            // Set default award
            for($count;$count>0;$count--)
            {
                $positionPercentile = number_format(($count / $totalCount) * 100, 2, '.', '');
                CompetitionParticipantsResults::where('level_id', $this->level->id)
                    ->where('group_id', $group_id)
                    ->whereNull('award')
                    ->orderBy('points', 'DESC')
                    ->limit(1)
                    ->update([
                        'award'     => $this->level->rounds->default_award_name,
                        'ref_award' => $this->level->rounds->default_award_name,
                        'percentile' => $positionPercentile,
                    ]);

                $this->updateComputeProgressPercentage(70);
            }
        }

    }

    private function updateParticipantsWhoShareSamePointsAsLastParticipant(int $group_id, string $awardName, int $totalCount, int $currentCount)
    {
        $lastParticipantPoints = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $group_id)
            ->where('award', $awardName)
            ->orderBy('points')->value('points');

        $competitionParticipantsQuery =  CompetitionParticipantsResults::where('level_id', $this->level->id)->where('group_id', $group_id)
            ->where('points', $lastParticipantPoints)
            ->whereNull('award');

        $competitionParticipantsQuery->get()
            ->each(function ($row,$index) use($totalCount, &$currentCount, $competitionParticipantsQuery, $awardName ) {
                CompetitionParticipantsResults::find($row->id)->update([
                    'award'     => $awardName,
                    'ref_award' => $awardName,
                    'percentile' => number_format(($currentCount / $totalCount) * 100, 2, '.', ''),
                ]);
                $currentCount--;
            });

        return $currentCount;
    }

    public function setParticipantsAwardsRank()
    {
        $awardsRankArray = collect(['PERFECT SCORER'])
            ->merge($this->awards->pluck('name'))
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
            }elseif($participantResult->points === $participantResults[$index-1]->points){
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
