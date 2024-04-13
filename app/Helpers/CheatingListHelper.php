<?php


namespace App\Helpers;

use App\Exports\CheatersExport;
use App\Http\Requests\Competition\CompetitionCheatingListRequest;
use App\Http\Requests\Competition\ConfirmCountryForIntegrityRequest;
use App\Jobs\ComputeCheatingParticipants;
use App\Models\CheatingStatus;
use App\Models\Competition;
use App\Models\Countries;
use App\Models\IntegrityCheckCompetitionCountries;
use App\Models\Participants;
use App\Services\GradeService;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class CheatingListHelper
{
    /**
     * Get Main Integrity List Data or Start the job
     *
     * @param Competition $competition
     * @return \Illuminate\Http\JsonResponse
     */
    public function getcheatingParticipants(Competition $competition, CompetitionCheatingListRequest $request)
    {
        try {
            if ($request->recompute) {
                $this->fireJob($competition, $request);
                return response()->json([
                    'status'    => 201,
                    'message'   => 'Computing Integrity list has been started.',
                    'progress'  => 1
                ], 201);
            }

            return $this->returnCheatingData($competition, $request);
        }

        catch (\Exception $e) {
            return response()->json([
                'status'    => intval($e->getCode()) ? intval($e->getCode()) : 500,
                'message'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Same Participant Cheating List Data or Start the job
     * 
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     */
    public function getSameParticipantCheatingList(Competition $competition, CompetitionCheatingListRequest $request)
    {
        try {
            $request->merge(['for_map_list' => 1]);

            if ($request->recompute) {
                $this->fireJob($competition, $request);
                return response()->json([
                    'status'    => 201,
                    'message'   => 'Computing Multiple Attempts List has been started.',
                    'progress'  => 1
                ], 201);
            }

            return $this->returnSameParticipantCheatingData($competition, $request);

        } catch (\Exception $e) {
            return response()->json([
                'status'    => intval($e->getCode()) ? intval($e->getCode()) : 500,
                'message'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fire job for main integrity list
     *
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     * @return void
     */
    private function fireJob(Competition $competition, CompetitionCheatingListRequest $request)
    {
        DB::beginTransaction();

        CheatingStatus::updateOrCreate([
            'competition_id'                    => $competition->id,
            'cheating_percentage'               => $request->percentage ?? 85,
            'number_of_same_incorrect_answers'  => $request->number_of_incorrect_answers ?? 5,
            'countries'                         => $request->country,
            'for_map_list'                      => $request->for_map_list ?? false
        ],
        [
            'status'                            => 'In Progress',
            'progress_percentage'               => 1,
            'compute_error_message'             => null
        ]);

        dispatch(new ComputeCheatingParticipants(
            $competition,
            $request->question_number,
            $request->percentage,
            $request->number_of_incorrect_answers,
            $request->country,
            $request->for_map_list
        ));

        DB::commit();
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
            'country' => $cheaters->map(function($participant) {
                return [
                    'id' => $participant['country_id'],
                    'name' => $participant['country']
                ];
            })->unique('id')->values(),
            'grade' => GradeService::getAvailableCorrespondingGradesFromList(
                $cheaters->pluck('grade')->unique()->sort()->values()->toArray()
            ),
        ];
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
     * Generate cheating list CSV file
     * 
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     * @param bool $forCSV
     * 
     * @return Illuminate\Http\Response
     */
    public static function getCheatersData(Competition $competition, CompetitionCheatingListRequest $request, bool $forCSV = false)
    {
        return static::getCheatersCollection($competition, $request)
            ->map(fn($participant) => static::getCheatingParticipantDataReady($participant, $forCSV))
            ->sortBy('group_id')
            ->unique(fn($participant) => sprintf("%s-%s", $participant['index_no'], $participant['group_id']))
            ->values();
    }

    /**
     * Get cheating list for CSV
     * 
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     * @return Illuminate\Support\Collection
     */
    private static function getCheatersCollection(Competition $competition, CompetitionCheatingListRequest $request)
    {
        return Participants::distinct()
            ->join('cheating_participants', function (JoinClause $join) {
                    $join->on('participants.index_no', 'cheating_participants.participant_index')
                        ->orOn('participants.index_no', 'cheating_participants.cheating_with_participant_index');
            })
            ->where('cheating_participants.competition_id', $competition->id)
            ->where('cheating_participants.is_same_participant', 0)
            ->when($request->has('country') && !$request->has('percentage'), fn($query) => $query->whereIn('participants.country_id', $request->country))
            ->when($request->has('grade'), fn($query) => $query->where('participants.grade', $request->grade))
            ->when($request->has('search'), function($query) use($request){
                $query->where('participants.index_no', 'like', "%{$request->search}%")
                    ->orWhere('participants.name', 'like', "%{$request->search}%");
            })
            ->selectRaw("
                participants.index_no, participants.name, participants.school_id, participants.country_id,
                participants.grade, cheating_participants.group_id, cheating_participants.number_of_questions,
                cheating_participants.number_of_cheating_questions, cheating_participants.cheating_percentage,
                cheating_participants.number_of_same_correct_answers, cheating_participants.number_of_same_incorrect_answers,
                cheating_participants.different_question_ids, cheating_participants.criteria_cheating_percentage,
                cheating_participants.criteria_number_of_same_incorrect_answers
            ")
            ->with(['school', 'country', 'answers' => fn($query) => $query->orderBy('task_id')->with('level.collection.sections'),
                'integrityCases' => fn($query) => $query->where('mode', 'system')]
            )
            ->withCount('answers')
            ->get();
    }

    /**
     * Get cheating participant ready for CSV
     * 
     * @param Participant $participant
     * @param bool $forCSV
     * 
     * @return array
     */
    private static function getCheatingParticipantDataReady($participant, $forCSV = false)
    {
        [$participant->different_questions, $questions] = static::getQuestionsAndDifferentQuestions($participant);

        $participant->school = $participant->school->name;
        $participant->country = $participant->country->display_name;
        $participant->number_of_correct_answers = $participant->answers->where('is_correct', true)->count();
        $participant->is_iac = $participant->integrityCases->isNotEmpty() ? 'Yes' : 'No';
        
        $filtered = $participant->only(
            'index_no', 'name', 'school', 'country', 'grade', 'is_iac', 'criteria_cheating_percentage',
            'criteria_number_of_same_incorrect_answers', 'group_id', 'number_of_questions', 
            'number_of_cheating_questions', 'cheating_percentage', 'number_of_same_correct_answers',
            'number_of_same_incorrect_answers', 'number_of_correct_answers', 'different_questions'
        );
        
        if(!$forCSV) $filtered['country_id'] = $participant->country_id;
        return array_merge($filtered, $questions);
    }
    
    /**
     * Get questions and different questions
     * 
     * @param Participant $participant
     * @return array
     */
    private static function getQuestionsAndDifferentQuestions($participant)
    {
        $sameIncorrectQuestionNumbers = [];
        $questions = [];

        $diffIds = json_decode($participant->different_question_ids, true);     // get the array of different questions

        for($i=1; $i<=$participant->answers_count; $i++){
            $participantAnswer = $participant->answers[$i-1];

            $questions["Q$i"] = sprintf("%s (%s)", $participantAnswer->answer, $participantAnswer->is_correct ? 'Correct' : 'Incorrect');

            if(!$participantAnswer->is_correct && !in_array($participantAnswer->task_id, $diffIds)) {
                $sameIncorrectQuestionNumbers[$participantAnswer->task_id] = "Q$i";
            }
        }

        return [implode(', ', $sameIncorrectQuestionNumbers), $questions];
    }

    /**
     * Get cheating csv file
     * 
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     * @return Illuminate\Http\Response
     */
    public function getCheatingCSVFile(Competition $competition, CompetitionCheatingListRequest $request)
    {
        $fileName = $this->getFileName($competition, $request->file_name);

        if(Storage::disk('local')->exists($fileName)){
            Storage::disk('local')->delete($fileName);
        }

        if (Excel::store(new CheatersExport($competition, $request), $fileName)) {
            $file = Storage::get($fileName);
            Storage::disk('local')->delete($fileName);
            $response = response()->make($file, 200);
            $response->header('Content-Type', 'application/'.pathinfo($fileName, PATHINFO_EXTENSION));
            $response->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
            return $response;
        }

        return response()->json([
            'status'    => 500,
            'message'   => 'Failed to generate cheating list'
        ], 500);
    }

    /**
     * Get file name
     * 
     * @param string $fileName
     * @return string
     */
    private function getFileName(Competition $competition, string|null $fileName)
    {
        if(!$fileName) {
            return sprintf("%s_cheating_list_%s.xlsx", $competition->name, now()->format('Y-m-d'));
        }

        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        if(!$fileExtension) {
            return sprintf("%s.%s", $fileName, 'xlsx');
        }

        return str_replace($fileExtension, 'xlsx', $fileName);
    }

    /**
     * Get cheating status
     * 
     * @param Competition $competition
     * @param CheatingStatus|null $cheatingStatus
     * 
     * @return Illuminate\Http\JsonResponse
     */
    public function returnCheatingStatus(Competition $competition, CheatingStatus|null $cheatingStatus, $list = 'Integrity')
    {
        switch ($cheatingStatus?->status) {
            case 'In Progress':
                $response = [
                    'status'    => 202,
                    'message'   => "Generating $list list is in progress"
                ];
                break;
            case 'Failed':
                $response = [
                    'status'    => 417,
                    'message'   => "Generating $list list failed at perentage {$cheatingStatus->progress_percentage} with error: {$cheatingStatus->compute_error_message}",
                ];
                break;
            case 'Completed':
                $response = [
                    'status'    => 200,
                    'message'   => "$list list generated successfully",
                ];
                break;
            default:
                return response()->json([
                    'status'        => 206,
                    'message'       => "Generating $list list is not started",
                    'progress'      => 0,
                    'competition'   => $competition->name
                ], 206);
                break;
        }

        return response()->json(array_merge($response, [
            'progress'      => $cheatingStatus->progress_percentage,
            'competition'   => $competition->name
        ]), $response['status']);
    }

    /**
     * Get cheating data
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     * @return Illuminate\Http\JsonResponse
     */
    public function returnCheatingData(Competition $competition, CompetitionCheatingListRequest $request)
    {
        $cheatingStatus = CheatingStatus::where([
                'competition_id'                    => $competition->id,
                'cheating_percentage'               => $request->percentage ?? 85,
                'number_of_same_incorrect_answers'  => $request->number_of_incorrect_answers ?? 5,
                'for_map_list'                      => 0
        ])
        ->when($request->countries, fn($query) => $query->whereJsonContains('countries', $request->countries))
        ->first();

        if($cheatingStatus?->status === 'Completed')
        return $request->mode === 'csv'
            ? $this->getCheatingCSVFile($competition, $request)
            : $this->returnCheatingDataForUI($competition, $request);

        return $this->returnCheatingStatus($competition, $cheatingStatus);
    }
    
    /**
     * Get same participant cheaters data
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     */
    public static function getSameParticipantCheatersData(Competition $competition, CompetitionCheatingListRequest $request, bool $forCSV = false)
    {
        return static::getSameParticipantCheatersCollection($competition, $request)
            ->map(fn($participant) => static::getSameParticipantCheatingParticipantReady($participant, $forCSV))
            ->sortBy('group_id')
            ->unique(fn($participant) => sprintf("%s-%s", $participant['index_no'], $participant['group_id']))
            ->values();
    }

    /**
     * Get same participant cheaters collection for CSV
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     */
    private static function getSameParticipantCheatersCollection(Competition $competition, CompetitionCheatingListRequest $request)
    {
        return Participants::distinct()
            ->join('cheating_participants', function (JoinClause $join) {
                    $join->on('participants.index_no', 'cheating_participants.participant_index')
                        ->orOn('participants.index_no', 'cheating_participants.cheating_with_participant_index');
            })
            ->where('cheating_participants.competition_id', $competition->id)
            ->where('cheating_participants.is_same_participant', 1)
            ->when($request->has('country'), fn($query) => $query->whereIn('participants.country_id', $request->country))
            ->when($request->has('grade'), fn($query) => $query->where('participants.grade', $request->grade))
            ->when($request->has('search'), function($query) use($request){
                $query->where('participants.index_no', 'like', "%{$request->search}%")
                    ->orWhere('participants.name', 'like', "%{$request->search}%");
            })
            ->select(
                'participants.index_no',
                'participants.name',
                'participants.school_id',
                'participants.country_id',
                'participants.grade',
                'cheating_participants.group_id'
            )
            ->with(['school', 'country', 'answers' => fn($query) => $query->orderBy('task_id')->with('level.collection.sections'),
                'integrityCases' => fn($query) => $query->where('mode', 'system')]
            )
            ->withCount('answers')
            ->get();
    }

    /**
     * Get same participant cheating participant ready for CSV
     * @param Participant $participant
     */
    private static function getSameParticipantCheatingParticipantReady($participant, $forCSV = false)
    {
        $questions = static::getSameParticipantQuestions($participant);

        $participant->school = $participant->school->name;
        $participant->country = $participant->country->display_name;
        $participant->number_of_answers = $participant->answers_count;
        $participant->is_iac = $participant->integrityCases->isNotEmpty() ? 'Yes' : 'No';

        $filtered = $participant->only(
            'index_no', 'name', 'school', 'country', 'grade', 'is_iac', 'group_id', 'number_of_answers'
        );
    
        if(!$forCSV) $filtered['country_id'] = $participant->country_id;
        return array_merge($filtered, $questions);
    }

    /**
     * Get same participant questions
     * @param Participant $participant
     */
    private static function getSameParticipantQuestions($participant)
    {
        $questions = [];
        for($i=1; $i <= $participant->answers_count; $i++){
            $participantAnswer = $participant->answers[$i-1];
            $questions["Q$i"] = sprintf("%s (%s)", $participantAnswer->answer, $participantAnswer->is_correct ? 'Correct' : 'Incorrect');
        }
        return $questions;
    }

    /**
     * Get Cheating data for UI
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     */
    public function returnCheatingDataForUI(Competition $competition, CompetitionCheatingListRequest $request)
    {
        $data = static::getCheatersData($competition, $request);

        $returnedCollection = collect();
        $lastGroup = null;
        foreach($data as $key=>$record) {
            if($key !== 0 && $lastGroup !== $record['group_id']) {
                $returnedCollection->push(['-'], $record);
            }else{
                $returnedCollection->push($record);
            }
            $lastGroup = $record['group_id'];
        }

        $headers = [];
        if($data->isNotEmpty()) {
            $headers = array_slice(array_keys($data->max()), 17);
            foreach($headers as $key => $header) {
                $headers[sprintf("Q%s", $key+1)] = $header;
                unset($headers[$key]);
            }
        }

        $headers =  [
            'Index'                                         => 'index_no',
            'Name'                                          => 'name',
            'School'                                        => 'school',
            'Country'                                       => 'country',
            'Grade'                                         => 'grade',
            'System generated IAC'                          => 'is_iac',
            'Criteria Integrity Percentage'                 => 'criteria_cheating_percentage',
            'Criteria No of Same Incorrect Answers'         => 'criteria_number_of_same_incorrect_answers',
            'Group ID'                                      => 'group_id',
            'No of qns'                                     => 'number_of_questions',
            'No of qns with same answer'                    => 'number_of_cheating_questions',
            'No of qns with same answer percentage'         => 'cheating_percentage', 
            'No of qns with same correct answer'            => 'number_of_same_correct_answers',
            'No of qns with same incorrect answer'          => 'number_of_same_incorrect_answers',
            'No of correct answers'                         => 'number_of_correct_answers',
            'Qns with same incorrect answer'                => 'different_questions',
            ...$headers
        ];
        
        return response()->json([
            'status'            => 201,
            'message'           => static::getMessageForCheatingData($competition, $request),
            'competition'       => $competition->name,
            'filter_options'    => static::getFilterOptions($data),
            'computed_countries'=> IntegrityCheckCompetitionCountries::getComputedCountriesList($competition),
            'remaining_countries'=> IntegrityCheckCompetitionCountries::getRemainingCountriesList($competition),
            'headers'           => $headers,
            'data'              => $returnedCollection
        ], 201);
    }

    /**
     * Get same participant cheating data
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     */
    public function returnSameParticipantCheatingData(Competition $competition, CompetitionCheatingListRequest $request)
    {
        $cheatingStatus = CheatingStatus::where([
            'competition_id'                    => $competition->id,
            'cheating_percentage'               => $request->percentage ?? 85,
            'number_of_same_incorrect_answers'  => $request->number_of_incorrect_answers ?? 5,
            'for_map_list'                      => 1
        ])
        ->when($request->countries, fn($query) => $query->whereJsonContains('countries', $request->countries))
        ->first();

        if($cheatingStatus?->status === 'Completed')
         return $this->returnSameParticipantCheatingList($competition, $request);

        return $this->returnCheatingStatus($competition, $cheatingStatus, 'Multiple Attempts');
    }

    /**
     * Get same participant cheating list
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     */
    public function returnSameParticipantCheatingList(Competition $competition, CompetitionCheatingListRequest $request)
    {
        $data = static::getSameParticipantCheatersData($competition, $request);

        $returnedCollection = collect();
        $lastGroup = null;
        foreach($data as $key=>$record) {
            if($key !== 0 && $lastGroup !== $record['group_id']) {
                $returnedCollection->push(['-'], $record);
            }else{
                $returnedCollection->push($record);
            }
            $lastGroup = $record['group_id'];
        }

        $headers = [];
        if($data->isNotEmpty()) {
            $headers = array_slice(array_keys($data->max()), 9);
            foreach($headers as $key => $header) {
                $headers[sprintf("Q%s", $key+1)] = $header;
                unset($headers[$key]);
            }
        }

        $headers =  [
            'Index'                                         => 'index_no',
            'Name'                                          => 'name',
            'School'                                        => 'school',
            'Country'                                       => 'country',
            'Grade'                                         => 'grade',
            'Group ID'                                      => 'group_id',
            'No. Of Answers Uploaded'                       => 'number_of_answers',
            'System generated IAC'                          => 'is_iac',
            ...$headers
        ];

        return response()->json([
            'status'            => 201,
            'message'           => static::getMessageForCheatingData($competition, $request),
            'competition'       => $competition->name,
            'filter_options'    => static::getFilterOptions($data),
            'computed_countries'=> IntegrityCheckCompetitionCountries::getComputedCountriesList($competition),
            'remaining_countries'=> IntegrityCheckCompetitionCountries::getRemainingCountriesList($competition),
            'headers'           => $headers,
            'data'              => $returnedCollection
        ], 201);
    }

    /**
     * Get message for cheating data
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     */
    public static function getMessageForCheatingData(Competition $competition, CompetitionCheatingListRequest $request)
    {
        if($request->has('percentage') && $request->has('number_of_incorrect_answers')) {
            return Participants::distinct()
            ->join('cheating_participants', function (JoinClause $join) {
                    $join->on('participants.index_no', 'cheating_participants.participant_index')
                        ->orOn('participants.index_no', 'cheating_participants.cheating_with_participant_index');
            })
            ->when($request->has('country'), fn($query) => $query->whereIn('participants.country_id', $request->country))
            ->where([
                'cheating_participants.competition_id'      => $competition->id,
                'cheating_participants.criteria_cheating_percentage' => $request->percentage,
                'cheating_participants.criteria_number_of_same_incorrect_answers' => $request->number_of_incorrect_answers
            ])
            ->exists()
            ? ''
            : sprintf("No Integrity Cases Found for cirteria (Percentage: %s, Number of incorrect answers: %s) and for countries: %s",
                $request->percentage,
                $request->number_of_incorrect_answers,
                $request->has('country')
                    ? Arr::join(Countries::whereIn('id', $request->country)->pluck('display_name')->toArray(), ', ')
                    : 'All Countries'
            );
        }
            
        return '';
    }

    public static function getCheatingCriteriaStatsData(Competition $competition)
    {
        return CheatingStatus::where([
            'competition_id'    => $competition->id,
            'for_map_list'      => 0
            ])
            ->select('competition_id', 'cheating_percentage', 'number_of_same_incorrect_answers', 'countries')
            ->get()
            ->map(function($cheatingStatus){
                $cheatingStatus->participants_count = Participants::distinct()
                    ->join('cheating_participants', function (JoinClause $join) {
                        $join->on('participants.index_no', 'cheating_participants.participant_index')
                            ->orOn('participants.index_no', 'cheating_participants.cheating_with_participant_index');
                    })
                    ->where([
                        'cheating_participants.competition_id'      => $cheatingStatus->competition_id,
                        'cheating_participants.criteria_cheating_percentage' => $cheatingStatus->cheating_percentage,
                        'cheating_participants.criteria_number_of_same_incorrect_answers' => $cheatingStatus->number_of_same_incorrect_answers
                    ])
                    ->when($cheatingStatus->original_countries && !empty($cheatingStatus->original_countries), fn($query) => $query->whereIn('participants.country_id', $cheatingStatus->original_countries))
                    ->count();
                return $cheatingStatus;
            });
    }

    public function getIntegrityCasesByCountry(Competition $competition, Countries $country)
    {
        return $competition->participants()
            ->has('integrityCases')
            ->where('participants.country_id', $country->id)
            ->with('school:id,name', 'country:id,display_name as name', 'integrityCases')
            ->select(
                'participants.index_no', 'participants.name', 'participants.school_id',
                'participants.country_id', 'participants.grade'
            )->get()
            ->map(function($participant){
                $data = $participant->toArray();
                $data['school'] = $participant->school->name;
                $data['country'] = $participant->country->name;
                $type = collect([]);
                foreach($participant->integrityCases as $integrityCase){
                    if($integrityCase->mode === 'system') {
                        $type->push('System');
                    }else{
                        $type->push('Custom');
                    }
                }
                $data['type'] = $type->join(', ', ' and ') . ' Generated IAC';
                unset($data['integrity_cases']);
                return $data;
            });
    }

    public function getConfirmedCountriesForIntegrityCheck(Competition $competition)
    {
        try {
            $confirmedCountries = $competition->integrityCheckCountries()
                ->join('all_countries as ac', 'ac.id', 'competition_countries_for_integrity_check.country_id')
                ->select('ac.display_name as name', 'ac.id', 'competition_countries_for_integrity_check.is_confirmed')
                ->get();

            return response()->json([
                'status'    => 200,
                'countries' => $confirmedCountries
            ], 200);
        }

        catch (\Exception $e) {
            return response()->json([
                'status'    => 500,
                'message'   => $e->getMessage(),
                'error'     => strval($e)
            ], 500);
        }
    }

    public function confirmCountryForIntegrityCheck(Competition $competition, ConfirmCountryForIntegrityRequest $request)
    {
        try {
            foreach($request->countries as $country) {
                $competition->integrityCheckCountries()
                    ->updateOrCreate(
                        ['country_id' => $country['id']],
                        [
                            'is_confirmed' => $country['is_confirmed'],
                            'confirmed_by' => auth()->id(),
                            'confirmed_at' => now()
                        ]
                    );
            }

            return response()->json([
                'status'    => 200,
                'message'   => 'Country has been confirmed for integrity check'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'    => 500,
                'message'   => $e->getMessage(),
                'error'     => strval($e)
            ], 500);
        }
    }

    public function getCustomLabeledIntegrityCases(Competition $competition)
    {
        try {
            return response()->json([
                'status'    => 200,
                'data'      => $this->getCustomLabeledIntegrityCasesData($competition)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'    => 500,
                'message'   => $e->getMessage(),
                'error'     => strval($e)
            ], 500);
        }
    }

    public function getCustomLabeledIntegrityCasesData(Competition $competition)
    {
        return $competition->participants()
            ->whereRelation('integrityCases', 'mode', 'custom')
            ->where('participants.status', Participants::STATUS_CHEATING)
            ->with('school:id,name', 'country:id,display_name as name', 'integrityCases')
            ->select(
                'participants.index_no', 'participants.name', 'participants.school_id',
                'participants.country_id', 'participants.grade'
            )
            ->get()
            ->map(function($participant){
                $data = $participant->toArray();
                $data['school'] = $participant->school->name;
                $data['country'] = $participant->country->name;
                $data['reason'] = $participant->integrityCases->first()->reason;
                unset($data['integrity_cases']);
                return $data;
            });
    }

    public function getCheatingCriteriaStats(Competition $competition)
    {
        try {
            return response()->json([
                'status'    => 200,
                'data'      => $this->getCheatingCriteriaStatsData($competition)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'    => 500,
                'message'   => $e->getMessage(),
                'error'     => strval($e)
            ], 500);
        }
    }
}