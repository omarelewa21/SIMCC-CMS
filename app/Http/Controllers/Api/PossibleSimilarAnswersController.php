<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\ParticipantsAnswer;
use App\Models\PossibleSimilarAnswer;
use App\Models\Tasks;
use App\Models\TasksAnswers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PossibleSimilarAnswersController extends Controller
{
    public function getCompetitionLevelsAndTasks(Competition $competition, Request $request)
    {
        $request->validate([
            'level_id' => 'required|integer|exists:competition_levels,id',
        ]);

        $level = $competition->levels()->where('competition_levels.id', $request->level_id)->with('collection.sections')->first();
        $levelTasks = [];
        if (!empty($level->collection)) {
            $taskNumber = 1;
            foreach ($level->collection->sections as $index => $section) {
                $sectionLetter = chr(65 + $index);
                $tasks = $section->section_task;
                foreach ($tasks as $task) {
                    if ($task->answer_type != 'mcq') {
                        $levelTasks[] = [
                            'task_id' => $task->id,
                            'task_name' => $task->identifier,
                            'collection_id' => $level->collection->id,
                            'collection_name' => $level->collection->name,
                            'section_id' => $section->id,
                            'task_tag' => $task->identifier . ' - Section ' . $sectionLetter . ' Question ' . $taskNumber
                        ];
                    }
                    $taskNumber++;
                }
            }
        }

        return response()->json([
            "status" => 200,
            "message" => "Success",
            "data" => [
                'competition_name' => $competition->name,
                'level_name' => $level?->name,
                'tasks' => $levelTasks
            ]
        ], 200);
    }

    public function getTaskPossibleSimilarAnswers(Tasks $task)
    {
        try {
            $answerData = [];
            $similarAnswers = $this->fetchSimilarAnswersForTask($task->id);

            foreach ($similarAnswers as $similarAnswer) {
                $filteredPossibleKeys = collect($similarAnswer['possible_keys'])->reject(function ($possibleKey) use ($similarAnswer) {
                    return trim($possibleKey) === trim($similarAnswer['answer_key']);
                })->values();

                if ($filteredPossibleKeys->isNotEmpty()) {
                    foreach ($filteredPossibleKeys as $key) {
                        $answerData = [
                            'task_id' => $task->id,
                            'answer_id' => $similarAnswer['answer_id'],
                            'answer_key' => $similarAnswer['answer_key'],
                            'possible_key' => $key,
                        ];

                        $identifiers = [
                            'task_id' => $answerData['task_id'],
                            'answer_id' => $answerData['answer_id'],
                            'possible_key' => $key,
                        ];
                        PossibleSimilarAnswer::updateOrCreate($identifiers, $answerData);
                    }
                }
            }

            $possibleSimilarAnswers = $task->possibleSimilarAnswers()->with(['task', 'answer', 'approver'])->get();

            // Group the collection by 'answer_key'
            $groupedByAnswerKey = $possibleSimilarAnswers->groupBy('answer_key');

            // If needed, transform the grouped collection into a more suitable format
            $transformed = $groupedByAnswerKey->map(function ($items, $answerKey) {
                return [
                    'answer_key' => $answerKey,
                    'possible_keys' => $items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            // 'task_id' => $item->task_id,
                            // 'answer_id' => $item->answer_id,
                            'possible_key' => $item->possible_key,
                            'status' => $item->status,
                            'approver' => $item->approver
                        ];
                    })->toArray(),
                ];
            })->values();

            return response()->json([
                "status" => 200,
                "message" => "Success",
                "data" => $transformed,
            ], 200);

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

        // Fetch and sort all participants' answers based on their frequency
        $allParticipantsAnswers = ParticipantsAnswer::where('task_id', $taskId)
            ->whereNotNull('answer')
            ->pluck('answer')
            ->reject(function ($answer) {
                return is_null($answer);
            })
            ->countBy()
            ->sortDesc()
            ->keys();

        $response = [];
        foreach ($taskAnswers as $taskAnswer) {
            $normalizedKey = intval($taskAnswer->answer);

            $similarAnswers = ParticipantsAnswer::where('task_id', $taskId)
                ->whereNotNull('answer')
                ->select('*', DB::raw('CAST(answer AS UNSIGNED) as numeric_answer'))
                ->get()
                ->filter(function ($participantAnswer) use ($normalizedKey) {
                    return intval($participantAnswer->numeric_answer) === $normalizedKey;
                })
                ->pluck('answer')
                ->unique();

            // Combine similarAnswers with the sorted allParticipantsAnswers, remove duplicates
            $combinedAnswers = $similarAnswers
                ->merge($allParticipantsAnswers)
                ->unique()
                ->values();

            $response[] = [
                'task_id' => $taskId,
                'answer_id' => $taskAnswer->id,
                'answer_key' => $taskAnswer->answer,
                'possible_keys' => $combinedAnswers->all() // Combined, unique, and sorted possible keys
            ];
        }

        return $response;
    }

    public function approvePossibleAnswers(Request $request)
    {
        $validStatuses = [
            PossibleSimilarAnswer::STATUS_WAITING_INPUT,
            PossibleSimilarAnswer::STATUS_APPROVED,
            PossibleSimilarAnswer::STATUS_DECLINED,
        ];

        $request->validate([
            '*.answer_id' => 'required|integer|exists:possible_similar_answers,id',
            '*.status' => ['required', 'string', Rule::in($validStatuses)]
        ]);

        $responses = [];

        foreach ($request->all() as $answerUpdate) {
            $possibleAnswer = PossibleSimilarAnswer::findOrFail($answerUpdate['answer_id']);
            $possibleAnswer->status = $answerUpdate['status'];
            $possibleAnswer->approved_by = Auth::id();
            $possibleAnswer->approved_at = now();
            $possibleAnswer->save();
            $responses[] = $possibleAnswer;
        }
        return response()->json([
            'message' => 'Possible similar answers status updated successfully.',
            'status' => 200
        ], 200);
    }
}
