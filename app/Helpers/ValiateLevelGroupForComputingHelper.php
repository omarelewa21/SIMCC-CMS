<?php

namespace App\Helpers;

use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use App\Models\LevelGroupCompute;
use App\Models\ParticipantsAnswer;
use App\Services\ComputeLevelGroupService;
use App\Services\MarkingService;

class ValiateLevelGroupForComputingHelper
{
    function __construct(private CompetitionLevels $level, private CompetitionMarkingGroup $group)
    {
    }

    public function validate(bool $throwException = true)
    {
        foreach ($this->conditions() as $index => $condition) {
            if ($condition()) {
                throw_if($throwException, new \Exception($this->getMessage($index), 400));
                return false;
            }
        }

        return true;
    }

    private function conditions(): array
    {
        return array(
            fn() => $this->group->levelGroupCompute($this->level->id)->value('computing_status') === LevelGroupCompute::STATUS_IN_PROGRESS,
            fn() => !MarkingService::isLevelReadyToCompute($this->level),
            fn() => $this->someAnswersIsNotComputed(),
            fn() => $this->awardShouldBeIncludedInRequest(),
            fn() => $this->awardIsNullWhileComputingGlobalRanking(),
            fn() => $this->awardAndGlobalRankWillBeComputedTogether(),
            fn() => $this->awardsNotFullyModeratedToComputeGlobalRanking()
        );
    }

    private function getMessage($index)
    {
        return array(
            "Grades {$this->level->name} is already under computing for this group {$this->group->name}, please wait till finished",
            "Level {$this->level->name} is not ready to compute, please check that all tasks in this level has answers and student answers are uploaded to this level",
            "Some of the answers have not been computed yet for this level {$this->level->name} and group {$this->group->name}, please select re-mark option to remark them",
            "Some of the awards have not been computed yet for this level {$this->level->name} and group {$this->group->name}, please select award option to compute award first",
            "Award is not computed for some of the countries inside this grade, you shall compute award for all countries inside this grade first",
            "Award and Global Ranking will be computed together, please compute and moderate awards first",
            "Awards are not fully moderated for this level {$this->level->name}, please moderate all awards for all group of countries first"
        )[$index];
    }

    private function someAnswersIsNotComputed(): bool
    {
        return request()->has('not_to_compute')
            && is_array(request('not_to_compute'))
            && in_array('remark', request('not_to_compute'))
            && !ComputeLevelGroupService::firstTimeCompute($this->level, $this->group)
            && $this->checkIfAnyAnswerHasANullScore();
    }

    private function checkIfAnyAnswerHasANullScore(): bool
    {
        return ParticipantsAnswer::where('level_id', $this->level->id)
            ->whereHas('participant', function($query) {
                $query->whereIn('country_id', $this->group->countries()->pluck('id')->toArray());
            })
            ->whereNull('score')
            ->exists();
    }

    private function awardShouldBeIncludedInRequest(): bool
    {
        return in_array('award', request('not_to_compute'))
            && $this->isRankingIncludedInRequest()
            && $this->checkIfAwardIsNotSet();
    }

    private function isRankingIncludedInRequest(): bool
    {
        
        return count(
            array_intersect(request('not_to_compute'), ['country_rank', 'school_rank', 'global_rank'])
        ) < 3;
    }

    private function checkIfAwardIsNotSet(): bool
    {
        if(request('clear_previous_results')) return false;

        return CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', $this->group->id)
            ->whereNull('award')
            ->exists();
    }

    private function awardIsNullWhileComputingGlobalRanking()
    {
        if(in_array('global_rank', request('not_to_compute'))) return false;

        if($this->checkIfNewStudentsAdded()) return true;

        if(in_array('award', request('not_to_compute'))) {
            // award will not be computed
            return CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->whereNull('award')
                ->exists();
        };

        // award will computed for this level and group, need to check for other groups
        return CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->where('group_id', '<>', $this->group->id)
            ->whereNull('award')
            ->exists();
    }

    private function checkIfNewStudentsAdded()
    {
        return ParticipantsAnswer::where('level_id', $this->level->id)
            ->whereNull('score')
            ->exists();
    }

    private function awardAndGlobalRankWillBeComputedTogether()
    {
        return !in_array('award', request('not_to_compute'))
            && !in_array('global_rank', request('not_to_compute'));
    }

    private function awardsNotFullyModeratedToComputeGlobalRanking()
    {
        if(in_array('global_rank', request('not_to_compute'))) return false;

        return LevelGroupCompute::where('level_id', $this->level->id)
            ->where('awards_moderated', false)
            ->exists();
    }
}