<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\ParticipantsAnswer;
use App\Models\PossibleSimilarAnswer;
use App\Models\Tasks;
use App\Models\TasksAnswers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PossibleSimilarAnswersController extends Controller
{
    public function getCompetitionLevelsAndTasks(Competition $competition, Request $request)
    {
        $level = $competition->levels()->where('competition_levels.id', $request->level_id)->with('collection.sections')->first();
        $levelTasks = [];
        if (!empty($level->collection)) {
            foreach ($level->collection->sections as $section) {
                $tasks = $section->section_task;
                $filteredTasks = $tasks->reject(function ($task) {
                    return $task->answer_type == 'mcq';
                });
                foreach ($filteredTasks as $task) {
                    $levelTasks[] = [
                        'task_id' => $task->id,
                        'task_name' => $task->identifier,
                        'collection_id' => $level->collection->id,
                        'collection_name' => $level->collection->name,
                        'section_id' => $section->id,
                    ];
                }
            }
        }

        return response()->json([
            "status" => 200,
            "message" => "Success",
            "data" => [
                'competition_name' => $competition->name,
                'level_name' => $level->name,
                'tasks' => $levelTasks
            ]
        ], 200);
    }

    public function getTaskPossibleSimilarAnswers(Tasks $task, Request $request)
    {
        try {
            $answerData = [];
            $similarAnswers = $this->fetchSimilarAnswersForTask($task->id);
            foreach ($similarAnswers as $similarAnswer) {
                $filteredPossibleKeys = collect($similarAnswer['possible_keys'])->reject(function ($possibleKey) use ($similarAnswer) {
                    return trim($possibleKey) === trim($similarAnswer['answer_key']);
                })->values();

                // Only include the node if there are any filteredPossibleKeys left
                if ($filteredPossibleKeys->isNotEmpty()) {
                    $answerData = [
                        'task_id' => $task->id,
                        'answer_id' => $similarAnswer['answer_id'],
                        'answer_key' => $similarAnswer['answer_key'],
                        'possible_keys' => $filteredPossibleKeys->all(),
                    ];

                    $identifiers = [
                        'task_id' => $answerData['task_id'],
                        'answer_id' => $answerData['answer_id']
                    ];

                    PossibleSimilarAnswer::updateOrCreate($identifiers, $answerData);
                }
            }
            $possibleSimilarAnswers = $task->possibleSimilarAnswers()->with(['task', 'answer', 'approver'])->get();

            return response()->json([
                "status" => 200,
                "message" => "Success",
                "data" => $possibleSimilarAnswers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Internal Server Error {$e->getMessage()}",
                "error"     => strval($e)
            ], 500);
        }
    }

    protected function fetchSimilarAnswersForTask($taskId)
    {
        $taskAnswers = TasksAnswers::where('task_id', $taskId)
            ->where('answer', '!=', null)
            ->whereHas('task', function ($query) {
                $query->where('answer_type', '!=', 1);
            })
            ->get();


        $response = [];
        foreach ($taskAnswers as $taskAnswer) {
            $normalizedKey = intval($taskAnswer->answer);

            $similarAnswers = ParticipantsAnswer::where('task_id', $taskId)
                ->select('*', DB::raw('CAST(answer AS UNSIGNED) as numeric_answer'))
                ->get()
                ->filter(function ($participantAnswer) use ($normalizedKey) {
                    return intval($participantAnswer->numeric_answer) === $normalizedKey;
                })
                ->pluck('answer')
                ->unique()
                ->values();

            $response[] = [
                'task_id' => $taskId,
                'answer_id' => $taskAnswer->id,
                'answer_key' => $taskAnswer->answer,
                'possible_keys' => $similarAnswers->all()
            ];
        }

        return $response;
    }

    public function approveSimilarAnswer($id)
    {
        $possibleAnswer = PossibleSimilarAnswer::findOrFail($id);
        $possibleAnswer->status = PossibleSimilarAnswer::STATUS_APPROVED;
        $possibleAnswer->approved_by = Auth::id();
        $possibleAnswer->approved_at = now();
        $possibleAnswer->save();
        return response()->json([
            'message' => 'Possible answer approved successfully.',
            'data' => $possibleAnswer
        ], 200);
    }

    public function declineSimilarAnswer($id)
    {
        $possibleAnswer = PossibleSimilarAnswer::findOrFail($id);
        $possibleAnswer->status = PossibleSimilarAnswer::STATUS_APPROVED;
        $possibleAnswer->approved_by = Auth::id();
        $possibleAnswer->approved_at = now();
        $possibleAnswer->save();
        return response()->json([
            'message' => 'Possible answer approved successfully.',
            'data' => $possibleAnswer
        ], 200);
    }
}
