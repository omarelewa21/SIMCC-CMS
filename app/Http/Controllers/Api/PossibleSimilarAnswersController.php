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
                    // if ($task->answer_type != 'mcq') {
                    $levelTasks[] = [
                        'task_id' => $task->id,
                        'task_name' => $task->identifier,
                        'task_type' => $task->answer_type,
                        'collection_id' => $level->collection->id,
                        'collection_name' => $level->collection->name,
                        'section_id' => $section->id,
                        'task_tag' => $task->identifier . ' - Section ' . $sectionLetter . ' Question ' . $taskNumber
                    ];
                    // }
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
            $isMCQ = $task->answer_type == 'mcq';

            // Process MCQ tasks separately
            if ($isMCQ && !empty($similarAnswers)) {
                $similarAnswer = $similarAnswers[0]; // Assuming MCQ tasks will have a single entry in $similarAnswers

                // Filter and process possible keys for MCQ task
                $filteredPossibleKeys = collect($similarAnswer['possible_keys'])->reject(function ($possibleKey) use ($similarAnswer) {
                    return trim($possibleKey) === (isset($similarAnswer['answer_key']) ? trim($similarAnswer['answer_key']) : null);
                })->values();

                foreach ($filteredPossibleKeys as $key) {
                    $answerData[] = [
                        'task_id' => $task->id,
                        'answer_id' => $similarAnswer['answer_id'] ?? null, // This might be null for MCQ tasks
                        'answer_key' => $similarAnswer['answer_key'],
                        'possible_key' => $key,
                    ];
                }

                // Skip further processing for MCQ tasks
            } else {
                // Handle non-MCQ tasks
                foreach ($similarAnswers as $similarAnswer) {
                    $filteredPossibleKeys = collect($similarAnswer['possible_keys'])->reject(function ($possibleKey) use ($similarAnswer) {
                        return trim($possibleKey) === (isset($similarAnswer['answer_key']) ? trim($similarAnswer['answer_key']) : null);
                    })->values();

                    foreach ($filteredPossibleKeys as $key) {
                        $answerData[] = [
                            'task_id' => $task->id,
                            'answer_id' => $similarAnswer['answer_id'] ?? null,
                            'answer_key' => $similarAnswer['answer_key'],
                            'possible_key' => $key,
                        ];
                    }
                }
            }

            // First, group all keys by task_id for targeted deletion.
            $tasksWithKeys = collect($answerData)->groupBy('task_id')->map(function ($groupedItems) {
                return $groupedItems->map(function ($item) {
                    return [
                        'answer_id' => $item['answer_id'],
                        'possible_key' => (string) $item['possible_key'],
                    ];
                });
            });

            // Update or create records.
            foreach ($answerData as $data) {
                PossibleSimilarAnswer::updateOrCreate([
                    'task_id' => $data['task_id'],
                    'answer_id' => $data['answer_id'],
                    'possible_key' => $data['possible_key'],
                ], $data);
            }

            // Delete records that are not in the answerData for each specific task_id.
            foreach ($tasksWithKeys as $taskId => $keys) {
                PossibleSimilarAnswer::where('task_id', $taskId)
                    ->whereNot(function ($query) use ($keys) {
                        foreach ($keys as $key) {
                            $query->orWhere(function ($orQuery) use ($key) {
                                $orQuery->where('answer_id', $key['answer_id'])
                                    ->where('possible_key', $key['possible_key']);
                            });
                        }
                    })
                    ->delete();
            }

            // Fetch updated possible similar answers and transform the collection
            $possibleSimilarAnswers = $task->possibleSimilarAnswers()
                ->with(['task', 'answer', 'approver'])
                ->get();

            $transformed = $possibleSimilarAnswers->groupBy('answer_key')
                ->map(function ($items, $answerKey) {
                    $sortedItems = $items->sortBy('possible_key'); // Sort items within the group
                    return [
                        'answer_key' => $answerKey,
                        'possible_keys' => $sortedItems->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'possible_key' => $item->possible_key,
                                'status' => $item->status,
                                'approver' => optional($item->approver)->name, // assuming 'approver' is an object with a 'name' attribute
                            ];
                        })->values(),
                    ];
                })->values();

            return response()->json([
                "status" => 200,
                "message" => "Success",
                "data" => $transformed,
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
        $task = Tasks::with('taskAnswers')->find($taskId);
        $isMCQ = $task->answer_type == 'mcq';

        // For MCQ tasks, identify the correct answer's label as the answer_key
        if ($isMCQ) {
            // Find the correct answer's position
            $correctAnswerPosition = $task->taskAnswers()
                ->where('answer', '1') // '1' indicates the correct answer
                ->value('position');

            // Fetch the label of the correct answer using its position
            $correctAnswerLabel = $task->taskAnswers()
                ->join('task_labels', 'task_labels.task_answers_id', '=', 'task_answers.id')
                ->where('task_answers.position', $correctAnswerPosition)
                ->value('task_labels.content'); // Directly fetching the content as 'answer_key'

            // Gather unique participant answers for the task
            $uniqueParticipantAnswers = ParticipantsAnswer::where('task_id', $taskId)
                ->whereNotNull('answer')
                ->pluck('answer')
                ->unique()
                ->values();

            // Return a single array with the correct answer_key and all unique participant answers
            return [
                [
                    'task_id' => $taskId,
                    'answer_key' => $correctAnswerLabel, // The label of the correct answer
                    'possible_keys' => $uniqueParticipantAnswers->all()
                ]
            ];
        }

        // Handle non-MCQ tasks as before
        $response = [];
        $allParticipantsAnswers = ParticipantsAnswer::where('task_id', $taskId)
            ->whereNotNull('answer')
            ->pluck('answer')
            ->reject(function ($answer) {
                return is_null($answer);
            })
            ->countBy()
            ->sortDesc()
            ->keys();

        foreach ($task->taskAnswers as $taskAnswer) {
            if ($taskAnswer->answer !== null) {
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
                    'possible_keys' => $combinedAnswers->all()
                ];
            }
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