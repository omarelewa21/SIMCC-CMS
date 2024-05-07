<?php

namespace App\Helpers;

use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use App\Models\IntegrityCheckCompetitionCountries;
use App\Models\LevelGroupCompute;
use App\Models\ParticipantsAnswer;
use App\Services\ComputeLevelGroupService;
use App\Services\MarkingService;
use App\Traits\ComputeOptions;

class ValiateLevelGroupForComputingHelper
{
    use ComputeOptions;

    private $levelGroupCompute;

    function __construct(private CompetitionLevels $level, private CompetitionMarkingGroup $group)
    {
        $this->levelGroupCompute = $group->levelGroupCompute($level->id)->first();
        $this->setRequestComputeOptions();
    }

    public function validate(bool $throwException = true)
    {
        foreach ($this->conditions() as $condition) {
            if ($condition['validate']()) {
                throw_if($throwException, new \Exception($condition['message'], 400));
                return false;
            }
        }

        return true;
    }

    private function conditions(): array
    {
        return array(
            [
                'validate' => fn() => $this->levelGroupCompute?->computing_status === LevelGroupCompute::STATUS_IN_PROGRESS,
                'message'  => "Grades {$this->level->name} is already under computing for this group {$this->group->name}, please wait till finished"
            ],
            [
                'validate' => fn() => !MarkingService::isLevelReadyToCompute($this->level),
                'message'  => "Level {$this->level->name} is not ready to compute, please check that all tasks in this level has marks the round has awards registered"
            ],
            [
                'validate' => fn() => $this->globalRankDoneAlready(),
                'message'  => "Global ranking is already computed, You can't do further changes to results anymore"
            ],
            [
                'validate' => fn() => MarkingService::noAnswersUploadedForLevelAndGroup($this->level, $this->group),
                'message'  => "There is no answers uploaded for this level and group, please upload answers first"
            ],
            [
                'validate' => fn() => ! $this->integrityCheckConducted(),
                'message'  => "Integrity check is not confirmed for this level {$this->level->name} and group {$this->group->name}, please confirm integrity check first"
            ],
            [
                'validate' => fn() => $this->someAnswersIsNotComputed(),
                'message'  => "Some of the answers have not been computed yet for this level {$this->level->name} and group {$this->group->name}, please select re-mark option to remark them"
            ],
            [
                'validate' => fn() => $this->tryingToRemarkWhileAwardsModerated(),
                'message'  => "You are trying to re-mark answers while award moderation is completed, please set moderation to in progress first"
            ],
            [
                'validate' => fn() => $this->tryingToComputeAwardWhileAwardsModerated(),
                'message'  => "You are trying to marks awards while award moderation is completed, please set moderation to in progress first"
            ],
            [
                'validate' => fn() => $this->willCompute('remark') && $this->willComputeRanking(),
                'message'  => "You can't re-mark answers and compute ranking at the same time, you must remark, mark awards, moderate awards and compute ranking in order"
            ],
            [
                'validate' => fn() => $this->awardAndRankingWillBeComputedTogether(),
                'message'  => "Award and Ranking will be computed together, please compute award and moderate it first before computing ranking"
            ],
            [
                'validate' => fn() => $this->tryingToComputeRankingWhileAwardsNotModerated(),
                'message'  => "You are trying to compute ranking while award moderation is not completed, please set moderation to completed first"
            ],
            [
                'validate' => fn() => $this->awardShouldBeConductedFirst(),
                'message'  => "Some of the awards have not been computed yet for this level {$this->level->name} and group {$this->group->name}, please compute award first before computing ranking"
            ],
            [
                'validate' => fn() => $this->shouldRunOtherRankingBeforeGlobalRanking(),
                'message'  => "You are computing global ranking while some of the school or country ranking have not been computed yet for this level, please ensure that school and country ranking have been computed first"
            ],
            [
                'validate' => fn() => $this->computeGlobalRankWhileSomeAnswersNotComputedInOtherGroups(),
                'message'  => "You are computing global ranking while some of the students in other groups in this level {$this->level->name} have not been marked yet, please ensure that all groups in this level have been marked first"
            ],
            [
                'validate' => fn() => $this->computeGlobalRankWhileSomeAwardsNotComputedInOtherGroups(),
                'message'  => "Award is not computed for some of the groups inside this level {$this->level->name}, you shall compute award for all groups inside this level first"
            ],
            [
                'validate' => fn() => $this->awardsNotFullyModeratedToComputeGlobalRanking(),
                'message'  => "Awards are not fully moderated for this level {$this->level->name}, please moderate all awards for all group of countries first"
            ]
        );
    }

    private function someAnswersIsNotComputed(): bool
    {
        return $this->willNotCompute('remark')
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

    private function awardShouldBeConductedFirst(): bool
    {
        return $this->willNotCompute('award')
            && $this->willComputeRanking()
            && $this->checkIfAwardIsNotSet();
    }

    private function willComputeRanking(): bool
    {
        return $this->willComputeAny(['country_rank', 'school_rank', 'global_rank']);
    }

    private function checkIfAwardIsNotSet(): bool
    {
        return CompetitionParticipantsResults
            ::filterByLevelAndGroup($this->level->id, $this->group->id)
            ->whereNull('award')
            ->exists();
    }

    private function computeGlobalRankWhileSomeAwardsNotComputedInOtherGroups()
    {
        if($this->willNotCompute('global_rank')) return false;

        return CompetitionParticipantsResults::where('level_id', $this->level->id)
                ->whereNull('award')
                ->exists();
    }

    private function awardAndRankingWillBeComputedTogether()
    {
        return $this->willCompute('award') && $this->willComputeRanking();
    }

    private function awardsNotFullyModeratedToComputeGlobalRanking()
    {
        if($this->willNotCompute('global_rank')) return false;

        return LevelGroupCompute::where('level_id', $this->level->id)
            ->where('awards_moderated', false)
            ->exists();
    }

    private function computeGlobalRankWhileSomeAnswersNotComputedInOtherGroups()
    {
        if($this->willNotCompute('global_rank')) return false;

        return ParticipantsAnswer::where('level_id', $this->level->id)
            ->whereHas('participant', function($query) {
                $query->whereNotIn('country_id', $this->group->countries()->pluck('id')->toArray());
            })
            ->whereNull('score')
            ->exists();
    }

    private function integrityCheckConducted()
    {
        $competitionId = $this->level->load('rounds.competition:id')->rounds->competition->id;
        $countryIds = $this->group->countries()->pluck('id')->toArray();
        $integrityChecks = IntegrityCheckCompetitionCountries::where('competition_id', $competitionId)
            ->whereIn('country_id', $countryIds)
            ->get();

        return $integrityChecks->count() === count($countryIds)
            && $integrityChecks->every(fn($check) => $check->is_confirmed );
    }

    private function shouldRunOtherRankingBeforeGlobalRanking()
    {
        if($this->willNotCompute('global_rank')) return false;
        if($this->willComputeAll(['country_rank', 'school_rank'])) return false;

        return CompetitionParticipantsResults::where('level_id', $this->level->id)
            ->whereRelation('participant', 'status', 'result computed')
            ->where(fn($query) => $query->whereNull('country_rank')->orWhereNull('school_rank'))
            ->exists();
    }

    private function globalRankDoneAlready()
    {
        return CompetitionParticipantsResults
            ::filterByLevelAndGroup($this->level->id, $this->group->id)
            ->whereNotNull('global_rank')
            ->exists();
    }

    private function tryingToRemarkWhileAwardsModerated()
    {
        return $this->willCompute('remark') && $this->levelGroupCompute?->awards_moderated;
    }

    private function tryingToComputeAwardWhileAwardsModerated()
    {
        return $this->willCompute('award') && $this->levelGroupCompute?->awards_moderated;
    }

    private function tryingToComputeRankingWhileAwardsNotModerated()
    {
        return $this->willComputeRanking() && !$this->levelGroupCompute?->awards_moderated;
    }
}
