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
use App\Rules\CheckAnswerLabelEqual;
use App\Rules\CheckEqualGradeDifficulty;
use App\Rules\CheckMissingGradeDifficulty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\User;

class TasksController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $validated = collect($request->validate([
            '*.title' => 'required|distinct|unique:task_contents,task_title|regex:/^[\.\,\s\(\)\[\]\w-]*$/',
            '*.identifier' => 'required|distinct|unique:tasks,identifier|regex:/^[\_\w-]*$/',
            '*.tag_id' => 'array|nullable',
            '*.tag_id.*' => ['exclude_if:*.tag_id,null','integer', Rule::exists('domains_tags', 'id')->where(function ($query) {
                $query->where('status', 'active')
                    ->whereNotNull('domain_id')
                    ->orWhere('is_tag',1);
            })],
            '*.description' => 'max:255',
            '*.solutions' => 'max:255',
            '*.image' => 'exclude_if:*.image,null|max:1000000',
            '*.recommended_grade' => 'required_with:*.recommended_difficulty|array',
            '*.recommended_grade.*' => ['integer', new CheckMissingGradeDifficulty('recommended_difficulty')],
            '*.recommended_difficulty' => 'required_with:*.recommended_grade|array',
            '*.recommended_difficulty.*' => ['string', 'max:255', new CheckMissingGradeDifficulty('recommended_grade')],
            '*.content' => 'string|max:65535',
            '*.answer_type' => 'required|integer|exists:answer_type,id',
            '*.answer_structure' => 'required|integer|min:1|max:4',
            '*.answer_sorting' => 'integer|nullable|required_if:*.answer_type_id,1|min:1|max:2', //
            '*.answer_layout' => 'integer|nullable|required_if:*.answer_type_id,1|min:1|max:2',
            '*.image_label' => 'integer|min:0|max:1',
            '*.labels' => 'required|array',
            '*.labels.*' => 'nullable',
            '*.answers' => ['required', 'array', new CheckAnswerLabelEqual],
            '*.answers.*' => 'string|max:65535|nullable',
        ]))
            ->map(function ($row) { //add created userid to arrays.
                $row['created_by_userid'] = auth()->user()->id;
                return $row;
            })->toArray();

        try {
            DB::beginTransaction();

            $counter = 0;

            User::find(auth()->user()->id)->tasks()->createMany($validated)->map(function ($row) use (&$counter, $validated) {
                $row = collect(array_merge($validated[$counter], $row->toArray()));

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
                        for ($i = 0; $i < count($validated[$counter]['recommended_grade']); $i++) {
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
        }
        catch(\Exception $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Tasks create unsuccessful" .$e
            ]);
        }
    }

    public function list(Request $request)
    {
        $vaildate = $request->validate([
            'id' => 'integer',
            'identifier' => 'regex:/^[\_\w-]*$/',
            'lang_id' => new CheckMultipleVaildIds(new Languages()),
            'tag_id' => new CheckMultipleVaildIds(new DomainsTags()),
            'status' => 'string|max:255',
            'limits' => 'integer',
            'page' => 'integer',
            'search' => 'string|max:255'
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
                ->where('status', '!=', 'deleted');

            if (!in_array(auth()->user()->role_id,[0,1])) { //if role is not admin, filter by their user respective account
                $taskModel = $taskModel->where('created_by_userid', '!=', auth()->user()->id);
            }

            $returnFiltered = $taskModel
                ->filter()
                ->get()
                ->makeHidden($hide);

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
                    if($row['domain_id'] == null && !$row['is_tag']) {
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

            /**
             * EOF Lists of availabe filters
             */

//            $engTitle = $taskTitle->filter(function ($item) { //get all english title
//                return strtolower($item['with_title']['name']) == 'english';
//            })->map(function ($row) {
//                return $row['with_title']['title'];
//            });

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

            $availForSearch = array("identifier", "title", "description","languages");
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
                "message" => "Retrieve school unsuccessful"
            ]);
        }

    }

    public function update_settings (Request $request) {

        $validated = $request->validate([
            'id' => 'required|integer|exists:tasks,id',
            'title' => ['required','string',Rule::unique('task_contents','task_title')->where(function ($query) {
                return $query->where('task_title', '!=', "2022-DOKA-PAPERP-SECA-Q01");
            })],
            'identifier' => 'required|regex:/^[\_\w-]*$/',
            'tag_id' => 'array|nullable',
            'tag_id.*' => ['exclude_if:*.tag_id,null','integer', Rule::exists('domains_tags', 'id')->where('status', 'active')],
            'description' => 'max:255',
            'solutions' => 'max:255',
            'image' => 'exclude_if:*.image,null|max:1000000',
        ]);

        try {
            $task = Tasks::find($validated['id']);
            if($validated['identifier'] != $task->identifier) {
                $task->identifier = $validated['identifier'];
            }
            $task->description = $validated['description'];
            $task->solutions = $validated['solutions'];
            $task->taskImage()->update(['image_string' => $validated['image']]);
            $task->taskTags()->sync($validated['tag_id']);
            $task->taskContents->first()->task_title = $validated['title'];
            $task->push();

            return response()->json([
                "status" => 200,
                "message" => "Tasks update successful"
            ]);
        }
        catch(\Exception $e){
            // do task when error
            return response()->json([
                "status" => 200,
                "message" => "Tasks update unsuccessful" .$e
            ]);
        }
    }

    public function update_content (Request $request) {

        $validated = $request->validate([
            'id' => 'required|integer|exists:tasks,id',
            're-moderate' => 'required|boolean',
            'taskContents' => 'required|array',
            'taskContents.*.title' => 'required|string|max:255',
            'taskContents.*.lang_id' => 'required|integer',
            'taskContents.*.content' => 'required|string|max:65535'
        ]);

        try {
            DB::beginTransaction();

            foreach($validated['taskContents'] as $content) {
                $task = Tasks::findOrFail($validated['id'])
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

            if($validated['re-moderate']) {
                Tasks::findOrFail($validated['id'])
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
            // do task when error
            return response()->json([
                "status" => 200,
                "message" => "Tasks update unsuccessful"
            ]);
        }
    }

    public function update_recommendation (Request $request) {

        $validated = $request->validate([
            'id' => 'required|integer|exists:tasks,id',
            'recommended_grade' => 'required|array',
            'recommended_grade.*' => ['integer', new CheckMissingGradeDifficulty('recommended_difficulty')],
            'recommended_difficulty' => 'required|array',
            'recommended_difficulty.*' => ['string', 'max:255', new CheckMissingGradeDifficulty('recommended_grade')],
        ]);

        try {
            Tasks::find($validated['id'])->gradeDifficulty()->delete();

            for ($i = 0; $i < count($validated['recommended_grade']); $i++) {
                Tasks::find($validated['id'])->gradeDifficulty()->create(
                    [
                        "grade" => $validated['recommended_grade'][$i],
                        "difficulty" => $validated['recommended_difficulty'][$i],
                    ]);
            }

            return response()->json([
                "status" => 200,
                "message" => "Tasks update successful"
            ]);
        }
         catch(\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Tasks update unsuccessful"
            ]);
         }
    }

    public function update_answer (Request $request) {

        $validated = collect($request->validate([
            'id' => 'required|integer|exists:tasks,id',
            'answer_type' => 'required|integer|exists:answer_type,id',
            'answer_structure' => 'required|integer|min:1|max:4',
            'answer_sorting' => 'integer|nullable|required_if:answer_type,1|min:1|max:2',
            'answer_layout' => 'integer|nullable|required_if:answer_type,1|min:1|max:2',
//            'correct_answer' => 'required_if:answer_type,1|array',
//            'correct_answer.*' => 'required_if:answer_type,1|string|max:65535',
            'labels' => 'required|array',
            'labels.*' => 'nullable',
            'answers' => ['required', 'array', new CheckAnswerLabelEqual],
            'answers.*' => 'string|max:65535|nullable',
        ]));

        $answers = Arr::pull($validated, 'answers');
        $labels = Arr::pull($validated, 'labels');

        try {
            $task = Tasks::findOrFail($validated['id']);
            $return = $task->update($validated->toArray());

            if($return)
            {
                $allAnswersId = TasksAnswers::where('task_id',$validated['id'])->pluck('id')->toArray();
                TasksLabels::whereIn('task_answers_id',$allAnswersId)->delete();
                TasksAnswers::where('task_id',$validated['id'])->delete();

                $answers = collect($answers)->map(function ($answer, $key) use ($validated) {

                    $temp = array([
                        'task_id' => $validated['id'],
                        'lang_id' => env('APP_DEFAULT_LANG'),
                        'answer' => $answer,
                        'position' => $key + 1,
                    ]);
                    return $temp;

                })->collapse();

                // add task answers
                $labels = Tasks::find($validated['id'])->taskAnswers()->createMany($answers)->pluck('id')->map(function ($answerId, $key) use ($labels) {

                    $temp = array([
                        'task_answers_id' => $answerId,
                        'lang_id' => env('APP_DEFAULT_LANG'),
                        'content' => $labels[$key],
                    ]);
                    return $temp;

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
                ]);
            }

        }
         catch(\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Tasks update unsuccessful"
            ]);
         }
    }
}
