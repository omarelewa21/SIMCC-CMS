<?php


namespace App\Helpers;

use App\Exports\CheatersExport;
use App\Http\Requests\Competition\CompetitionCheatingListRequest;
use App\Models\CheatingStatus;
use App\Models\Competition;
use App\Models\Participants;
use App\Services\GradeService;
use App\Services\MarkingService;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class CheatingListHelper
{
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
            if($request->csv == 1) return static::getCheatingCSVFile($competition, $request);

            $cheaters = static::getCheatingList($competition)
                ->filterByRequest(
                    $request,
                    array("country", "school", "grade", "group_id"),
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
     * @return Illuminate\Http\Response
     */
    public static function getCheatersDataForCSV(Competition $competition, CompetitionCheatingListRequest $request)
    {
        return static::getCheatersCollectionForCSV($competition, $request)
            ->map(fn($participant) => static::getCheatingParticipantReadyForCSV($participant))
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
    private static function getCheatersCollectionForCSV(Competition $competition, CompetitionCheatingListRequest $request)
    {
        return Participants::distinct()
            ->join('cheating_participants', function (JoinClause $join) {
                    $join->on('participants.index_no', 'cheating_participants.participant_index')
                        ->orOn('participants.index_no', 'cheating_participants.cheating_with_participant_index');
            })
            ->where('cheating_participants.competition_id', $competition->id)
            ->when($request->has('country'), fn($query) => $query->where('participants.country_id', $request->country))
            ->select(
                'participants.index_no',
                'participants.name',
                'participants.school_id',
                'participants.country_id',
                'participants.grade',
                'cheating_participants.group_id',
                'cheating_participants.number_of_questions',
                'cheating_participants.number_of_cheating_questions',
                'cheating_participants.cheating_percentage',
                'cheating_participants.number_of_same_correct_answers',
                'cheating_participants.number_of_same_incorrect_answers',
                'cheating_participants.different_question_ids',
            )
            ->with(['school', 'country', 'answers' => fn($query) => $query->orderBy('task_id')->with('level.collection.sections')])
            ->withCount('answers')
            ->get();
    }

    /**
     * Get cheating participant ready for CSV
     * 
     * @param Participant $participant
     * @return array
     */
    private static function getCheatingParticipantReadyForCSV($participant)
    {
        // [$participant->different_questions, $sections, $questions] = static::getQuestionsAndDifferentQuestions($participant);
        [$participant->different_questions, $questions] = static::getQuestionsAndDifferentQuestions($participant);

        $participant->school = $participant->school->name;
        $participant->country = $participant->country->display_name;
        $participant->number_of_correct_answers = $participant->answers->where('is_correct', true)->count();
        
        $formattedSections = [];
        // foreach ($sections as $key => $value) {
        //     $newKey = range('A', 'Z')[$key];
        //     $formattedSections["No of wrong answers in Section $newKey"] = $value;
        // }

        return array_merge($participant->only(
                'index_no', 'name', 'school', 'country', 'grade', 'group_id', 'number_of_questions', 
                'number_of_cheating_questions', 'cheating_percentage', 'number_of_same_correct_answers',
                'number_of_same_incorrect_answers', 'number_of_correct_answers', 'different_questions'
            ),
            $questions,
            $formattedSections
        );
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
        // $sections = [];

        $diffIds = json_decode($participant->different_question_ids, true);     // get the array of different questions

        for($i=1; $i<=$participant->answers_count; $i++){
            $participantAnswer = $participant->answers[$i-1];

            $questions["Q$i"] = sprintf("%s (%s)", $participantAnswer->answer, $participantAnswer->is_correct ? 'Correct' : 'Incorrect');

            if(!$participantAnswer->is_correct && !in_array($participantAnswer->task_id, $diffIds)) {
                $sameIncorrectQuestionNumbers[$participantAnswer->task_id] = "Q$i";
            }
        }

        // $sections = static::getIncorrectAnswersCountPerSection($participant, $sameIncorrectQuestionNumbers);
        // return [implode(', ', $sameIncorrectQuestionNumbers), $sections, $questions];
        return [implode(', ', $sameIncorrectQuestionNumbers), $questions];
    }

    /**
     * Get incorrect answers count per section
     * 
     * @param Participant $participant
     * @param array $sameIncorrectQuestionNumbers
     * @return array
     */
    private static function getIncorrectAnswersCountPerSection($participant, $sameIncorrectQuestionNumbers)
    {
        $sectionTasks = $participant->answers->first()
            ->level->collection->sections->pluck('tasks')
            ->map(fn($tasksArrayObject) => collect($tasksArrayObject)->flatten());  // get a collection of task ids with section number as key

        $sections = [];

        foreach($sectionTasks as $key => $taskIdsCollection){
            if(!isset($sections[$key])) $sections[$key] = 0;
            foreach($taskIdsCollection as $taskId){
                if(in_array($taskId, array_keys($sameIncorrectQuestionNumbers))) $sections[$key]++;
            }
        }

        return $sections;
    }

    /**
     * Get cheating csv file
     * 
     * @param Competition $competition
     * @param CompetitionCheatingListRequest $request
     * @return Illuminate\Http\Response
     */
    public static function getCheatingCSVFile(Competition $competition, CompetitionCheatingListRequest $request)
    {
        $fileName = static::getFileName($competition, $request->file_name);

        if(Storage::disk('local')->exists($fileName)){
            Storage::disk('local')->delete($fileName);
        }

        if (Excel::store(new CheatersExport($competition, $request), $fileName)) {
            // $file = Storage::get($fileName);
            // $response = response()->make($file, 200);
            // $response->header('Content-Type', 'application/'.pathinfo($fileName, PATHINFO_EXTENSION));
            return response()->file(storage_path("app/$fileName"), [
                'Content-Type' => 'application/'.pathinfo($fileName, PATHINFO_EXTENSION),
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            ]);
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
    private static function getFileName(Competition $competition, string|null $fileName)
    {
        if(!$fileName) {
            return sprintf("%s_cheating_list_%s.csv", $competition->name, now()->format('Y-m-d'));
        }

        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        if(!$fileExtension) {
            return sprintf("%s.%s", $fileName, 'csv');
        }

        if(!in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
            return str_replace($fileExtension, 'csv', $fileName);
        }

        return $fileName;
    }
}