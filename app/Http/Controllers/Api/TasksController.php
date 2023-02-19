<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Tasks;
use App\Models\TasksAnswers;
use App\Models\TasksLabels;
use Illuminate\Support\Arr;
use App\Helpers\General\CollectionHelper;
use App\Http\Requests\tasks\ApproveTasksRequest;
use App\Http\Requests\tasks\DeleteTaskRequest;
use App\Http\Requests\tasks\RejectTasksRequest;
use App\Http\Requests\tasks\StoreTaskRequest;
use App\Http\Requests\tasks\TasksListRequest;
use App\Http\Requests\tasks\UpdateTaskAnswerRequest;
use App\Http\Requests\tasks\UpdateTaskContentRequest;
use App\Http\Requests\tasks\UpdateTaskRecommendationsRequest;
use App\Http\Requests\tasks\UpdateTaskSettingsRequest;
use App\Http\Services\TasksService;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class TasksController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(StoreTaskRequest $request)
    {
        try {
            DB::beginTransaction();

            $counter = 0;

            User::find(auth()->user()->id)->tasks()->createMany($request->all())->map(function ($row) use(&$counter, $request){
                $row = collect(array_merge($request->all()[$counter], $row->toArray()));

                //add image entry via polymorphic relationship (one to one)
                if ($row->has('image')) {
                    Tasks::find($row->get('id'))->taskImage()->create([
                        'image_string' => $row->get('image')
                    ]);
                }

                //add tag entry via polymorphic relationship (many to many)
                if ($row->has('tag_id')) {
                    Tasks::find($row->get('id'))->tags()->attach($row->get('tag_id'));
                }

                //add recommended difficulty entry via polymorphic relationship (one to many)
                if ($row->has('recommended_grade')) {
                    if (count($row->get('recommended_grade')) > 0) {
                        for ($i = 0; $i < count($request->all()[$counter]['recommended_grade']); $i++) {
                            Tasks::find($row->get('id'))->gradeDifficulty()->create(
                                [
                                    "grade" => $row->get('recommended_grade')[$i],
                                    "difficulty" => $row->get('recommended_difficulty')[$i],
                                ]);
                        }
                    }
                };

                //add task content
                Tasks::findOrFail($row->get('id'))->taskContents()->create([
                    'language_id' => env('APP_DEFAULT_LANG'),
                    'task_title' => $row->get('title'),
                    'content' => $row->get('content'),
                    'status' => auth()->user()->role_id == 0 || auth()->user()->role_id == 1 ? 'active' : 'pending moderation',
                    'created_by_userid' => auth()->user()->id
                ]);

                $answers = collect($row->get('answers'))->map(function ($answer, $key) use ($row) {
                    $temp = array([
                        'task_id' => $row->get('id'),
                        'lang_id' => env('APP_DEFAULT_LANG'),
                        'answer' => $answer,
                        'position' => $key + 1,
                    ]);
                    return $temp;

                })->toArray();
              

                // add task answers
                $labels = Tasks::find($row->get('id'))->taskAnswers()->createMany(Arr::collapse($answers))->pluck('id')->map(function ($answerId, $key) use ($row) {
                    $temp = array([
                        'task_answers_id' => $answerId,
                        'lang_id' => env('APP_DEFAULT_LANG'),
                        'content' => $row->get('labels')[$key] ?: '-',
                    ]);
                    return $temp;

                });

                // add labels for task answers
                TasksLabels::insert(Arr::collapse($labels));

                $counter++;
            });

            DB::commit();
            return response()->json([
                "status" => 200,
                "message" => 'Tasks created successfully'
            ]);

        }catch(\Exception $e){
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks create was unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function list(TasksListRequest $request)
    {
        try {
            $taskCollection = TasksService::getTaskListCollection($request);
            $filterOptions = TasksService::getTaskListFilterOptions($taskCollection, $request);
            $taskList = CollectionHelper::searchCollection(
                $request->has('search') ? $request->search : null,
                $taskCollection,
                array("identifier", "title", "description"),
                $request->has('limits') ? $request->limits : 10
            );
            return response()->json([
                "status"    => 200,
                "data"      => array(
                    "filterOptions" => $filterOptions,
                    'taskLists'     => $taskList
                )
            ]);
        } catch(\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "List fetching is not successfull",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function update_settings(UpdateTaskSettingsRequest $request)
    {
        try {
            $task = Tasks::find($request->id);
            if($task->allowedToUpdateAll()){
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
        }
        catch(\Exception $e){
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

            foreach($request->taskContents as $content) {
                $task = Tasks::findOrFail($request->id)
                    ->taskContents()
                    ->where('language_id',$content['lang_id'])
                    ->first();

                if($content['title'] != $task->title) {
                    $task->task_title = $content['title'];
                }
                $task->content = $content['content'];
                $task->status = auth()->user()->role_id == 0 || auth()->user()->role_id == 1 ? 'active' : 'pending moderation';
                $task->save();
            }

            if($request->get('re-moderate')) {
                Tasks::findOrFail($request->id)
                    ->taskContents()
                    ->where('language_id', '!=' ,env('APP_DEFAULT_LANG'))
                    ->update(['status' => 'pending moderation']);
            }

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "Tasks update successful"
            ]);
        }

        catch(\Exception $e){
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
        }
         catch(\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks update unsuccessful" . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
         }
    }

    public function update_answer(UpdateTaskAnswerRequest $request)
    {
        try {
            $task = Tasks::findOrFail($request->id);
            if($task->update($request->all())){
                $allAnswersId = TasksAnswers::where('task_id', $task->id)->pluck('id')->toArray();
                TasksLabels::whereIn('task_answers_id', $allAnswersId)->delete();
                TasksAnswers::where('task_id', $task->id)->delete();

                $answers = collect($request->answers)->map(function ($answer, $key) use($task){
                    return array([
                        'task_id'   => $task->id,
                        'lang_id'   => env('APP_DEFAULT_LANG'),
                        'answer'    => $answer,
                        'position'  => $key + 1,
                    ]);
                })->collapse();

                // add task answers
                $labels = $request->labels;
                $labels = Tasks::find($request->id)->taskAnswers()->createMany($answers)->pluck('id')
                    ->map(function ($answerId, $key) use($labels){
                        return array([
                            'task_answers_id'   => $answerId,
                            'lang_id'           => env('APP_DEFAULT_LANG'),
                            'content'           => $labels[$key],
                        ]);
                });

                // add labels for task answers
                TasksLabels::insert(Arr::collapse($labels));
                return response()->json([
                    "status" => 200,
                    "message" => "Tasks update successful"
                ]);
            } else {
                return response()->json([
                    "status" => 500,
                    "message" => "Tasks update unsuccessful"
                ], 500);
            }
        }
        catch(\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks update unsuccessful" . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
         }
    }

    public function delete(DeleteTaskRequest $request)
    {
        DB::beginTransaction();

        try {
            foreach($request->id as $task_id){
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
            "status"    => 200,
            "message"   => "Tasks deleted successfully"
        ]);
    }

    public function approve(ApproveTasksRequest $request)
    {
        try {
            Tasks::whereIn('id', $request->ids)->update([
                'status' => 'active'
            ]);
            return response()->json([
                "status"    => 200,
                "message"   => "Tasks approved successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks approval operation not successfull",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function reject(RejectTasksRequest $request, Tasks $task)
    {
        try {
            DB::transaction(function () use($task, $request) {
                $task->rejectReasons()->create([
                    'reason'            => $request->reason,
                    'created_by_userid' => auth()->id()
                ]);
                $task->status = 'Rejected';
                $task->save();
            });

            return response()->json([
                "status"    => 200,
                "message"   => "Tasks approved successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks approval operation not successfull",
                "error"     => $e->getMessage()
            ], 500);
        }
    }
}
