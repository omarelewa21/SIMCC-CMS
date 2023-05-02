<?php

namespace App\Services;

use App\Models\CompetitionOrganization;
use App\Models\CompetitionParticipantsResults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Custom\Marking;
use App\Exports\CheatersExport;
use App\Http\Requests\Competition\CompetitionCheatingListRequest;
use App\Models\CheatingParticipants;
use App\Models\CheatingStatus;
use App\Models\Competition;
use App\Models\Participants;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

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
                ->when($mode === 'csv', function($query){
                    $query->selectRaw(
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
                        CONCAT('\"',competition_participants_results.award,'\"') as award"
                    );
                })->when($mode === 'all', function($query){
                    $query->selectRaw(
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
                        competition_participants_results.country_rank"
                    );
                })->orderByRaw(
                    "`competition_levels`.`id`,
                    FIELD(`competition_participants_results`.`award`,'PERFECT SCORER','GOLD','SILVER','BRONZE','HONORABLE MENTION','Participation'),
                    `competition_participants_results`.`points` desc;"
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
     * Validate if competition is ready to compute
     * 
     * @param Competition $competition
     */
    public static function validateIfCanGenerateCheatingPage(Competition $competition)
    {
        $competition->rounds()->with('levels')->get()
            ->pluck('levels')->flatten()
            ->each(function($level){
                if(Marking::isLevelReadyToCompute($level) === false) {
                    throw new \Exception(
                        sprintf("Level %s is not ready to compute. Check that all tasks has correct answers, round has awards and answers are uploaded to that level", $level->name),
                        400
                    );
                }
            });
    }

    /**
     * get cheat status and data
     * 
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     * @return Illuminate\Http\JsonResponse
     */
    public static function returnCheatStatusAndData(Competition $competition, CompetitionCheatingListRequest $request)
    {
        $cheatingStatus = CheatingStatus::findOrFail($competition->id);

        if($cheatingStatus->status === 'In Progress') {
            return response()->json([
                'status'    => 206,
                'message'   => 'Generating cheating list is in progress',
                'cheating_percentage' => $cheatingStatus->progress_percentage
            ], 206);
        }

        if($cheatingStatus->status === 'Failed') {
            return response()->json([
                'status'    => 500,
                'message'   => sprintf("Generating cheating list failed at perentage %s with error: %s", $cheatingStatus->progress_percentage, $cheatingStatus->compute_error_message)
            ], 500);
        }

        if($cheatingStatus->status === 'Completed') {
            if($request->csv == 1) return static::getCheatingCSVFile($competition);

            $cheaters = static::getCheatingList($competition)
                ->filterByRequest(
                    $request,
                    array("country", "school", "grade", "cheating_percentage", "group_id"),
                    array('participants', 'participants', 'school', 'country')
                );

            $filterOptions = static::getFilterOptions($cheaters);

            return response()->json([
                'status'    => 200,
                'message'   => 'Cheating list generated successfully',
                'filter_options' => $filterOptions,
                'Cheaters'  => $cheaters->paginate($request->limits ?? 10, $request->page ?? 1)
            ], 200);
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

    /**
     * Get cheating list
     * 
     * @param Competition $competition
     * @return Illuminate\Support\Collection
     */
    public static function getCheatingList(Competition $competition)
    {
        return Participants::distinct()
            ->join('cheating_participants', function (JoinClause $join) {
                $join->on('participants.index_no', 'cheating_participants.participant_index')
                    ->orOn('participants.index_no', 'cheating_participants.cheating_with_participant_index');
            })
            ->where('cheating_participants.competition_id', $competition->id)
            ->select(
                'participants.index_no', 'participants.name', 'participants.school_id', 
                'participants.country_id', 'participants.grade', 'cheating_participants.group_id',
                'cheating_participants.number_of_cheating_questions', 'cheating_participants.cheating_percentage'
            )
            ->with('school', 'country')
            ->withCount('answers')
            ->get()
            ->groupBy('group_id')
            ->map(function($group, $group_id){
                $firstRecordParticipant = $group->first();
                $cheatersGroupData['number_of_questions'] = $firstRecordParticipant->answers_count;
                $cheatersGroupData['cheating_percentage'] = round($group->avg('cheating_percentage'));
                $cheatersGroupData['number_of_cheating_questions'] = round($group->avg('number_of_cheating_questions'));
                $cheatersGroupData['school'] = $firstRecordParticipant->school->name;
                $cheatersGroupData['country'] = $firstRecordParticipant->country->display_name;
                $cheatersGroupData['grade'] = $firstRecordParticipant->grade;
                $cheatersGroupData['group_id'] = $group_id;
                $cheatersGroupData['participants'] = $group->map(
                    fn($cheatingParticipant) => $cheatingParticipant->only('index_no', 'name')
                )->toArray();
                return $cheatersGroupData;
            });
    }

    /**
     * Get filter options For cheating list
     * 
     * @param Illuminate\Support\Collection $cheaters
     * 
     */
    public static function getFilterOptions($cheaters)
    {
        return [
            'country' => $cheaters->pluck('country')->unique()->values(),
            'school' => $cheaters->pluck('school')->unique()->values(),
            'grade' => $cheaters->pluck('grade')->unique()->values(),
            'cheating_percentage' => $cheaters->pluck('cheating_percentage')->unique()->values(),
            'number_of_cheating_questions' => $cheaters->pluck('number_of_cheating_questions')->unique()->values(),
        ];
    }

    /**
     * Generate cheating list CSV file
     * 
     * @param Competition $competition
     * @return Illuminate\Http\Response
     */
    public static function getCheatersDataForCSV(Competition $competition)
    {
        return Participants::distinct()
            ->join('cheating_participants', function (JoinClause $join) {
                    $join->on('participants.index_no', 'cheating_participants.participant_index')
                        ->orOn('participants.index_no', 'cheating_participants.cheating_with_participant_index');
            })
            ->where('cheating_participants.competition_id', $competition->id)
            ->select(
                'participants.index_no', 'participants.name', 'participants.school_id', 
                'participants.country_id', 'participants.grade', 'cheating_participants.group_id',
                'cheating_participants.number_of_cheating_questions', 'cheating_participants.cheating_percentage'
            )
            ->with(['school', 'country', 'answers' => fn($query) => $query->orderBy('task_id')])
            ->withCount('answers')
            ->get()
            ->map(function($participant) {
                $questions = [];
                for($i=1; $i<=$participant->answers_count; $i++){
                    $questions[sprintf("Question %s", $i)] =
                        sprintf("%s (%s)", $participant->answers[$i-1]->answer, $participant->answers[$i-1]->is_correct ? 'Correct' : 'Incorrect');
                }
                $participant->school = $participant->school->name;
                $participant->country = $participant->country->display_name;
                return array_merge($participant->only('index_no', 'name', 'school', 'country', 'grade', 'group_id', 'number_of_cheating_questions', 'cheating_percentage'), $questions);
            })->sortBy('group_id')
            ->unique(function ($participant) {
                return sprintf("%s-%s", $participant['index_no'], $participant['group_id']);
            })->values();
    }

    /**
     * Get cheating csv file
     * 
     * @param Competition $competition
     * @return Illuminate\Http\Response
     */
    public static function getCheatingCSVFile(Competition $competition)
    {
        $fileName = sprintf("cheaters_%s.xlsx", $competition->id);
        if(Storage::disk('local')->exists($fileName)){
            Storage::disk('local')->delete($fileName);
        }
        if (Excel::store(new CheatersExport($competition), $fileName)) {
            return response(200);
        }
        return response(500);
    }
}
