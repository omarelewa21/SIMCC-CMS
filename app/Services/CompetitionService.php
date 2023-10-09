<?php

namespace App\Services;

use App\Models\CompetitionOrganization;
use App\Models\CompetitionParticipantsResults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Models\Competition;

class CompetitionService
{
    protected Competition $competition;

    function __construct(Competition $competition)
    {
        $this->competition = $competition;
    }

    /**
     * get query for competition report
     * 
     * @param string $mode csv or all
     * 
     * @return \Illuminate\Database\Eloquent\Builder $query
     */
    public function getReportQuery(string $mode): Builder
    {
        $round = $this->competition->rounds->first();
        $awardsRankArray = collect(['PERFECT SCORE'])
                    ->merge($round->roundsAwards->pluck('name'))
                    ->push($round->default_award_name);

        return
            CompetitionParticipantsResults::leftJoin('competition_levels', 'competition_levels.id', 'competition_participants_results.level_id')
                ->leftJoin('competition_rounds', 'competition_levels.round_id', 'competition_rounds.id')
                ->leftJoin('competition', 'competition.id', 'competition_rounds.competition_id')
                ->leftJoin('participants', 'participants.index_no', 'competition_participants_results.participant_index')
                ->leftJoin('schools', 'participants.school_id', 'schools.id')
                ->leftJoin('schools AS tuition_school', 'participants.tuition_centre_id', 'tuition_school.id')
                ->leftJoin('all_countries', 'all_countries.id', 'participants.country_id')
                ->leftJoin('competition_organization', 'participants.competition_organization_id', 'competition_organization.id')
                ->leftJoin('organization', 'organization.id', 'competition_organization.organization_id')
                ->where('competition.id', $this->competition->id)
                ->when(
                    $mode === 'csv',
                    fn($query) => $this->getCompetitionReportQueryForCSV($query),
                    fn($query) => $this->getCompetitionReportQueryForAllMode($query)
                )
                ->orderByRaw(
                    "competition_levels.id,
                    FIELD(competition_participants_results.award, '". $awardsRankArray->implode("','") ."'),
                    competition_participants_results.points desc;"
                );
    }

    private function getCompetitionReportQueryForCSV(Builder $query): Builder
    {
        return $query->selectRaw(
            "CONCAT('\"',competition.name,'\"') as competition,
            CONCAT('\"',organization.name,'\"') as organization,
            CONCAT('\"',all_countries.display_name,'\"') as country,
            CONCAT('\"',competition_levels.name,'\"') as level,
            competition_levels.id as level_id,
            participants.grade,
            participants.country_id,
            participants.school_id,
            CONCAT('\"',schools.name,'\"') as school,
            CONCAT('\"',tuition_school.name,'\"') as tuition_centre,
            participants.index_no,
            CONCAT('\"',participants.name,'\"') as name,
            participants.certificate_no,
            competition_participants_results.points,
            CONCAT('\"',competition_participants_results.award,'\"') as award,
            competition_participants_results.school_rank,
            competition_participants_results.country_rank,
            CONCAT('\"',competition_participants_results.global_rank,'\"') as global_rank"
        );
    }

    private function getCompetitionReportQueryForAllMode(Builder $query): Builder
    {
        return $query->selectRaw(
            "competition.name as competition,
            organization.name as organization,
            all_countries.display_name as country,
            competition_levels.name as level,
            competition_levels.id as level_id,
            participants.grade,
            participants.country_id,
            participants.school_id,
            schools.name as school,
            tuition_school.name as tuition_centre,
            participants.index_no,
            participants.name as name,
            participants.certificate_no,
            competition_participants_results.points,
            competition_participants_results.award as award,
            competition_participants_results.school_rank,
            competition_participants_results.country_rank,
            competition_participants_results.global_rank"
        );
    }

    /**
     * apply filter to the query
     * 
     * @param \Illuminate\Http\Request $reques
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder $query
     */
    public function applyFilterToReport(Builder $query, Request $request): Builder
    {
        if($request->mode === 'csv' || count($request->all()) === 0) return $query;

        if($request->filled('grade')) $query->where('participants.grade', $request->grade);
        if($request->filled('country')) $query->where('participants.country_id', $request->country);
        if($request->filled('school')) $query->where('participants.school_id', $request->school);
        if($request->filled('award')) $query->where('competition_participants_results.award', $request->award);

        return $query;
    }

    public function setReportSchoolRanking(array $data, &$participants, &$currentLevel, &$currentSchool, &$currentPoints, &$counter)
    {
        collect($data)->sortBy([
            ['level_id', 'asc'],
            ['school', 'asc'],
            ['points', 'desc']
        ])->each(function ($row, $index) use(&$participants, &$currentLevel, &$currentSchool, &$currentPoints, &$counter){
            if($index == 0) {
                $currentLevel = $row['level_id'];
                $currentSchool = $row['school'];
                $currentPoints = $row['points'];
                $counter = 1;
            }

            if($currentPoints !== $row['points']) {
                $counter++;
                $currentPoints = $row['points'];
            }

            if($currentLevel !== $row['level_id'] || $currentSchool !== $row['school']){
                $currentLevel = $row['level_id'];
                $currentSchool = $row['school'];
                $counter = 1;
            }

            $participants[$row['index_no']] = [
                ...$row,
                'school_rank' => $counter
            ];
        });
    }

    public function setReportCountryRanking(&$participants, &$currentLevel, &$currentCountry, &$currentPoints, &$counter)
    {
        collect($participants)->sortBy([
            ['level_id','asc'],
            ['country','asc'],
            ['points','desc']
        ])->each(function ($row, $index) use(&$participants, &$currentLevel, &$currentCountry, &$currentPoints, &$counter){
            if($index == 0) {
                $currentLevel = $row['level_id'];
                $currentCountry = $row['country'];
                $currentPoints = $row['points'];
                $counter = 1;
            }

            if($currentPoints !== $row['points']) {
                $counter++;
                $currentPoints = $row['points'];
            }

            if($currentLevel !== $row['level_id'] || $currentCountry !== $row['country']){
                $currentLevel = $row['level_id'];
                $currentCountry = $row['country'];
                $counter = 1;
            }

            $participants[$row['index_no']] = [
                ...$row,
                'country_rank' => $counter
            ];

        });
    }

    public function setReportAwards($data, &$noAwards, &$awards, &$output, $header, &$participants, &$currentLevel, &$currentAward, &$currentPoints, &$globalRank, &$counter)
    {
        collect($data)->each(function ($row) use(&$noAwards, &$awards) { // seperate participant with/without award
            if($row['award'] !== 'NULL') {
                $awards[] = $row;
            } else {
                $noAwards[] = $row;
            }
        });

        collect($awards)->each(function ($fields, $index) use(&$output, $header, &$participants, &$currentLevel, &$currentAward, &$currentPoints, &$globalRank, &$counter) {

            if($index == 0) {
                $globalRank = 1;
                $counter = 1;
                $currentAward = $fields['award'];
                $currentPoints = $fields['points'];
                $currentLevel = $fields['level_id'];
            }

            if($currentLevel != $fields['level_id']){
                $globalRank = 1;
                $counter = 1;
            }

            if($currentAward === $fields['award'] && $currentPoints !== $fields['points']) {
                $globalRank = $counter;
                $currentPoints = $fields['points'];
            } elseif ($currentAward !== $fields['award'] ) {
                $currentAward = $fields['award'];
                $currentPoints = $fields['points'];
                $globalRank = 1;
                $counter = 1;
            }

            $currentLevel = $fields['level_id'];
            $participants[$fields['index_no']]['global_rank'] = $fields['award'] .' '.$globalRank;
            unset($participants[$fields['index_no']]['level_id']);
            $output[] = $participants[$fields['index_no']];
            $counter++;
        });

        if(isset($noAwards)) {
            foreach ($noAwards as $row) {
                unset($participants[$row['index_no']]['level_id']);
                $participants[$row['index_no']]['global_rank'] = '';
                $output[] = $participants[$row['index_no']];
            }
        }
    }

    

    /**
     * Get filter options for report data
     * 
     * @param array $data
     * @return \Illuminate\Support\Collection
     */
    public function getReportFilterOptions(array $data): array
    {
        $collection = collect($data);
        $grades = $collection->pluck('grade')->unique()->values();
        $countries = $collection->pluck('country', 'country_id')->unique();
        $schools = $collection->pluck('school', 'school_id')->unique();
        $awards = $collection->pluck('award')->unique()->values();

        return [
            'grade'     => $grades,
            'country'   => $countries,
            'school'    => $schools,
            'award'     => $awards
        ];
    }

    public static function addOrganizations(array $organizations, int $competition_id)
    {
        foreach($organizations as $organization){
            if(CompetitionOrganization::where('competition_id', $competition_id)->where('organization_id', $organization['organization_id'])->where('country_id', $organization['country_id'])->doesntExist()){
                CompetitionOrganization::create(
                    array_merge($organization, [
                        'competition_id'    => $competition_id,
                        'created_by_userid' => auth()->user()->id,
                ]));
            }
        }
    }
}
