<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DomainsTags;
use App\Models\Languages;
use App\Models\Tasks;
use App\Rules\CheckMultipleVaildIds;
use App\Helpers\General\CollectionHelper;
use App\Http\Requests\Task\DeleteTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskAnswerRequest;
use App\Http\Requests\Task\UpdateTaskContentRequest;
use App\Http\Requests\Task\UpdateTaskRecommendationsRequest;
use App\Http\Requests\Task\UpdateTaskSettingsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Tasks\CreateTaskService;
use App\Services\Tasks\DuplicateTaskService;
use App\Services\Tasks\UpdateTaskService;

class TasksController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(StoreTaskRequest $request)
    {
        DB::beginTransaction();
        try {
            (new CreateTaskService())->create($request->all());
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks create was unsuccessful" . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => 'Tasks created successfully'
        ]);
    }

    public function list(Request $request)
    {
        $vaildate = $request->validate([
            'id'         => 'integer',
            'identifier' => 'regex:/^[\_\w-]*$/',
            'lang_id'    => new CheckMultipleVaildIds(new Languages()),
            'tag_id'     => new CheckMultipleVaildIds(new DomainsTags()),
            'status'     => 'string|max:255',
            'limits'     => 'integer',
            'page'       => 'integer',
            'search'     => 'string|max:255'
        ]);

        try {
            $eagerload = auth()->user()->role_id == 0 || auth()->user()->role_id == 1 ? ['taskAnswers:id,task_id,answer,position', 'taskAnswers.taskLabels:id,task_answers_id,lang_id,content'] : [];
            $hide = isset($request->id) || isset($request->identifier) ? [] : ['image'];

            $limits = $request->limits ? $request->limits : 10;
            $searchKey = isset($vaildate['search']) ? $vaildate['search'] : null;

            $eagerload = [
                ...$eagerload,
                'tags:id,is_tag,domain_id,name',
                'Moderation:moderation_id,moderation_date,moderation_by_userid',
                'Moderation.user:id,username',
                'gradeDifficulty:gradeDifficulty_id,grade,difficulty',
            ];

            $hide = [
                ...$hide,
                'created_by_userid',
                'last_modified_userid'
            ];

            $taskModel = Tasks::with($eagerload)
                ->AcceptRequest(['id', 'status', 'identifier'])
                ->where('tasks.status', '!=', 'deleted');

            if (!in_array(auth()->user()->role_id, [0, 1])) { //if role is not admin, filter by their user respective account
                $taskModel = $taskModel->where('created_by_userid', '!=', auth()->user()->id);
            }

            $returnFiltered = Tasks::applyFilter($taskModel, $request)->get()->makeHidden($hide);

            $taskCollection = collect($returnFiltered);

            $taskTitle = $taskCollection->map(function ($item) {
                foreach ($item['languages'] as $row) {
                    $noTitle = ["id" => $row['id'], "name" => $row['name']];
                    $withTitle = array_merge($noTitle, ["title" => $row['task_title']]);
                    return ["no_title" => $noTitle, "with_title" => $withTitle];
                }
            });

            /**
             * Lists of availabe filters
             */
            $availTaskStatus = $taskCollection->map(function ($item) {
                return $item['status'];
            })->unique()->values();

            $availLang = $taskTitle->map(function ($item, $key) {
                if (isset($item['no_title'])) {
                    return $item['no_title'];
                }
            })->unique()->values();

            $availDomainType = $taskCollection->map(function ($item) {
                $temp = [];
                foreach ($item->toArray()['tags'] as $row) {
                    if ($row['domain_id'] && !$row['is_tag']) {
                        $temp[] = ["id" => $row['id'], "name" => $row['name']];
                    }
                }
                return $temp;
            })->filter()->collapse()->unique()->values();

            $availTagType = $taskCollection->map(function ($item) {
                $temp = [];
                foreach ($item->toArray()['tags'] as $row) {
                    if ($row['is_tag']) {
                        $temp[] = ["id" => $row['id'], "name" => $row['name']];
                    }
                }
                return $temp;
            })->filter()->collapse()->unique()->values();

            $taskCollection = $taskCollection->map(function ($item, $key) use ($taskTitle) { //map title into collection row
                $item['title'] = $taskTitle[$key]['with_title']['title'];
                return $item;
            });

            if ($request->has('lang_id') || $request->has('tag_id')) {
                /** addition filtering done in collection**/

                $taskCollection = $this->filterCollectionList(
                    $taskCollection,
                    [
                        "1,languages" => $request->lang_id ?? false, // 0 = non-nested, 1 = nested
                        "1,tags" =>  $request->tag_id ?? false
                    ]
                );
            }
            //            dd($taskCollection->toArray());
            //            dd($availForSearch);

            $availForSearch = array("identifier", "title", "description");
            $taskList = CollectionHelper::searchCollection($searchKey, $taskCollection, $availForSearch, $limits);
            $data = array("filterOptions" => ['status' => $availTaskStatus, 'lang' => $availLang, 'domains' => $availDomainType, 'tags' => $availTagType], 'taskLists' => $taskList);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        } catch (\Exception $e) {
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => $e->getMessage()
            ], 500);
        }
    }

    public function update_settings(UpdateTaskSettingsRequest $request)
    {
        try {
            $task = Tasks::find($request->id);
            if ($task->allowedToUpdateAll()) {
                $task->identifier = $request->identifier;
                $task->taskContents->first()->task_title = $request->title;
            }
            $task->description = $request->description;
            $task->solutions = $request->solutions;
            $task->taskImage()->update(['image_string' => $request->image]);
            $task->taskTags()->sync($request->tag_id);

            $task->push();

            return response()->json([
                "status"  => 200,
                "message" => "Tasks update successful"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 200,
                "message"   => "Tasks update unsuccessful" . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function update_content(UpdateTaskContentRequest $request)
    {
        try {
            DB::beginTransaction();

            foreach ($request->taskContents as $content) {
                $task = Tasks::findOrFail($request->id)
                    ->taskContents()
                    ->where('language_id', $content['lang_id'])
                    ->first();

                if ($content['title'] != $task->title) {
                    $task->task_title = $content['title'];
                }
                $task->content = $content['content'];
                $task->status = auth()->user()->role_id == 0 || auth()->user()->role_id == 1 ? 'active' : 'pending moderation';
                $task->save();
            }

            if ($request->get('re-moderate')) {
                Tasks::findOrFail($request->id)
                    ->taskContents()
                    ->where('language_id', '!=', env('APP_DEFAULT_LANG', 171))
                    ->update(['status' => 'pending moderation']);
            }

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "Tasks update successful"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 200,
                "message"   => "Tasks update unsuccessful" . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function update_recommendation(UpdateTaskRecommendationsRequest $request)
    {
        try {
            Tasks::find($request->id)->gradeDifficulty()->delete();
            for ($i = 0; $i < count($request->recommended_grade); $i++) {
                Tasks::find($request->id)->gradeDifficulty()->create(
                    [
                        "grade"         => $request->recommended_grade[$i],
                        "difficulty"    => $request->recommended_difficulty[$i],
                    ]
                );
            }
            return response()->json([
                "status" => 200,
                "message" => "Tasks update successful"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks update unsuccessful" . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function update_answer(UpdateTaskAnswerRequest $request)
    {
        DB::beginTransaction();
        try {
            (new UpdateTaskService())->updateAnswer($request->all());
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks update unsuccessful " . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "Tasks update successful"
        ]);
    }

    public function delete(DeleteTaskRequest $request)
    {
        DB::beginTransaction();

        try {
            foreach ($request->id as $task_id) {
                Tasks::find($task_id)->delete();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks deletetion was unsuccessful" . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status" => 200,
            "message" => "Tasks deleted successfully"
        ]);
    }

    public function duplicate(Tasks $task)
    {
        DB::beginTransaction();
        try {
            (new DuplicateTaskService($task))->duplicate();
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks duplicate was unsuccessful" . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }
        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => 'Tasks duplicated successfully'
        ]);
    }
    public function verify(Tasks $task)
    {
        if (!auth()->user()->hasRole(['super admin', 'admin'])) {
            return response()->json([
                "status"  => 403,
                "message" => "Only admins can verify collection"
            ],403);
        }

        $task->status = Tasks::STATUS_VERIFIED;
        $task->save();
        return response()->json([
            "status"  => 200,
            "message" => "task verified successfully"
        ]);
    }
}
