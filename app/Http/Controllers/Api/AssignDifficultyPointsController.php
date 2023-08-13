<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collections;
use App\Models\Competition;
use App\Models\CompetitionLevels;
use App\Models\CompetitionRounds;
use App\Models\CompetitionTaskDifficulty;
use App\Models\CompetitionTasksMark;
use App\Models\TaskDifficulty;
use App\Models\TaskDifficultyGroup;
use App\Models\TaskDifficultyVerification;
use App\Models\TasksAnswers;
use App\Models\Tasks;
use App\Rules\CheckCompetitionLevelExist;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignDifficultyPointsController extends Controller
{
    public function list(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:competition,id',
        ]);

        try {
            $competition = Competition::AcceptRequest(['id'])->with(['rounds.levels.collection.sections', 'taskDifficulty'])->find($validated['id']);
            $TaskDifficultyGroupId = $competition->difficulty_group_id;
            $difficultyList = TaskDifficultyGroup::whereId($TaskDifficultyGroupId)->exists() ? TaskDifficultyGroup::with('difficulty')->find($TaskDifficultyGroupId)->toArray() : [];
            $competitionRounds = CompetitionRounds::where('competition_id', $competition->id)->get()->map(function ($round) {

                $round['levels'] = CompetitionLevels::where('round_id', $round->id)->get()->map(function ($level) {
                    $CompetitionTasksMark = [];
                    CompetitionTasksMark::where('level_id', $level->id)->get()->each(function ($row) use (&$CompetitionTasksMark) {
                        $task_answers_id = is_numeric($row->task_answers_id) ? intVal($row->task_answers_id) : json_decode($row->task_answers_id)[0];
                        $task_id = TasksAnswers::find($task_answers_id)->task->id;
                        $CompetitionTasksMark[$task_id][] = $row->toArray();
                    });

                    $CompetitionTasksDifficulty = [];
                    CompetitionTaskDifficulty::where('level_id', $level->id)->get()
                        ->each(function ($row) use (&$CompetitionTasksDifficulty) {
                            $row['difficulty_id'] === null ? null : TaskDifficulty::find($row['difficulty_id'])->name;
                            $CompetitionTasksDifficulty[$row['task_id']] = ['id' => $row['id'], 'difficulty' => $row['difficulty'], 'wrong_marks' => $row['wrong_marks'], 'blank_marks' => $row['blank_marks']];
                        });

                    $collection = Collections::with(['sections'])->where('id', $level->collection_id)->first();

                    $collectionSection = $collection->sections
                        ->map(function ($section) use ($CompetitionTasksMark, $CompetitionTasksDifficulty) {
                            return $section->section_task->map(function ($task) use ($CompetitionTasksMark, $CompetitionTasksDifficulty, $section) {
                                $section = [
                                    'id'                => $task->id,
                                    'languages'         => $task->languages->first()->name,
                                    'name'              => $task->languages->first()->task_title,
                                    'identifier'        => $task->identifier,
                                    'answer_structure'  => $task->answer_structure,
                                    'task_difficulty'   => $CompetitionTasksDifficulty[$task->id]['difficulty'],
                                    'task_wrong'        => $CompetitionTasksDifficulty[$task->id]['wrong_marks'],
                                    'task_blank'        => $CompetitionTasksDifficulty[$task->id]['blank_marks'],
                                    'task_marks'        => $CompetitionTasksMark[$task->id]
                                ];
                                return $section;
                            })->toArray();
                        })->toArray();

                    $level['collections'] = [
                        'id'        => $collection->id,
                        'name'      => $collection->name,
                        'section'   => $collectionSection
                    ];

                    return [
                        'id'            => $level->id,
                        'name'          => $level->name,
                        'grades'        => $level->grades,
                        'collections'   => $level->collections
                    ];
                });

                return [
                    'id'        => $round->id,
                    'name'      => $round->name,
                    'levels'    => $round->levels
                ];
            });

            $competitionAssignTaskDifficultyMarks =  [
                'id' => $competition->id,
                'competition_name' => $competition->name,
                'rounds' => $competitionRounds->toArray()
            ];

            $data = array("difficultyList" => $difficultyList, "competitionTask" => $competitionAssignTaskDifficultyMarks);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Retrieve competition retrieve unsuccessful" . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Retrieve competition retrieve unsuccessful" . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $competitionId = $request->validate([
            'competition_id' => ["required", Rule::exists('competition', 'id')->where('status', 'active')],
        ])['competition_id'];

        $levelId = $request->validate([
            'level_id' => ["required", new CheckCompetitionLevelExist($competitionId)],
        ])['level_id'];

        $difficulty = Competition::find($competitionId)->taskDifficulty->pluck('name')->toArray();

        $validate = $request->validate([
            'tasks' => "required|array",
            'tasks.*.task_id' => ["required", Rule::exists("competition_task_difficulty", "task_id")->where("level_id", $levelId)],
            'tasks.*.difficulty' => ["required", "string", Rule::in($difficulty)],
            'tasks.*.wrong_marks' => 'required|integer',
            'tasks.*.blank_marks' => 'required|integer',
            'tasks.*.answers' => 'required|array',
            'tasks.*.answers.*.id' => ["required", Rule::exists('competition_tasks_mark', 'id')->where("level_id", $levelId)],
            'tasks.*.answers.*.min_marks' => 'nullable|integer',
            'tasks.*.answers.*.marks' => 'required|integer',
        ]);

        try {
            $insertDifficulty = collect(Arr::pull($validate, 'tasks'))->map(function ($task) use ($levelId, &$insertAnswers) {

                $taskStructure = Tasks::find($task['task_id'])->answer_structure;

                $insertAnswers[] = collect($task['answers'])->map(function ($answer) use ($levelId, $taskStructure) {
                    return [
                        ...$answer,
                        'task_structure' => $taskStructure,
                        'level_id' => $levelId
                    ];
                })->collapse()->toArray();

                //                unset($task['answers']);

                return [
                    ...$task,
                    'level_id' => $levelId,
                ];
            })->toArray();
            //            dd($insertAnswers);
            DB::beginTransaction();
            collect($insertDifficulty)->map(function ($row) use ($levelId) {
                $taskDifficulty = CompetitionTaskDifficulty::where(['level_id' => $levelId, 'task_id' => $row['task_id']])->first();
                $taskDifficulty->difficulty = $row['difficulty'];
                $taskDifficulty->wrong_marks = $row['wrong_marks'];
                $taskDifficulty->blank_marks = $row['blank_marks'];
                $taskDifficulty->save();
            });

            collect($insertAnswers)->map(function ($row) {
                $taskMarks = CompetitionTasksMark::find($row['id']);
                $taskMarks->min_marks = $row['task_structure'] == 'open' ? $row['min_marks'] : null;
                $taskMarks->marks = $row['marks'];
                $taskMarks->save();
            });

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "Update competition task marks successful."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Update competition task marks unsuccessful." . $e
            ]);
        }
    }

    public function verify(Request $request)
    {
        if (!auth()->user()->hasRole(['super admin', 'admin'])) {
            return response()->json([
                "status"  => 403,
                "message" => "Only admins can verify collection"
            ]);
        }

        $competitionId = $request->validate([
            'competition_id' => ["required", Rule::exists('competition', 'id')->where('status', 'active')],
        ])['competition_id'];

        $request->validate([
            'collection_id' => [
                'required',
                Rule::exists('collection', 'id')->where('status', '!=', Collections::STATUS_VERIFIED)
            ],
        ], [
            'collection_id.exists' => 'Collection must be verified.'
        ]);

        $levelId = $request->validate([
            'level_id' => ["required", new CheckCompetitionLevelExist($competitionId)],
        ])['level_id'];

        $taskDifficulty = TaskDifficultyVerification::where('competition_id', $competitionId)->where('level_id', $levelId)->where('round_id', $request->round_id)->first();
        if ($taskDifficulty) {
            return response()->json([
                "status"  => 403,
                "message" => "This difficulty and points is already verified"
            ]);
        }

        TaskDifficultyVerification::create(
            [
                'competition_id' => $competitionId,
                'level_id' => $levelId,
                'round_id' => $request->round_id,
                'is_verified' => true,
                'verified_by_userid' => auth()->user()->id
            ]
        );
        return response()->json(
            [
                'status' => 200,
                'message' => 'difficulty and points verified successfully'
            ]
        );
    }
}
