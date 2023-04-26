<?php

namespace App\Services;

use App\Custom\Marking;
use App\Http\Requests\Competition\CompetitionCheatingListRequest;
use App\Models\CheatingParticipants;
use App\Models\CheatingStatus;
use App\Models\Competition;
use App\Models\Participants;

class CompetitionService
{
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
            if($request->csv == 1) return static::generateCheatersCSVFile($competition);

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
     * Get cheating list
     * 
     * @param Competition $competition
     * @return Illuminate\Support\Collection
     */
    public static function getCheatingList(Competition $competition)
    {
        return CheatingParticipants::where('competition_id', $competition->id)
            ->selectRaw(
                "*,
                AVG(number_of_cheating_questions) AS avg_cheating_questions_number,
                AVG(cheating_percentage) AS avg_cheating_percentage_percentage"
            )->groupBy('group_id')
            ->get()
            ->mapWithKeys(function($group){
                $cheatersGroupData = [];
                $cheatingParticipants = static::getCheatingParticipantsByGroup($group->group_id, ['country', 'school']); 
                $firstRecordParticipant = $cheatingParticipants->first();

                $cheatersGroupData['number_of_questions'] = $firstRecordParticipant->answers()->count();
                $cheatersGroupData['cheating_percentage'] = round($group->avg_cheating_percentage_percentage);
                $cheatersGroupData['number_of_cheating_questions'] = round($group->avg_cheating_questions_number);
                $cheatersGroupData['school'] = $firstRecordParticipant->school->name;
                $cheatersGroupData['country'] = $firstRecordParticipant->country->display_name;
                $cheatersGroupData['grade'] = $firstRecordParticipant->grade;
                $cheatersGroupData['group_id'] = $group->group_id;
                $cheatersGroupData['participants'] = $cheatingParticipants->map(
                    fn($cheatingParticipant) => $cheatingParticipant->only('index_no', 'name')
                )->toArray();
                return [$group->group_id => $cheatersGroupData];
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
     * Get cheating participants by group
     * 
     * @param int $group_id
     * @param array $eagerLoad
     * @return Illuminate\Support\Collection
     */
    public static function getCheatingParticipantsByGroup($group_id, $eagerLoad=[])
    {
        return Participants::distinct()
            ->leftJoin('cheating_participants as cp1', 'cp1.participant_index', 'participants.index_no')
            ->leftJoin('cheating_participants as cp2', 'cp2.cheating_with_participant_index', 'participants.index_no')
            ->where('cp1.group_id', $group_id)
            ->orWhere('cp2.group_id', $group_id)
            ->select('participants.index_no', 'participants.name', 'participants.school_id', 'participants.country_id', 'participants.grade')
            ->with($eagerLoad)
            ->get();
    }

    /**
     * Generate cheating list CSV file
     * 
     * @param Competition $competition
     * @return Illuminate\Http\Response
     */
    public static function generateCheatersCSVFile(Competition $competition)
    {
        $cheaters =  Participants::select('index_no', 'name', 'school_id', 'country_id', 'grade')
            ->where(function ($query) use ($competition) {
                $query->whereIn('index_no', function ($subquery) use ($competition) {
                    $subquery->select('participant_index')
                        ->from('cheating_participants')
                        ->where('competition_id', $competition->id);
                })->orWhereIn('index_no', function ($subquery) use ($competition) {
                    $subquery->select('cheating_with_participant_index')
                        ->from('cheating_participants')
                        ->where('competition_id', $competition->id);
                });
            })
        ->groupBy('index_no', 'name', 'school_id', 'country_id', 'grade')
        ->with(['school', 'country', 'answers' => fn($query) => $query->orderBy('task_id')])
        ->withCount('answers')
        ->get()
        ->map(function($participant){
            $questions = [];
            for($i=1; $i<=$participant->answers_count; $i++){
                $questions[sprintf("Question %s", $i)] =
                    sprintf("%s (%s)", $participant->answers[$i-1]->answer, $participant->answers[$i-1]->is_correct ? 'Correct' : 'Incorrect');
            }
            $participant->school = $participant->school->name;
            $participant->country = $participant->country->display_name;
            return array_merge($participant->only('index_no', 'name', 'school', 'country', 'grade'), $questions);
        });

        return response()->json([
            'headers'   => array_keys($cheaters->first()),
            'data'      => $cheaters
        ], 200);

        // $filename = 'report.csv';
        // $fp = fopen(public_path().'/'.$filename, 'w');
        // fputcsv($fp, $cheaters[0]);
        // foreach ($cheaters as $cheater) {
        //     fputcsv($fp, $cheater);
        // }
        // fclose($fp);


        // if (file_exists(public_path().'/'.$filename)) {
        //     header('Content-Description: File Transfer');
        //     header('Content-Type: application/octet-stream');
        //     header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        //     header('Expires: 0');
        //     header('Cache-Control: must-revalidate');
        //     header('Pragma: public');
        //     header('Content-Length: ' . filesize($filename));
        //     readfile($filename);
        //     exit;
        // }
    }
}
