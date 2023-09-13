<?php

namespace App\Helpers;

use App\Models\CompetitionLevels;
use App\Models\CompetitionParticipantsResults;
use App\Models\CompetitionRounds;
use App\Models\CompetitionRoundsAwards;

class SetParticipantsAwardsHelper
{
    private $awards;

    function __construct(public CompetitionLevels $level)
    {
        $this->awards = $level->rounds->roundsAwards;
    }

    public function setParticipantsAwards()
    {
        $this->level->rounds->award_type === "Position"
            ? $this->setParticipantsAwardsByPosition()
            : $this->setParticipantsAwardsByPercentage();
    }

    private function setParticipantsAwardsByPosition()
    {
        $groupIds = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->select('group_id')->distinct()->pluck('group_id')->toArray();

        foreach($groupIds as $groupId){
            [$totalCount, $perfectScoreresCount] = $this->getTotalCountAndPerfectScoreresCount($groupId);
            $count = $totalCount;

            $this->awards->each(function($award) use($groupId, &$count, $totalCount, $perfectScoreresCount){
                $participantResult = CompetitionParticipantsResults::where('level_id', $this->level->id)
                    ->where('group_id', $groupId)
                    ->whereNull('award')
                    ->where('points', '>=', $award->min_marks)
                    ->orderBy('points', 'DESC')
                    ->limit(1)
                    ->first();
                
                if(!$participantResult) return;

                $participantResult->update([
                        'award'     => $award->name,
                        'ref_award' => $award->name,
                        'percentile'=> $this->calculatePostionPercentile($count, $totalCount, $perfectScoreresCount),
                    ]);

                $count--;
            });

            $this->setDefaultAward($groupId, $totalCount, $count, $perfectScoreresCount);
        }
    }

    private function setParticipantsAwardsByPercentage()
    {
        $groupIds = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->select('group_id')->distinct()->pluck('group_id')->toArray();
        
        foreach($groupIds as $groupId){
            [$totalCount, $perfectScoreresCount] = $this->getTotalCountAndPerfectScoreresCount($groupId);
            
            $awardPercentage = 0;
            $count = $totalCount;

            $this->awards->each(function($award, $key) use($groupId, $totalCount, &$count, &$awardPercentage, $perfectScoreresCount){
                $awardPercentage += $award->percentage;
                $percentileCutoff = 100 - $awardPercentage;

                for($count; $count > 0; $count--) {
                    $positionPercentile = number_format(($count / $totalCount) * 100, 2, '.', '');

                    if($positionPercentile >= $percentileCutoff) {
                        $participantResult = $this->getParticipantResult($groupId);
                        if(!$participantResult) break;

                        $participantDeservedAward = $this->getParticipantDeservedAward($participantResult, $award, $key);
                        $participantResult->update([
                                'award'     => $participantDeservedAward,
                                'ref_award' => $participantDeservedAward,
                                'percentile'=> $this->calculatePostionPercentile($count, $totalCount, $perfectScoreresCount),
                            ]);

                    } else {
                        $updatedCount = $this->updateParticipantsWhoShareSamePointsAsLastParticipant($groupId, $award->name, $totalCount, $count, $perfectScoreresCount);
                        $count = $updatedCount;
                        break;
                    }
                }
            });

            $this->setDefaultAward($groupId, $totalCount, $count, $perfectScoreresCount);
        }
    }

    private function getTotalCountAndPerfectScoreresCount(int $groupId): array
    {
        $totalCount = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $groupId)->count();

        $perfectScoreresCount = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $groupId)
            ->where('award', 'PERFECT SCORE')
            ->count();

        return [$totalCount, $perfectScoreresCount];
    }

    private function getParticipantResult(int $groupId): CompetitionParticipantsResults|null
    {
        return CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $groupId)
            ->whereNull('award')
            ->orderBy('points', 'DESC')
            ->limit(1)
            ->first();
    }

    private function getParticipantDeservedAward(CompetitionParticipantsResults $participantResult, CompetitionRoundsAwards $award, int $key): string
    {
        if($participantResult->points >= $award->min_marks) return $award->name;

        if($this->awards->has($key + 1)) {
            $nextAward = $this->awards->get($key + 1);
            return $this->getParticipantDeservedAward($participantResult, $nextAward, $key + 1);
        }

        return $this->level->rounds->default_award_name;
    }

    private function setDefaultAward(int $groupId, int $totalCount, int $count, int $perfectScoreresCount)
    {
        for($count; $count > 0; $count--) {
            CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->where('group_id', $groupId)
                ->whereNull('award')
                ->orderBy('points', 'DESC')
                ->limit(1)
                ->update([
                    'award'     => $this->level->rounds->default_award_name,
                    'ref_award' => $this->level->rounds->default_award_name,
                    'percentile'=> $this->calculatePostionPercentile($count, $totalCount, $perfectScoreresCount),
                ]);
        }
    }

    private function updateParticipantsWhoShareSamePointsAsLastParticipant(int $groupId, string $awardName, int $totalCount, int $currentCount, int $perfectScoreresCount): int
    {
        $lastParticipantPoints = CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $groupId)
            ->where('award', $awardName)
            ->orderBy('points')->value('points');

        $competitionParticipantsQuery =  CompetitionParticipantsResults::where('level_id', $this->level->id)->where('group_id', $groupId)
            ->where('points', $lastParticipantPoints)
            ->whereNull('award');

        $competitionParticipantsQuery->get()
            ->each(function ($row) use($totalCount, &$currentCount, $awardName, $perfectScoreresCount ) {
                CompetitionParticipantsResults::find($row->id)->update([
                    'award'     => $awardName,
                    'ref_award' => $awardName,
                    'percentile' => $this->calculatePostionPercentile($currentCount, $totalCount, $perfectScoreresCount),
                ]);
                $currentCount--;
            });

        return $currentCount;
    }

    private function calculatePostionPercentile(int $count, int $totalCount, int $perfectScoreresCount): string
    {
        $percentile = $count / ($totalCount + $perfectScoreresCount);
        return number_format($percentile * 100, 2, '.', '');
    }
}