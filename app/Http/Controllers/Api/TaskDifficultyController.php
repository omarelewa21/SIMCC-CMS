<?php

namespace App\Http\Controllers\Api;

use App\Helpers\General\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Models\CompetitionTaskDifficulty;
use App\Models\TaskDifficulty;
use App\Models\TaskDifficultyGroup;
use App\Rules\CheckDifficultyIdInGroup;
use App\Rules\CheckDifficultyIdUsed;
use App\Rules\CheckDifficultyGroupUsed;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TaskDifficultyController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            '*.name' => 'required|regex:/^[\.\,\s\(\)\[\]\w-]*$/|unique:difficulty_groups,name|min:3',
            '*.assign_marks' => 'required|boolean',
            '*.difficulty' => 'required|array',
            '*.difficulty.*.name' => 'required|regex:/^[\.\,\s\(\)\[\]\w-]*$/|min:3',
            '*.difficulty.*.correct_marks' => 'integer|required_if:*.assign_marks,1',
            '*.difficulty.*.wrong_marks' => 'integer|required_if:*.assign_marks,1',
            '*.difficulty.*.blank_marks' => 'integer|required_if:*.assign_marks,1',
        ]);

        try {
            DB::beginTransaction();

            $validated = collect($validated)->map(function ($row) {
                $row = [
                    ...$row,
                    'created_by_userid' => auth()->user()->id,
                ];

                $difficulty = collect(Arr::pull($row, "difficulty"))->map(function ($item, $index) {
                    return [
                        ...$item,
                        'sort_order' => $index + 1,
                    ];
                });

                $group_id = TaskDifficultyGroup::create($row)->id;

                TaskDifficultyGroup::find($group_id)->difficulty()->createMany($difficulty);

            });

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'add difficulty group successful'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'add difficulty group unsuccessful'
            ]);
        }
    }

    public function list (Request $request) {

        $vaildate = $request->validate([
            'id' => "integer",
            'name' => 'regex:/^[\.\,\s\(\)\[\]\w-]*$/',
            'status' => 'alpha',
            'limits' => 'integer',
            'page' => 'integer',
            'search' => 'max:255'
        ]);

        try {
            if($request->limits == "0") {
                $limits = 99999999;
            } else {
                $limits = $request->limits ?? 10; //set default to 10 rows per page
            }

            $searchKey = isset($vaildate['search']) ? $vaildate['search'] : null;

            $TaskDifficultyGroupModel = TaskDifficultyGroup::with(['difficulty' => function($query) {
                $query->orderBy('sort_order');
            }])->AcceptRequest(['status']);

            $returnFiltered = $TaskDifficultyGroupModel
                ->filter()
                ->get();

            $TaskDifficultyGroupCollection = collect($returnFiltered)->map(function ($item) { // match country id and add country name into the collection
                return $item;
            });

            /**
             * Lists of availabe filters
             */
            $taskDifficultyGroupStatus = $TaskDifficultyGroupCollection->map(function ($item) {
                return $item['status'];
            })->unique()->values();
            /**
             * EOL Lists of availabe filters
             */

            $availForSearch = array("name");
            $schoolList = CollectionHelper::searchCollection($searchKey, $TaskDifficultyGroupCollection, $availForSearch, $limits);
            $data = array("filterOptions" => ['status' => $taskDifficultyGroupStatus], "TaskDifficultyGroupSLists" => $schoolList);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        }

        catch(\Exception $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Retrieve difficulty group unsuccessful"
            ]);
        }

    }

    public function update(Request $request)
    {

        $validated = $request->validate([
            'id' => ['required','regex:/^[\'\;\.\,\s\(\)\[\]\w-]*$/',Rule::exists('difficulty_groups',"id")->where(function ($query) {
                $query->where('status','!=','deleted');
            })],
            'name' => 'sometimes|required|regex:/^[\.\,\s\(\)\[\]\w-]*$/|unique:difficulty_groups,name|min:3',
            'assign_marks' => 'required|boolean',
            'delete_id' => 'array',
            'delete_id.*' => ['sometimes','required', 'integer', 'exists:difficulty,id', new CheckDifficultyIdUsed],
            'difficulty' => 'required|array',
            'difficulty.*.id' => ['sometimes', 'required', 'integer', new CheckDifficultyIdInGroup],
            'difficulty.*.name' => 'required|regex:/^[\.\,\s\(\)\[\]\w-]*$/|min:3',
            'difficulty.*.correct_marks' => 'integer|required_if:*.assign_marks,1',
            'difficulty.*.wrong_marks' => 'integer|required_if:*.assign_marks,1',
            'difficulty.*.blank_marks' => 'integer|required_if:*.assign_marks,1',
        ]);
        try {
            $difficulty = Arr::pull($validated, 'difficulty');
            $sortOrderDifficulty = collect($difficulty)->map(function ($item, $index) {
                return [
                    ...$item,
                    'sort_order' => $index + 1
                ];
            });

            DB::beginTransaction();

            if(count($validated['delete_id']) > 0) {
                collect($validated['delete_id'])->map(function ($id) {
                    TaskDifficulty::findOrFail($id)->forceDelete();
                });
            }

            unset($validated['delete_id']);

            $difficultyGroup = TaskDifficultyGroup::find($validated['id']);
            $difficultyGroup->fill($validated);
            $difficultyGroup->save();

            collect($sortOrderDifficulty)->map(function ($item) use ($difficultyGroup) {
                if (isset($item['id'])) {
                    $difficulty = TaskDifficulty::find($item['id']);
                    $difficulty->fill($item);
                    $difficulty->save();
                } else {
                    $difficultyGroup->difficulty()->create($item);
                }
            });

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'update difficulty group successful'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'update difficulty group unsuccessful' .$e
            ]);
        }
    }

    public function delete_difficulty(Request $request)
    {
        $validated = $request->validate([
            'id' => 'array|required',
            'id.*' => ['required', 'integer', 'exists:difficulty,id', new CheckDifficultyIdUsed]// create a rule to check id alre
        ]);

        try {

            DB::beginTransaction();

            collect(Arr::collapse($validated))->map(function ($id) {
                TaskDifficulty::findOrFail($id)->forceDelete();
            });

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'delete difficulty successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'delete difficulty  unsuccessful'
            ]);
        }
    }

    public function delete(Request $request)
    {

        $validated = $request->validate([
            'id' => 'array|required',
            'id.*' => ['required', 'integer', 'exists:difficulty_groups,id',new CheckDifficultyGroupUsed]// create a rule to check id alre
        ]);

        try {

            DB::beginTransaction();

            collect(Arr::collapse($validated))->map(function ($id) {
//                TaskDifficulty::where('difficulty_groups_id',$id)->forceDelete();
//                TaskDifficultyGroup::findOrFail($id)->forceDelete();

                $query = TaskDifficultyGroup::findOrfail($id);
                $query->status = 'deleted';
                $query->save();

            });

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'delete difficulty successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'delete difficulty  unsuccessful' .$e
            ]);
        }
    }
}
