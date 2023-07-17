<?php

namespace App\Http\Controllers\Api;

use App\Helpers\General\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\CreateCollectionRequest;
use App\Models\Collections;
use App\Models\CollectionSections;
use App\Models\CompetitionLevels;
use App\Models\DomainsTags;
use App\Models\Competition;
use App\Models\Tasks;
use App\Models\User;
use App\Http\Requests\collection\DeleteCollectionsRequest;
use App\Http\Requests\collection\UpdateCollectionRecommendationsRequest;
use App\Http\Requests\collection\UpdateCollectionSectionRequest;
use App\Http\Requests\collection\UpdateCollectionSettingsRequest;
use App\Rules\CheckMultipleVaildIds;
use App\Services\Collection\CreateCollectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class CollectionController extends Controller
{
    public function list (Request $request) {

        $vaildated = $request->validate([
            "id" => "integer",
            'name' => 'regex:/^[\.\,\s\(\)\[\]\w-]*$/',
            'status' => 'alpha',
            'competition_id' => new CheckMultipleVaildIds(new Competition()),
            'tag_id' => new CheckMultipleVaildIds(new DomainsTags()),
            'limits' => 'integer',
            'page' => 'integer',
            'search' => 'max:255'
        ]);


        try {

            $limits = $request->limits ? $request->limits : 10; //set default to 10 rows per page
            $searchKey = isset($vaildated['search']) ? $vaildated['search'] : null;
            $eagerload = [
                'reject_reason:reject_id,reason,created_at,created_by_userid',
                'reject_reason.user:id,username',
                'reject_reason.role:roles.name',
                'tags:id,name',
                'gradeDifficulty',
                'sections',
            ];

            $collectionModel = Collections::with($eagerload)
                ->AcceptRequest(['status', 'id', 'name','identifier']);

            $collections = $collectionModel
                ->filter()
                ->get()
                ->map(function ($item) {
                        $section = $item->sections->map(function ($section)  {
                            foreach($section->tasks as $group) {
                                $tasks = Tasks::with('taskAnswers')->whereIn('id',$group['task_id'])->get()->map(function ($task) {
                                    return ['id' => $task->id,'task_title' => $task->languages->first()->task_title,'identifier' => $task->identifier] ;
                                });

                                $groups[] = $tasks;
                            }

                            $section->tasks = $groups;
                            return $section;
                        });

                        return collect($item)->except(['updated_at','created_at','reject_reason','last_modified_userid','created_by_userid']);
                });

            /**
             * Lists of availabe filters
             */
            $availCollectionsStatus = $collections->map(function ($item) {
                return $item['status'];
            })->unique()->values();
            $availCollectionsCompetition = $collections->map(function ($item) {
                $competitions = $item->get('competitions');

                return collect($competitions)->map(function ($competition) {
                    return ["id" => $competition['id'], "name" => $competition['competition']];
                });

            })->filter()->collapse()->unique()->values();
            $availTagType = $collections->map(function ($item) {
                $temp = [];

                foreach($item->toArray()['tags'] as $row) {
                    $temp[] = ["id" => $row['id'], "name" => $row['name']];

                }
                return $temp;
            })->filter()->collapse()->unique()->values();

            /**
             * EOL Lists of availabe filters
             */

            if($request->has('competition_id') || $request->has('tag_id') ) {
                /** addition filtering done in collection**/

                $collections = $this->filterCollectionList($collections,[
                        "1,competitions" => $request->competition_id ?? false, // 0 = non-nested, 1 = nested
                        "1,tags" =>  $request->tag_id ?? false
                    ]
                );

            }
          
          
            $availForSearch = array("identifier", "name", "description");
            $collectionsList = CollectionHelper::searchCollection($searchKey, $collections, $availForSearch, $limits);
            $data = array("filterOptions" => ['status' => $availCollectionsStatus, 'competition' => $availCollectionsCompetition, 'tags' => $availTagType], 'collectionList' => $collectionsList);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        }

        catch(\Exception $e){
            // do task when error
            return response()->json([
                "status"    => 500,
                "message"   => "Retrieve collection unsuccessful",
                "error"     => $e->getMessage()
            ]);
        }
    }

    public function create (CreateCollectionRequest $request)
    {
        DB::beginTransaction();
        try {
            (new CreateCollectionService())->create($request->all());
        } catch(\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Collections create was unsuccessful" . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => 'Collections created successfully'
        ]);
    }

    public function update_settings(UpdateCollectionSettingsRequest $request)
    {
        DB::beginTransaction();
        try {
            $collection = Collections::findOrFail($request->collection_id);
            $settings = $request->settings;
            if($collection->allowedToUpdateAll()){
                $collection->update($settings);
            }else{
                $collection->update([
                    'time_to_solve'     => $settings['time_to_solve'],
                    'initial_points'    => $settings['initial_points'],
                    'description'       => $settings['description']
                ]);
            }
            if(Arr::has($settings, 'tags') && count($settings['tags']) > 0){
                $collection->tags()->sync($settings['tags']);
            }

        } catch(\Exception $e){
            return response()->json([
                "status"    => 500,
                "message"   => "collection settings update unsuccessfull" . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "collection seetings update successful"
        ]);        
    }

    public function update_recommendations(UpdateCollectionRecommendationsRequest $request)
    {
        DB::beginTransaction();
        try {
            $collection = Collections::findOrFail($request->collection_id);
            $collection->gradeDifficulty()->delete();
            if(count($request->recommendations) > 0 ) {
                collect($request->recommendations)->map(function ($item) use ($collection) {
                    $collection->gradeDifficulty()->create(
                        [
                            "grade"         => $item['grade'],
                            "difficulty"    => $item['difficulty']
                        ]);
                });
            }
        } catch(\Exception $e){
            return response()->json([
                "status"  => 500,
                "message" => "collection recommendation update successful" . $e->getMessage(),
                "error"   => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "collection recommendation update successful"
        ]);
    }

    public function add_sections(Request $request)
    {
        $validated = $request->validate([
            'collection_id' => 'required|integer|exists:collection,id',
            'groups' => 'required|array',
            'groups.*.task_id' => 'array|required',
            'groups.*.task_id.*' => 'required|integer',//exists:tasks,id
            'sort_randomly' => 'boolean|required',
            'allow_skip' => 'boolean|required',
            'description' => 'string|max:65535',
        ]);

        try {
            $collection_id = Arr::pull($validated, 'collection_id');
            $tasks =  json_encode(Arr::pull($validated, 'groups'),JSON_UNESCAPED_SLASHES);

            DB::beginTransaction();

            CollectionSections::insert(
            ["collection_id" => $collection_id,
              "description" => $validated['description'],
              "tasks" => $tasks,
              "allow_skip" =>  $validated['allow_skip'],
              "sort_randomly" => $validated['sort_randomly']
            ] 
            );
          
            $section = CollectionSections::orderBy('id', 'DESC')->first();
            DB::commit();

            CollectionSections::inset([
                'collection_id'     => $collection_id,
                'description'       => $validated['description'],
                'tasks'             => $tasks,
                'allow_skip'        => $validated['allow_skip'],
                'sort_randomly'     => $validated['sort_randomly']
            ]);

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "collection section update successful",
                "data" => $section
            ]);
        } catch(\Exception $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "collection section update unsuccessful ".$e
            ]);
        }
    }

    public function update_sections(UpdateCollectionSectionRequest $request)
    {
        DB::beginTransaction();

        try {
            $section = $request->section;
            $section['tasks'] = Arr::pull($section, 'groups');
            $this->CheckUploadedAnswersCount($request->collection_id);

            if($request->has('section_id')) {
                $results = CollectionSections::findOrFail($request->section_id);
                $results->update($section);
            } else {
                $section['collection_id'] = $request->collection_id;
                $results = CollectionSections::create($section);
            }

        } catch(\Exception $e){
            return response()->json([
                "status"    => 500,
                "message"   => "collection section update unsuccessful "  . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "collection section update successful",
            "data"      => $results
        ]);
    }

    public function delete_section (Request $request) {
        $validated = $request->validate([
            'collection_id' => 'required|integer|exists:collection,id',
            'id' => 'required|array',
            'id.*' => 'required|integer|distinct|exists:collection_sections,id'
        ]);

        $collection_id = Arr::pull($validated, 'collection_id');
        $sections_id = Arr::pull($validated, 'id');

        try {
            $this->CheckUploadedAnswersCount($collection_id);

            DB::beginTransaction();

            collect($sections_id)->map(function ($item) use($collection_id) {

                $sections = CollectionSections::where(['collection_id' => $collection_id, 'id' => $item])->firstOrFail();
                $sections->forceDelete();

            });

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "collection section delete successful"
            ]);


        } catch(\Exception $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "collection section delete unsuccessful"
            ]);
        }

    }

    public function delete(DeleteCollectionsRequest $request)
    {
        DB::beginTransaction();
        try {
            Collections::whereIn('id', $request->id)->get()
                ->each(fn($collection) => $collection->delete());

        } catch(\Exception $e){
            return response()->json([
                "status"    => 500,
                "message"   => "collection delete unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
        DB::commit();
        return response()->json([
            "status"  => 200,
            "message" => "collection delete successful"
        ]);
    }

    private function CheckUploadedAnswersCount($collection_id) {
        $uploadAnswersCount = CompetitionLevels::with(['participantsAnswersUploaded'])->where('collection_id',$collection_id)->get()->pluck('participantsAnswersUploaded')->flatten()->count();
        $uploadAnswersCount == 0 ?:  abort(403, 'Unauthorized action, Answers have been uploaded to collection');;
    }
}
