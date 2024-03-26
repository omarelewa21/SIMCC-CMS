<?php

namespace App\Http\Controllers\Api;

use App\Exports\AnswersReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Competition\ParticipantAnswersDeleteRequest;
use App\Http\Requests\getParticipantListRequest;
use App\Http\Requests\Participant\AnswerReportRequest;
use App\Models\Competition;
use App\Models\ParticipantsAnswer;
use App\Models\PossibleAnswer;
use App\Models\Tasks;
use App\Models\TasksAnswers;
use App\Services\Competition\ParticipantAnswersListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ParticipantAnswersController extends Controller
{
    public function list(Competition $competition, getParticipantListRequest $request)
    {
        try {
            $participantAnswersService = new ParticipantAnswersListService($competition, $request);
            $participantAnswersList = $participantAnswersService->getList();

            return response()->json([
                "status"        => 200,
                "message"       => "Success",
                'competition'   => $competition->name,
                "filterOptions" => $participantAnswersService->getFilterOptions(),
                "headers"       => $participantAnswersService->getHeaders($participantAnswersList),
                "data"          => $participantAnswersList
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Internal Server Error {$e->getMessage()}",
                "error"     => strval($e)
            ], 500);
        }
    }

    public function delete(Competition $competition, ParticipantAnswersDeleteRequest $request)
    {
        try {
            $participantAnswersService = new ParticipantAnswersListService($competition, $request);
            $participantAnswersService->deleteParticipantsAnswers();
            return response()->json([
                "status"    => 200,
                "message"   => "Success",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Internal Server Error {$e->getMessage()}",
                "error"     => strval($e)
            ], 500);
        }
    }

    public function answerReport(Competition $competition, AnswerReportRequest $request)
    {
        try {
            $reportName = ParticipantAnswersListService::getAnswersReportName($competition);

            if (Storage::disk('local')->exists($reportName)) {
                Storage::disk('local')->delete($reportName);
            }

            if (Excel::store(new AnswersReportExport($competition, $request), $reportName)) {
                $file = Storage::get($reportName);
                Storage::disk('local')->delete($reportName);
                $response = response()->make($file, 200);
                $response->header('Content-Type', 'application/' . pathinfo($reportName, PATHINFO_EXTENSION));
                $response->header('Content-Disposition', 'attachment; filename="' . $reportName . '"');
                return $response;
            }

            return response()->json([
                'status'    => 500,
                'message'   => 'Failed to generate cheating list'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Internal Server Error {$e->getMessage()}",
                "error"     => strval($e)
            ], 500);
        }
    }

    // public function getCompetitionLevelsAndTasksWithSimilarAnswers(Competition $competition)
    // {

    //     $levels = $competition->levels()
    //         ->with('collection.sections')
    //         ->get();

    //     $levels->each(function ($level) {
    //         if (isset($level->collection) && isset($level->collection->sections)) {
    //             $level->collection->sections->each(function ($section) {
    //                 $tasks = $section->section_task;
    //                 $filteredTasks = $tasks->reject(function ($task) {
    //                     return $task->answer_type == 1;
    //                 });
    //                 $filteredTasks->each(function ($task) use ($section) {
    //                     $similarAnswers = $this->fetchSimilarAnswersForTask($task->id);
    //                     $task->similar_answers = $similarAnswers;
    //                 });
    //                 $section->setRelation('section_task', $filteredTasks);
    //             });
    //         }
    //     });

    //     return response()->json($levels);
    // }

    public function getCompetitionLevelsAndTasksWithSimilarAnswers(Competition $competition, Request $request)
    {
        try {
            $possibleAnswersQuery = $competition->possibleAnswers()
                ->with(['level', 'task', 'competition','collection', 'section']);
            $possibleAnswers = $possibleAnswersQuery->get();
            if ($possibleAnswers->isEmpty()) {
                $levels = $competition->levels()->with('collection.sections')->get();
                foreach ($levels as $level) {
                    if (isset($level->collection) && isset($level->collection->sections)) {
                        foreach ($level->collection->sections as $section) {
                            $tasks = $section->section_task;
                            $filteredTasks = $tasks->reject(function ($task) {
                                return $task->answer_type == 1;
                            });

                            foreach ($filteredTasks as $task) {
                                $similarAnswers = $this->fetchSimilarAnswersForTask($task->id);
                                foreach ($similarAnswers as $similarAnswer) {
                                    $filteredPossibleKeys = collect($similarAnswer['possible_keys'])->reject(function ($possibleKey) use ($similarAnswer) {
                                        return trim($possibleKey) === trim($similarAnswer['answer_key']);
                                    })->values();

                                    // Only include the node if there are any filteredPossibleKeys left
                                    if ($filteredPossibleKeys->isNotEmpty()) {
                                        $answerData = [
                                            'competition_id' => $competition->id,
                                            'level_id' => $level->id,
                                            'collection_id' => $level->collection->id,
                                            'section_id' => $section->id,
                                            'task_id' => $task->id,
                                            'answer_id' => $similarAnswer['answer_id'],
                                            'answer_key' => $similarAnswer['answer_key'],
                                            'possible_keys' => $filteredPossibleKeys->all(),
                                        ];;
                                        PossibleAnswer::create($answerData);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $perPage = $request->limits ?? 10;
            $possibleAnswers = $possibleAnswersQuery->paginate($perPage);
            return response()->json([
                "status" => 200,
                "message" => "Success",
                "data" => $possibleAnswers
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
}
