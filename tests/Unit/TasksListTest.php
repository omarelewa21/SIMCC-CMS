<?php

namespace Tests\Unit;

use App\Helpers\General\CollectionHelper;
use App\Models\DomainsTags;
use App\Models\Languages;
use App\Models\Tasks;
use App\Models\User;
use App\Rules\CheckMultipleVaildIds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TasksListTest extends TestCase
{
    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function test_tasks_listing()
    {
        // dd($this->list(new Request())['filterOptions']);
        $response = $this->withHeaders([
            'Authorization' => 'Bearer 2333|pZ7AnpCxWaLp5bHmeMjEbiaf7Mn1kYy3g3ezk9ID',
            'Accept'        => 'application/json'
        ])->get('/api/tasks');
        
        $response->assertStatus(200)
            ->assertJson([
                'data.filterOptions' => $this->list(new Request())
            ]);
    }


    private function list(Request $request)
    {
        $user = User::where('username', 'testAdmin')->first();

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
            $eagerload = $user->role_id == 0 || $user->role_id == 1 ? ['taskAnswers:id,task_id,answer,position','taskAnswers.taskLabels:task_answers_id,lang_id,content'] : [];
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

            if (!in_array($user->role_id,[0,1])) { //if role is not admin, filter by their user respective account
                $taskModel = $taskModel->where('created_by_userid', '!=', $user->id);
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

            return collect($data)->toArray();
        }

        catch(\Exception $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => $e->getMessage()
            ], 500);
        }
    }

    private function filterCollectionList ($collection,$filters,$filterBy="id") {

        try {
            foreach ($filters as $filter => $val) {
                $temp = explode(",",$filter);
                $nested = $temp[0];
                $filter = $temp[1];

                if($val) {
                    $val = explode(",",$val);
                    foreach($val as $row) {
                        $collection = $this->filterCollection ($collection,$filter,$row,$nested,$filterBy);
                    }
                }
            }
            return $collection;
        }
        catch(\Exception $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Filter unsuccessful"
            ]);
        }
    }

    private function filterCollection ($collection,$filter,$filterVal,$nested=0,$filterBy='id') {
        $collection = is_array($collection) ? collect($collection) : $collection;

        try {
            $filtered = $collection->filter(function ($fvalue, $fkey) use ($filter, $filterVal, $nested, $filterBy) {
                return $nested ? collect($fvalue[$filter])->contains($filterBy, $filterVal) : (collect($fvalue)->get($filter) == $filterVal);
            });


            return $filtered;
        }
        catch(\Exception $e){
            // do task when error11
            return response()->json([
                "status" => 500,
                "message" => "Filter unsuccessful"
            ]);
        }
    }
    
}
