<?php

namespace App\Services\Participant;

use App\Abstracts\GetList;
use App\Models\CompetitionOrganization;
use App\Models\Participants;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ParticipantsListService extends GetList
{
    public User $user;

    function __construct(protected Request $request)
    {
        parent::__construct($request);
        $this->user = auth()->user();
    }

    protected function getModel(): string
    {
        return Participants::class;
    }

    protected function returnTableData(): LengthAwarePaginator
    {
        return $this->getRespectiveUserModelQuery()
            ->with($this->getWithRelations())
            ->withExists('answers as is_answers_uploaded')
            ->filter($this->request)
            ->when($this->request->private,
                fn($query) => $query->whereNotNull('participants.tuition_centre_id'),
                fn($query) => $this->request->has('private') ? $query->whereNull('participants.tuition_centre_id') : $query
            )
            ->search($this->request->search ?? '')
            ->orderBy("{$this->getTable()}.updated_at", 'desc')
            ->paginate($this->request->limits ?? defaultLimit());
    }

    protected function getRespectiveUserModelQuery(): Builder
    {
        if($this->user->hasRole(['country partner', 'country partner assistant']))
            return $this->getQueryForCP();

        if($this->user->hasRole(['school manager', 'teacher']))
            return $this->getQueryForTeacher();

        return Participants::query();
    }

    protected function getWithRelations(): array
    {
        return [
            'country:id,display_name as name',
            'school:id,name',
            'tuition_centre:id,name',
            'competition_organization:id,competition_id,organization_id' => [
                'competition:id,alias,name',
                'organization:id,name'
            ],
            'result:participant_index,award',
            'participantGrade:id,display_name as name',
        ];
    }

    private function getQueryForCP(): Builder
    {
        $allowedOrganizationIds = CompetitionOrganization::where('organization_id', $this->user->organization_id)->pluck('id');
        return Participants::where('participants.country_id', $this->user->country_id)
            ->whereIn("participants.competition_organization_id", $allowedOrganizationIds);
    }

    private function getQueryForTeacher(): Builder
    {
        $allowedOrganizationIds = CompetitionOrganization::where([
            'country_id'        => $this->user->country_id,
            'organization_id'   => $this->user->organization_id
        ])->pluck('id')->toArray();

        return Participants::whereIn("competition_organization_id", $allowedOrganizationIds)
            ->where("tuition_centre_id", $this->user->school_id)
            ->orWhere("schools.id", $this->user->school_id);
    }

    protected function filterables(): Collection
    {
        return collect(array_merge($this->getInstance()->filterable, ['private' => 'private']))
            ->except('id')
            ->map(fn($value, $key) => fn() => $this->{Str::camel("get_$key")}());
    }

    protected function getPrivate(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->selectRaw("
            CASE WHEN tuition_centre_id IS NOT NULL THEN 1 ELSE 0 END as filter_id,
            CASE WHEN tuition_centre_id IS NOT NULL THEN 'Private' ELSE 'School' END as filter_name
        ");
    }

    protected function getOrganization(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->join('organization', 'organization.id', '=', 'competition_organization.organization_id')
            ->select('organization.id as filter_id', 'organization.name as filter_name');
    }

    protected function getCompetition(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->join('competition', 'competition.id', '=', 'competition_organization.competition_id')
            ->select('competition.id as filter_id', 'competition.name as filter_name');
    }

    protected function getCountry(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('all_countries', 'all_countries.id', '=', 'participants.country_id')
            ->select('all_countries.id as filter_id', 'all_countries.display_name as filter_name');
    }

    protected function getSchool(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('schools', 'schools.id', '=', 'participants.school_id')
            ->select('schools.id as filter_id', 'schools.name as filter_name');
    }

    protected function getGrade(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('grades', 'grades.id', '=', 'participants.grade')
            ->select('participants.grade as filter_id', 'grades.display_name as filter_name')
            ->orderBy('grades.id');
    }
}
