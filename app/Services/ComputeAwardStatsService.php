<?php

namespace App\Services;

use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use Illuminate\Validation\ValidationException;

class ComputeAwardStatsService
{
    protected $group;

    public function __construct(CompetitionMarkingGroup $group)
    {
        $this->group = $group->load(['competition:id,name', 'countries:id,display_name']);
    }

    protected function validateCompetition()
    {
        if (!$this->group->competition->isComputed()) {
            throw ValidationException::withMessages([
                'Not all levels have been computed, please compute all levels first'
            ])->status(406);
        }
    }

    public function getAwardsStats()
    {
        $this->validateCompetition();
        $headers = $this->getHeaders();
        $data = $this->getData();
        return [$headers, $data];
    }

    protected function getHeaders()
    {
        $headers = [
            'Competition' => $this->group->competition->name,
            'Marking Group' => $this->group->name,
            'Countries' => $this->group->countries->pluck('display_name')->join(', '),
        ];

        return $headers;
    }

    protected function getData()
    {
        $results = CompetitionParticipantsResults::where('group_id', $this->group->id)
            ->join('participants', 'participants.index_no', 'competition_participants_results.participant_index')
            ->select('competition_participants_results.award', 'competition_participants_results.points', 'participants.grade')
            ->orderBy('participants.grade')
            ->orderBy('competition_participants_results.points', 'desc')
            ->get();

        return [
            'totalParticipants' => $results->count(),
            'grades'    => $this->getGrades($results)
        ];
    }

    protected function getGrades($results)
    {
        $grades = $results->groupBy('grade')->map(function ($grade, $key) {
            $totalParticipants = $grade->count();
            return [
                'grade' => $key,
                'totalParticipants' => $totalParticipants,
                'awards' => $this->getAwardsPerGrade($grade, $totalParticipants),
            ];
        });

        return $grades->values();
    }

    protected function getAwardsPerGrade($grade, $totalParticipants)
    {
        $awards = $grade->groupBy('award')->map(function ($award, $key) use ($totalParticipants) {
            return [
                'award' => $key,
                'participantsCount' => $award->count(),
                'topPoints' => $award->max('points'),
                'leastPoints' => $award->min('points'),
                'awardPercentage' => round(($award->count() / $totalParticipants) * 100, 2),
            ];
        });

        return $awards;
    }
}
