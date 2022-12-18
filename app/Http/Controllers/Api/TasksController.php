<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DomainsTags;
use App\Models\Languages;
use App\Models\Tasks;
use App\Models\TasksAnswers;
use App\Models\TasksLabels;
use App\Rules\CheckMultipleVaildIds;
use Illuminate\Support\Arr;
use App\Helpers\General\CollectionHelper;
use App\Http\Requests\task\DeleteTaskRequest;
use App\Http\Requests\tasks\StoreTaskRequest;
use App\Http\Requests\tasks\UpdateTaskAnswerRequest;
use App\Http\Requests\tasks\UpdateTaskContentRequest;
use App\Http\Requests\tasks\UpdateTaskRecommendationsRequest;
use App\Http\Requests\tasks\UpdateTaskSettingsRequest;
use Illuminate\Http\Request;
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
            $eagerload = auth()->user()->role_id == 0 || auth()->user()->role_id == 1 ? ['taskAnswers:id,task_id,answer,position','taskAnswers.taskLabels:task_answers_id,lang_id,content'] : [];
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
                ->AcceptRequest(['id','status', 'identifier'])
                ->where('tasks.status', '!=', 'deleted');

            if (!in_array(auth()->user()->role_id,[0,1])) { //if role is not admin, filter by their user respective account
                $taskModel = $taskModel->where('created_by_userid', '!=', auth()->user()->id);
            }

            $returnFiltered = Tasks::applyFilter($taskModel, $request)->get()->makeHidden($hide);

            $taskCollection = collect($returnFiltered);

            $taskTitle = $taskCollection->map(function ($item) {
                foreach($item['languages'] as $row) {
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

            $availLang = $taskTitle->map(function ($item,$key) {
                if(isset($item['no_title'])) {
                    return $item['no_title'];
                }
            })->unique()->values();

            $availDomainType = $taskCollection->map(function ($item) {
                $temp = [];
                foreach($item->toArray()['tags'] as $row) {
                    if($row['domain_id'] && !$row['is_tag']) {
                        $temp[] = ["id" => $row['id'], "name" => $row['name']];
                    }
                }
                return $temp;
            })->filter()->collapse()->unique()->values();

            $availTagType = $taskCollection->map(function ($item) {
                $temp = [];
                foreach($item->toArray()['tags'] as $row) {
                    if($row['is_tag']) {
                        $temp[] = ["id" => $row['id'], "name" => $row['name']];
                    }
                }
                return $temp;
            })->filter()->collapse()->unique()->values();

            $taskCollection = $taskCollection->map(function ($item, $key) use ($taskTitle){ //map title into collection row
                $item['title'] = $taskTitle[$key]['with_title']['title'];
                return $item;
            });

            if($request->has('lang_id') || $request->has('tag_id') ) {
                /** addition filtering done in collection**/

                $taskCollection = $this->filterCollectionList($taskCollection,[
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
        }

        catch(\Exception $e){
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

    public function delete(Tasks $task, DeleteTaskRequest $request)
    {
        
    }
}
