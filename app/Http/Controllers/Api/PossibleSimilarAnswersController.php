<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use App\Models\PossibleSimilarAnswer;
use App\Models\Tasks;
use App\Models\TasksAnswers;
use App\Models\UpdatedAnswer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Svg\Tag\Rect;

class PossibleSimilarAnswersController extends Controller
{
    public function getCompetitionLevels(Competition $competition)
    {
        try {
            $competition = $competition->load('levels');
            return response()->json([
                "status" => 200,
                "message" => "Success",
                "data" => [
                    'name' => $competition->name,
                    'levels' => $competition->levels
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Internal Server Error {$e->getMessage()}",
                "error"     => strval($e)
            ], 500);
        }
    }

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

            $correctAnswerParticipants = [];
            if (isset($answersData['possible_keys'][$answerKey])) {
                $correctAnswerParticipants = $this->getPartiticpantsForCorrectAnswer($answersData['possible_keys'][$answerKey]);
            }

            // Remove correct answer key for similar answers

            $possibleKeys = $answersData['possible_keys'];

            // Update or create records based on modified data
            foreach ($possibleKeys as $possibleKey => $participantsAnswers) {
                PossibleSimilarAnswer::updateOrCreate([
                    'task_id' => $taskId,
                    'level_id' => $levelId,
                    'possible_key' => strval($possibleKey ?? ''),
                ], [
                    'answer_key' => $answerKey,
                    'participants_answers_indices' => $participantsAnswers,
                ]);
            }


            // Delete
            PossibleSimilarAnswer::where('level_id', $levelId)
                ->where('task_id', $taskId)
                ->where('possible_key', $answerKey)
                ->delete();

            // Delete records that are not in the updated list
            PossibleSimilarAnswer::where('level_id', $levelId)
                ->where('task_id', $taskId)
                ->whereNotIn('possible_key', array_map('strval', array_keys($possibleKeys)))
                ->delete();

            $orderedPossibleKeys = $task->possibleSimilarAnswers()
                ->with(['approver'])
                ->get()
                ->map(function ($answer) {
                    return [
                        'id' => $answer->id,
                        'possible_key' => $answer->possible_key,
                        'status' => $answer->status,
                        'approver' => $answer->approver,
                    ];
                })
                ->sortBy('possible_key')
                ->values();

            return response()->json([
                "status" => 200,
                "message" => "Success",
                "data" => [[
                    'answer_key' => $answerKey,
                    'correct_answer_participants' => $correctAnswerParticipants,
                    'possible_keys' => $orderedPossibleKeys
                ]],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => $e->getMessage(),
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
            if (!$correctAnswerPosition) {
                throw new Exception('There\'s no configured answer for this task');
            }

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
                ->select('answer', 'id')
                ->get()
                ->groupBy('answer')
                ->mapWithKeys(function ($items, $key) {
                    return [$key => $items->pluck('id')->all()];
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
            ->select('answer', 'id')
            ->get()
            ->groupBy('answer')
            ->mapWithKeys(function ($items, $key) {
                return [$key => $items->pluck('id')->all()];
            });

        $taskAnswer = $task->taskAnswers[0];
        if ($taskAnswer->answer === null) {
            throw new Exception('There\'s no configured answer for this task');
        }
        $normalizedKey = intval($taskAnswer->answer);
        $similarAnswers = ParticipantsAnswer::where('task_id', $taskId)
            // ->whereNotNull('answer')
            ->select('answer', 'id', DB::raw('CAST(answer AS UNSIGNED) as numeric_answer'))
            ->get()
            ->filter(function ($participantAnswer) use ($normalizedKey) {
                return intval($participantAnswer->numeric_answer) === $normalizedKey;
            })
            ->groupBy('answer')
            ->mapWithKeys(function ($items, $key) {
                return [$key => $items->pluck('id')->all()];
            });

        $combinedAnswers = $similarAnswers->union($allParticipantsAnswers);

        return [
            'task_id' => $taskId,
            'answer_id' => $taskAnswer->id,
            'answer_key' => $taskAnswer->answer,
            'possible_keys' => $combinedAnswers->all()
        ];
    }

    protected function getPartiticpantsForCorrectAnswer($participantsAnswersIndices)
    {
        if (empty($participantsAnswersIndices)) {
            return collect();
        }

        $allParticipants = collect();

        foreach ($participantsAnswersIndices as $answerIndex) {
            $participants = Participants::with(['country', 'school', 'competition_organization'])
                ->whereIn('index_no', function ($query) use ($answerIndex) {
                    $query->select('participant_index')
                        ->from('participant_answers')
                        ->where('id', $answerIndex);
                })->get()->each(function ($participant) use ($answerIndex) {
                    $participant->participant_answer_id = $answerIndex;
                });

            $allParticipants = $allParticipants->merge($participants);
        }

        return $allParticipants;
    }

    public function getTaskPossibleSimilarParticipants($answerId)
    {
        $possibleSimilarAnswer = PossibleSimilarAnswer::findOrFail($answerId);
        $participants = $possibleSimilarAnswer->participants();
        return response()->json([
            "status" => 200,
            "message" => "Success",
            'data' => $participants
        ], 200);
    }

    public function updateParticipantAnswer(Request $request)
    {
        $request->validate([
            'answer_id' => 'required|array|min:1',
            'answer_id.*' => 'exists:participant_answers,id',
            'new_answer' => 'required',
        ]);

        $newAnswer = $request->new_answer;
        $reason = $request->reason;
        $updateResults = [];

        foreach ($request->answer_id as $participantAnswerId) {
            $participantAnswer = ParticipantsAnswer::find($participantAnswerId);
            if (!$participantAnswer) {
                continue;  // Skip if not found
            }

            $oldAnswer = $participantAnswer->answer;
            if ($oldAnswer === $newAnswer) {
                $updateResults[$participantAnswerId] = 'No update needed as the answer has not changed.';
                continue;
            }

            $updateRecord = new UpdatedAnswer([
                'level_id' => $participantAnswer->level_id,
                'task_id' => $participantAnswer->task_id,
                'answer_id' => $participantAnswerId,
                'participant_index' => $participantAnswer->participant_index,
                'old_answer' => $oldAnswer,
                'new_answer' => $newAnswer,
                'reason' => $reason,
                'updated_by' => auth()->id()
            ]);

            $updateRecord->save();

            $participantAnswer->answer = $newAnswer;
            $participantAnswer->save();
            $updateResults[$participantAnswerId] = 'Participant answer updated successfully.';
        }

        return response()->json([
            'status' => 200,
            'results' => $updateResults,
        ], 200);
    }


    public function getAnswerUpdates(Tasks $task, Request $request)
    {
        $request->validate([
            'level_id' => 'required|integer|exists:competition_levels,id',
        ]);

        $levelId = $request->level_id;
        $taskId = $task->id;

        $updates = UpdatedAnswer::with('updated_by')
            ->where('level_id', $levelId)
            ->where('task_id', $taskId)
            ->get();

        return response()->json([
            'status' => 200,
            'data' => $updates
        ], 200);
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
