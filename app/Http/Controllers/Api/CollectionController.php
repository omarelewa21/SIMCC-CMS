<?php

namespace App\Http\Controllers\Api;

use App\Helpers\General\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Models\Collections;
use App\Models\CollectionSections;
use App\Models\CompetitionLevels;
use App\Models\User;
use App\Helpers\General\CollectionCompetitionStatus;
use App\Http\Requests\collection\ApproveCollectionRequest;
use App\Http\Requests\collection\CollectionListRequest;
use App\Http\Requests\collection\DeleteCollectionsRequest;
use App\Http\Requests\collection\RejectCollectionRequest;
use App\Http\Requests\collection\UpdateCollectionRecommendationsRequest;
use App\Http\Requests\collection\UpdateCollectionSectionRequest;
use App\Http\Requests\collection\UpdateCollectionSettingsRequest;
use App\Http\Services\CollectionsService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class CollectionController extends Controller
{
    public function list (CollectionListRequest $request)
    {
        try{
            $collections = CollectionsService::getCollectionListCollection($request);
            $filterOptions = CollectionsService::getCollectionListFilterOptions($collections);
            if($request->has('competition_id') || $request->has('tag_id') ) {
                $collections = $this->filterCollectionList($collections, [
                    "1,competitions"    => $request->competition_id ?? false,
                    "1,tags"            => $request->tag_id ?? false
                ]);
            }
            $collectionsList = CollectionHelper::searchCollection(
                $request->search ? $request->search : null,
                $collections,
                array("identifier", "name", "description"),
                $request->limits ? $request->limits : 10
            );
            return response()->json([
                "status"    => 200,
                "data"      => array(
                    "filterOptions" => $filterOptions,
                    'taskLists'     => $collectionsList
                )
            ]);
        } catch(\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Retrieve collection unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function create (Request $request) {

        $validated = collect($request->validate([
            '*.settings.name' => 'required|distinct|unique:collection,name|regex:/^[\/\.\,\s\(\)\[\]\w-]*$/',
            '*.settings.identifier' => 'required|distinct|unique:collection,identifier|regex:/^[\/\_\w-]*$/',
            '*.settings.time_to_solve' => 'required|integer|min:0|max:600',
            '*.settings.initial_points' => 'integer|min:0',
            '*.settings.tags' => 'array',
            '*.settings.tags.*' => ['integer',Rule::exists('domains_tags','id')->where('is_tag',1)],
            '*.settings.description' => 'string|max:65535',
            '*.recommendations' => 'array',
            '*.recommendations.*.grade' => 'required_with:collection.*.recommendation.*.difficulty|integer|distinct', // add collection index infront of the grade for example grade 1 will be grade 11, as distinct function check through all the collection for the unqiue value
            '*.recommendations.*.difficulty' => 'required_with:collection.*.recommendation.*.grade|string',
            '*.sections' => 'required|array',
            '*.sections.*.groups' => 'required|array',
            '*.sections.*.groups.*.task_id' => 'array|required',
            '*.sections.*.groups.*.task_id.*' => 'required|integer|exists:tasks,id',
            '*.sections.*.sort_randomly' => 'boolean|required',
            '*.sections.*.allow_skip' => 'boolean|required',
            '*.sections.*.description' => 'string|max:65535',
        ]))->map(function ($item,$index) use (&$tags,&$recommendation,&$sections,&$role_id) {
            $item['settings']['status'] = (auth()->user()->role_id == 0 || auth()->user()->role_id == 1 ? 'Active' : 'Pending Moderation');
            $item['settings']['created_by_userid'] = auth()->user()->id;
            $tags[] = Arr::pull($item, 'settings.tags');
            $recommendation[] = Arr::pull($item, 'recommendations');
            $sections[] = $item['sections'];
            unset($item['sections']);
            return $item;
        })->pluck("settings")->toArray();

        try {
            DB::beginTransaction();

            $results = User::find(auth()->user()->id)->collection()->createMany($validated)->pluck('id')->map(function ($item,$collectionIndex) use (&$tags,&$recommendation,&$sections) {
                $collection = Collections::find($item);
                count($tags) == 0 ?: $collection->tags()->sync($tags[$collectionIndex]);

                // add recommendation of grade and difficulty to collection
                if(count($recommendation[$collectionIndex]) > 0 ) {
                    collect($recommendation[$collectionIndex])->map(function ($item) use ($collection,$collectionIndex) {
                        $collection->gradeDifficulty()->create(
                            [
                                "grade" => Str::after($item['grade'], (intval(($collectionIndex+1) / 10)+1)),
                                "difficulty" => $item['difficulty']
                            ]);
                    });
                }

                collect($sections[$collectionIndex])->map(function ($section,$index) use ($collection) {
                    $section['tasks'] =  Arr::pull($section, 'groups');
                    $section_id = $collection->sections()->create($section)->id;
                });
            });
            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "collection create successful"
            ]);
        } catch(\Exception $e){
            // do task when error
            return response()->json([
                "status"  => 500,
                "message" => "collection create unsuccessful" . $e->getMessage(),
                "error"   => $e->getMessage()
            ]);
        }
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

    public function approve(ApproveCollectionRequest $request)
    {
        try {
            Collections::whereIn('id', $request->ids)->update([
                'status' => 'Active'
            ]);
            return response()->json([
                "status"    => 200,
                "message"   => "Collections approved successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Collections approval operation not successfull",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function reject(RejectCollectionRequest $request, Collections $collection)
    {
        try {
            DB::transaction(function () use($collection, $request) {
                $collection->reject_reason()->create([
                    'reason'            => $request->reason,
                    'created_by_userid' => auth()->id()
                ]);
                $collection->status = 'Rejected';
                $collection->save();
            });

            return response()->json([
                "status"    => 200,
                "message"   => "Collections Rejected successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Tasks Rejection operation not successfull",
                "error"     => $e->getMessage()
            ], 500);
        }
    }
}
