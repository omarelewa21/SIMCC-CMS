<?php

namespace App\Custom;

use App\Models\CompetitionLevels;
use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use Illuminate\Support\Facades\DB;

class ComputeLevelCustom
{
    private $level;
    private $participantsAnswersGrouped;
    private $awards;
    private $awardsRankArray;

    function __construct(CompetitionLevels $level)
    {
        $this->level = $level->load('participantsAnswersUploaded');
        $this->computeParticipantAnswersScores();
        $this->participantsAnswersGrouped = ParticipantsAnswer::where('level_id', $level->id)
                ->select('*', DB::raw('SUM(score) AS points'))->groupBy('participant_index')
                ->orderBy('points', 'DESC')->get();

        $this->awards = $this->level->rounds->roundsAwards;
        $this->awardsRankArray = collect(['PERFECT SCORER'])
            ->merge($this->awards->pluck('name'))
            ->push($this->level->rounds->default_award_name);
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
        $this->setupCompetitionParticipantsResultsTable();
        $this->setParticipantsGroupRank();
        $this->setParticipantsCountryRank();
        $this->setParticipantsSchoolRank();
        $this->setParticipantsAwards();
        $this->setParticipantsAwardsRank();
        $this->setParticipantsGlobalRank();
        $this->level->updateStatus(CompetitionLevels::STATUS_FINISHED);
    }

    public function computeParticipantAnswersScores()
    {
        DB::transaction(function(){
            $this->level->participantsAnswersUploaded->each(function($participantAnswer){
                $participantAnswer->score = $participantAnswer->getAnswerMark();
                $participantAnswer->save();
            });
            $this->updateComputeProgressPercentage(20);
        });
    }

    public function setupCompetitionParticipantsResultsTable()
    {
        DB::transaction(function(){
            $attendeesIds = [];
            $this->participantsAnswersGrouped->each(function($participantAnswer) use(&$attendeesIds){
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
        $this->updateComputeProgressPercentage(60);
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
        $this->updateComputeProgressPercentage(70);
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
            $this->awards->each(function($award) use($group_id){
                $count =  CompetitionParticipantsResults::where('level_id', $this->level->id)
                    ->where('group_id', $group_id)->whereNull('award')->count();

                $limit = ceil(($award->percentage / 100) * $count);
                CompetitionParticipantsResults::where('level_id', $this->level->id)
                    ->where('group_id', $group_id)->whereNull('award')
                    ->orderBy('points', 'DESC')->limit($limit)
                    ->update([
                        'award'     => $award->name,
                        'ref_award' => $award->name
                    ]);
                $this->updateParticipantsWhoShareSamePointsAsLastParticipant($group_id, $award->name);
            });
        }

        // Set default award
        CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->whereNull('award')
            ->update([
                'award'     => $this->level->rounds->default_award_name,
                'ref_award' => $this->level->rounds->default_award_name
            ]);

        $this->updateComputeProgressPercentage(90);
    }

    private function updateParticipantsWhoShareSamePointsAsLastParticipant(int $group_id, string $awardName)
    {
        $lastParticipantPoints = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $group_id)->where('award', $awardName)
            ->orderBy('points')->value('points');

        CompetitionParticipantsResults::where('level_id', $this->level->id)->where('group_id', $group_id)
            ->where('points', $lastParticipantPoints)
            ->update([
                'award'     => $awardName,
                'ref_award' => $awardName
            ]);
    }

    public function setParticipantsAwardsRank()
    {
        $this->awardsRankArray->each(function($award, $key){
            CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->where('award', $award)
                ->update([
                    'award_rank' => $key+1
                ]);
        });
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