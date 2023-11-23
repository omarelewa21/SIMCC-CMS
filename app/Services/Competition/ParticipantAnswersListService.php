<?php

namespace App\Services\Competition;

use App\Http\Requests\getParticipantListRequest;
use App\Models\Competition;
use App\Services\GradeService;

class ParticipantAnswersListService
{
    public function __construct(
        private Competition $competition,
        private getParticipantListRequest $request
    ){}

    public function getFilterOptions()
    {
        $availableStatusses = $this->getAvailableStatusses();
        $availableGrades = GradeService::getAvailableCorrespondingGradesFromList($this->getAvailableGrades());
        $availableCountries = $this->getAvailableCountries();
        return [
            'status' => $availableStatusses,
            'grade' => $availableGrades,
            'country' => $availableCountries
        ];
    }

    private function getParticipantsQuery()
    {
        return $this->competition->participants()
            ->leftJoin('schools', 'schools.id', '=', 'participants.school_id')
            ->leftJoin('schools as tuition_centre', 'tuition_centre.id', '=', 'participants.tuition_centre_id')
            ->leftJoin('all_countries', 'all_countries.id', '=', 'participants.country_id')
            ->filterList($this->request)
            ->with('answers', 'school', 'country');
    }

    private function getAvailableStatusses(): array
    {
        return (clone $this->getParticipantsQuery())
            ->select('participants.status')
            ->distinct()
            ->get()
            ->pluck('status')
            ->toArray();
    }

    private function getAvailableGrades(): array
    {
        return (clone $this->getParticipantsQuery())
            ->select('participants.grade')
            ->orderBy('participants.grade')
            ->distinct()
            ->get()
            ->pluck('grade')
            ->toArray();
    }

    private function getAvailableCountries(): array
    {
        return (clone $this->getParticipantsQuery())
            ->select('participants.country_id', 'all_countries.display_name as country_name')
            ->distinct('participants.country_id')
            ->get()
            ->map(fn($country) => [
                'id' => $country->country_id,
                'name' => $country->country_name
            ])
            ->toArray();
    }

    public function getList()
    {
        return (clone $this->getParticipantsQuery())
            ->paginate($this->request->limits ?? 10);
    }
}
