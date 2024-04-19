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

    public function getTaskPossibleSimilarAnswers(Tasks $task, Request $request)
    {
        $request->validate([
            'level_id' => 'required|integer|exists:competition_levels,id',
        ]);

        try {
            $levelId = $request->level_id;

            $answersData = $this->fetchSimilarAnswersForTask($task->id);
            $taskId = $answersData['task_id'];
            $answerKey = trim($answersData['answer_key']);
            $answerId = $answersData['answer_id'] ?? null;

            // Remove correct answer key for similar answers
            $answersData['possible_keys'] = collect($answersData['possible_keys'])
                ->reject(function ($value, $key) use ($answerKey) {
                    return $key === $answerKey;
                })
                ->all();

            $possibleKeys = $answersData['possible_keys'];

            // Update or create records based on modified data
            foreach ($possibleKeys as $possibleKey => $participants) {
                PossibleSimilarAnswer::updateOrCreate([
                    'task_id' => $taskId,
                    'level_id' => $levelId,
                    'answer_id' => $answerId,
                    'answer_key' => $answerKey,
                    'possible_key' => $possibleKey,
                ], [
                    'participants_indices' => $participants
                ]);
            }

            // Delete records that are not in the updated list
             PossibleSimilarAnswer::where('level_id', $levelId)
                ->where('task_id', $taskId)
                ->whereNotIn('possible_key', array_map('strval', array_keys($possibleKeys)))
                ->delete();

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

        if ($isMCQ) {
            // Find the correct answer's position
            $correctAnswerPosition = $task->taskAnswers()
                ->where('answer', '1') // '1' indicates the correct answer
                ->value('position');

            // Fetch the label of the correct answer using its position
            $correctAnswerData = $task->taskAnswers()
                ->join('task_labels', 'task_labels.task_answers_id', '=', 'task_answers.id')
                ->where('task_answers.position', $correctAnswerPosition)
                ->select('task_answers.id as answer_id', 'task_labels.content as content')
                ->first();

            $correctAnswerId = $correctAnswerData->answer_id;
            $correctAnswerLabel = $correctAnswerData->content;


            // Gather unique participant answers for the task with their indices
            $uniqueParticipantAnswers = ParticipantsAnswer::where('task_id', $taskId)
                ->whereNotNull('answer')
                ->select('answer', 'participant_index')
                ->get()
                ->groupBy('answer')
                ->mapWithKeys(function ($items, $key) {
                    return [$key => $items->pluck('participant_index')->all()];
                });

            // Return a single array with the correct answer_key and all unique participant answers
            return [
                'task_id' => $taskId,
                'answer_key' => $correctAnswerLabel,
                'answer_id' => $correctAnswerId,
                'possible_keys' => $uniqueParticipantAnswers->all()
            ];
        }


        // Handle non-MCQ tasks as before
        $allParticipantsAnswers = ParticipantsAnswer::where('task_id', $taskId)
            ->whereNotNull('answer')
            ->select('answer', 'participant_index')
            ->get()
            ->groupBy('answer')
            ->mapWithKeys(function ($items, $key) {
                return [$key => $items->pluck('participant_index')->all()];
            });

        $taskAnswer = $task->taskAnswers[0];
        if ($taskAnswer->answer !== null) {
            $normalizedKey = intval($taskAnswer->answer);
            $similarAnswers = ParticipantsAnswer::where('task_id', $taskId)
                ->whereNotNull('answer')
                ->select('answer', 'participant_index', DB::raw('CAST(answer AS UNSIGNED) as numeric_answer'))
                ->get()
                ->filter(function ($participantAnswer) use ($normalizedKey) {
                    return intval($participantAnswer->numeric_answer) === $normalizedKey;
                })
                ->groupBy('answer')
                ->mapWithKeys(function ($items, $key) {
                    return [$key => $items->pluck('participant_index')->all()];
                });

            $combinedAnswers = $similarAnswers->union($allParticipantsAnswers);

            return [
                'task_id' => $taskId,
                'answer_id' => $taskAnswer->id,
                'answer_key' => $taskAnswer->answer,
                'possible_keys' => $combinedAnswers->all()
            ];
        }
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
