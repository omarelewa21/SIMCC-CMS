<?php

namespace App\Services\Competition;

use App\Models\Competition;
use App\Models\Participants;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportListService
{
    protected Builder $baseQuery;

    function __construct(protected Competition $competition, protected Request $request)
    {
        $this->competition = $competition;
        if($this->request->missing('get_filter')) {
            $this->baseQuery = $this->getBaseQuery();
        }
    }

    protected function getBaseQuery(): Builder
    {
        return Participants::with('integrityCases')
        ->leftJoin('grades', 'participants.grade', 'grades.id')
        ->leftJoin('competition_participants_results', 'participants.index_no', 'competition_participants_results.participant_index')
        ->leftJoin('competition_levels', 'competition_levels.id', 'competition_participants_results.level_id')
        ->leftJoin('schools', 'participants.school_id', 'schools.id')
        ->leftJoin('schools AS tuition_school', 'participants.tuition_centre_id', 'tuition_school.id')
        ->leftJoin('all_countries', 'all_countries.id', 'participants.country_id')
        ->leftJoin('competition_organization', 'participants.competition_organization_id', 'competition_organization.id')
        ->leftJoin('competition', 'competition.id', 'competition_organization.competition_id')
        ->leftJoin('organization', 'organization.id', 'competition_organization.organization_id')
        ->where('competition.id', $this->competition->id)
        ->groupBy('participants.index_no')
        ->orderByRaw(
            "competition_levels.id,
            FIELD(competition_participants_results.award, {$this->getAwardRanks()}),
            competition_participants_results.points desc"
        );
    }

    protected function getAwardRanks(): string
    {
        $round = $this->competition->rounds->first();
        $awardsRankArray = collect(['PERFECT SCORE'])
            ->merge($round->roundsAwards->pluck('name'))
            ->push($round->default_award_name);

        return "'{$awardsRankArray->implode("','")}'";
    }

    public function getReportCSVData()
    {
        $csvQuery = $this->baseQuery->selectRaw($this->getCSVSelectQuery());
        $data = $this->applyFilterToReport($csvQuery)->get();
        throw_if($data->isEmpty(), new \Exception('No data found'));
        return $data->prepend($this->getCSVHeader());
    }

    protected function getCSVSelectQuery(): string
    {
        return "CONCAT('\"',competition.name,'\"') as competition,
            CONCAT('\"',organization.name,'\"') as organization,
            CONCAT('\"',all_countries.display_name,'\"') as country,
            CONCAT('\"',competition_levels.name,'\"') as level,
            competition_levels.id as level_id,
            grades.display_name as grade,
            participants.country_id,
            participants.school_id,
            participants.status,
            CONCAT('\"',COALESCE(schools.name_in_certificate, schools.name),'\"') as `school`,
            CONCAT('\"',tuition_school.name,'\"') as tuition_centre,
            participants.index_no,
            CONCAT('\"',participants.name,'\"') as name,
            participants.certificate_no,
            competition_participants_results.points,
            CONCAT('\"',competition_participants_results.award,'\"') as award,
            CONCAT('\"',competition_participants_results.award, ' ', competition_participants_results.school_rank, '\"') as school_rank,
            CONCAT('\"',competition_participants_results.award, ' ', competition_participants_results.country_rank, '\"') as country_rank,
            CONCAT('\"',competition_participants_results.global_rank,'\"') as global_rank";
    }

    public function applyFilterToReport(Builder $query): Builder
    {
        if (count($this->request->all()) === 0) return $query;

        if ($this->request->filled('grade')) $query->where('participants.grade', $this->request->grade);
        if ($this->request->filled('country')) $query->where('participants.country_id', $this->request->country);
        if ($this->request->filled('award')) $query->where('competition_participants_results.award', $this->request->award);
        if ($this->request->filled('status')) $query->where('participants.status', $this->request->status);
        if($this->request->filled('search')) {
            $query->where(function($query) {
                $query->where('participants.name', 'like', "%{$this->request->search}%")
                    ->orWhere('participants.index_no', 'like', "%{$this->request->search}%")
                    ->orWhere('schools.name', 'like', "%{$this->request->search}%")
                    ->orWhere('organization.name', 'like', "%{$this->request->search}%");
            });
        }

        return $query;
    }

    protected function getCSVHeader(): array
    {
        return [
            'participant', 'index', 'certificate number', 'status', 'competition', 'organization', 'country',
                'level', 'grade', 'school', 'tuition', 'points', 'award', 'school_rank', 'country_rank', 'global rank'
        ];
    }

    public function getReportListOrFilter()
    {
        if($this->request->filled('get_filter')) {
            return $this->getFilterOptions();
        }
        $listQuery = $this->baseQuery->selectRaw($this->getListSelectQuery());
        return $this->applyFilterToReport($listQuery)->paginate($this->request->limits ?? defaultLimit());
    }

    protected function getListSelectQuery(): string
    {
        return "competition.name as competition,
            organization.name as organization,
            all_countries.display_name as country,
            competition_levels.name as level,
            competition_levels.id as level_id,
            grades.display_name as grade,
            participants.country_id,
            participants.school_id,
            participants.status,
            CONCAT(COALESCE(schools.name_in_certificate, schools.name)) as `school`,
            tuition_school.name as tuition_centre,
            participants.index_no,
            competition_participants_results.participant_index,
            participants.name as name,
            participants.certificate_no,
            competition_participants_results.points,
            competition_participants_results.award as award,
            CONCAT(competition_participants_results.award, ' ', competition_participants_results.school_rank) as school_rank,
            CONCAT(competition_participants_results.award, ' ', competition_participants_results.country_rank) as country_rank,
            competition_participants_results.global_rank";
    }

    public function getFilterOptions()
    {
        if(method_exists($this, sprintf("get%sFilterOptions", Str::studly($this->request->get('get_filter'))))) {
            return $this->{sprintf("get%sFilterOptions", Str::studly($this->request->get('get_filter')))}();
        }
        return [];
    }

    protected function returnFilterOptions($query)
    {
        return $this->applyFilterToReport(
            $query
            ->leftJoin('competition_participants_results', 'participants.index_no', 'competition_participants_results.participant_index')
            ->leftJoin('grades', 'participants.grade', 'grades.id')
            ->leftJoin('all_countries', 'participants.country_id', 'all_countries.id')
            ->leftJoin('schools', 'participants.school_id', 'schools.id')
            ->leftJoin('competition_organization', 'participants.competition_organization_id', 'competition_organization.id')
            ->leftJoin('organization', 'organization.id', 'competition_organization.organization_id')
        )->get()
        ->map(fn($item) => $item->setAppends([]));
    }

    public function getAwardFilterOptions()
    {
        return $this->returnFilterOptions(
            Participants::whereRelation('competition_organization', 'competition_id', $this->competition->id)
            ->whereNotNull('competition_participants_results.award')
            ->select('competition_participants_results.award as filter_name', 'competition_participants_results.award as filter_id')
            ->distinct('competition_participants_results.award')
            ->orderByRaw("FIELD(competition_participants_results.award, {$this->getAwardRanks()})")
        );
    }

    public function getGradeFilterOptions()
    {
        return $this->returnFilterOptions(
            Participants::whereRelation('competition_organization', 'competition_id', $this->competition->id)
            ->select('grades.display_name as filter_name', 'participants.grade as filter_id')
            ->distinct('grades.display_name')
            ->orderBy('participants.grade')
        );
    }

    public function getCountryFilterOptions()
    {
        return $this->returnFilterOptions(
            Participants::whereRelation('competition_organization', 'competition_id', $this->competition->id)
            ->select('all_countries.display_name as filter_name', 'participants.country_id as filter_id')
            ->distinct('all_countries.display_name')
            ->orderBy('all_countries.display_name')
        );
    }

    public function getStatusFilterOptions()
    {
        return $this->returnFilterOptions(
            Participants::whereRelation('competition_organization', 'competition_id', $this->competition->id)
            ->select('participants.status as filter_name', 'participants.status as filter_id')
            ->distinct('participants.status')
        );
    }
}
